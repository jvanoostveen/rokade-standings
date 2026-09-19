#!/usr/bin/env node
import { spawnSync } from 'node:child_process';
import { existsSync, mkdirSync, mkdtempSync, readFileSync, readdirSync, renameSync, rmSync, statSync, writeFileSync } from 'node:fs';
import { homedir } from 'node:os';
import { tmpdir } from 'node:os';
import { basename, dirname, join, resolve } from 'node:path';
import { chromium } from 'playwright';

import { adminUrl, config, outputDir, projectDir, shotDir, videoDir } from './lib/config.mjs';
import { Ui } from './lib/ui.mjs';
import { wp, wpOk } from './lib/wp.mjs';
import instellen from './scenes/instellen.mjs';
import gebruiken from './scenes/gebruiken.mjs';

const SCENES = [instellen, gebruiken];

// Alleen nodig als de host zelf geen ffmpeg heeft; zie tools/handleiding/README.md.
const FFMPEG_IMAGE = process.env.FFMPEG_IMAGE || 'jrottenberg/ffmpeg:7.1-alpine';

const argumenten = process.argv.slice(2);
const alleen = (argumenten.find((arg) => arg.startsWith('--only=')) || '').replace('--only=', '');
const alleenSeed = argumenten.includes('--seed-only');
const behoudWebm = argumenten.includes('--keep-webm');

function pluginversie() {
	const bestand = resolve(projectDir, 'rokade-standings.php');
	const inhoud = spawnSync('sed', ['-nE', 's/^[[:space:]]*\\*[[:space:]]*Version:[[:space:]]*([^[:space:]]+).*/\\1/p', bestand], { encoding: 'utf8' });
	return (inhoud.stdout || '').trim().split('\n')[0] || 'onbekend';
}

function ffmpegPad() {
	const opPad = spawnSync('which', ['ffmpeg'], { encoding: 'utf8' });
	if (opPad.status === 0 && opPad.stdout.trim()) {
		return { pad: opPad.stdout.trim(), systeem: true };
	}

	// Playwright neemt zijn eigen ffmpeg mee. Die kan geen mp4 maken, maar wel
	// de opname opnieuw comprimeren; dat scheelt een veelvoud aan bestandsgrootte.
	const wortel = process.env.PLAYWRIGHT_BROWSERS_PATH || (
		process.platform === 'darwin' ? join(homedir(), 'Library', 'Caches', 'ms-playwright')
			: process.platform === 'win32' ? join(homedir(), 'AppData', 'Local', 'ms-playwright')
				: join(homedir(), '.cache', 'ms-playwright')
	);
	if (existsSync(wortel)) {
		for (const map of readdirSync(wortel).filter((naam) => naam.startsWith('ffmpeg-')).sort().reverse()) {
			for (const naam of ['ffmpeg-mac', 'ffmpeg-linux', 'ffmpeg-win64.exe']) {
				const kandidaat = join(wortel, map, naam);
				if (existsSync(kandidaat)) return { pad: kandidaat, systeem: false };
			}
		}
	}
	return { pad: '', systeem: false };
}

/** Draait ffmpeg in een container, zodat de host niets hoeft te installeren. */
function dockerBeschikbaar() {
	return spawnSync('docker', ['info'], { encoding: 'utf8' }).status === 0;
}

function naarMp4(pad, webm, { viaDocker = false } = {}) {
	const mp4 = webm.replace(/\.webm$/, '.mp4');
	const opties = ['-hide_banner', '-y', '-i', '', '-c:v', 'libx264', '-crf', '23', '-preset', 'slow', '-pix_fmt', 'yuv420p', '-movflags', '+faststart', ''];
	let commando;
	let argumentenlijst;

	if (viaDocker) {
		opties[3] = basename(webm);
		opties[opties.length - 1] = basename(mp4);
		commando = 'docker';
		argumentenlijst = ['run', '--rm', '-v', `${dirname(webm)}:/work`, '-w', '/work', pad, ...opties];
	} else {
		opties[3] = webm;
		opties[opties.length - 1] = mp4;
		commando = pad;
		argumentenlijst = opties;
	}

	const resultaat = spawnSync(commando, argumentenlijst, { encoding: 'utf8' });
	if (resultaat.status !== 0 || !existsSync(mp4)) {
		rmSync(mp4, { force: true });
		return null;
	}
	if (!behoudWebm) rmSync(webm, { force: true });
	return mp4;
}

/** Een mp4 als het kan, anders een kleinere webm. */
function verwerkVideo(webm) {
	const { pad, systeem } = ffmpegPad();

	if (systeem) {
		const mp4 = naarMp4(pad, webm);
		if (mp4) return { bestand: mp4, formaat: 'mp4' };
	}

	// Zonder ffmpeg op de host doet een container het werk; dat is dezelfde
	// omgeving als waar de site in draait, dus er hoeft niets bij geïnstalleerd.
	if (dockerBeschikbaar()) {
		const mp4 = naarMp4(FFMPEG_IMAGE, webm, { viaDocker: true });
		if (mp4) return { bestand: mp4, formaat: 'mp4' };
	}

	if (!pad) return { bestand: webm, formaat: 'webm', reden: 'geen ffmpeg en geen Docker gevonden' };

	// Laatste redmiddel: de ffmpeg van Playwright kan geen mp4 maken, maar de
	// opname wel flink kleiner.
	const kleiner = webm.replace(/\.webm$/, '.klein.webm');
	const resultaat = spawnSync(pad, ['-y', '-i', webm, '-c:v', 'libvpx', '-crf', '36', '-b:v', '0', '-deadline', 'good', '-cpu-used', '2', kleiner], { encoding: 'utf8' });
	if (resultaat.status !== 0 || !existsSync(kleiner)) {
		rmSync(kleiner, { force: true });
		return { bestand: webm, formaat: 'webm', reden: 'omzetten naar mp4 mislukte' };
	}
	if (statSync(kleiner).size >= statSync(webm).size) {
		rmSync(kleiner, { force: true });
		return { bestand: webm, formaat: 'webm', reden: 'omzetten naar mp4 mislukte' };
	}
	rmSync(webm, { force: true });
	renameSync(kleiner, webm);
	return { bestand: webm, formaat: 'webm', reden: 'omzetten naar mp4 mislukte' };
}

async function meldAan(browser) {
	if (!config.password) {
		throw new Error('Geen wachtwoord: zet WP_ADMIN_PASSWORD in tools/handleiding/.env (zie .env.example).');
	}
	const context = await browser.newContext({ viewport: config.viewport });
	const page = await context.newPage();
	await page.goto(`${config.baseUrl}/wp-login.php`);
	await page.fill('#user_login', config.user);
	await page.fill('#user_pass', config.password);
	await Promise.all([page.waitForURL('**/wp-admin/**', { timeout: 30000 }), page.click('#wp-submit')]);
	const staat = await context.storageState();
	await context.close();
	return staat;
}

async function speel(browser, scene, staat, meta) {
	console.log(`\n▶︎ ${scene.naam}: ${scene.titel}`);
	scene.seed();

	const tijdelijk = mkdtempSync(join(tmpdir(), `handleiding-${scene.naam}-`));
	const context = await browser.newContext({
		storageState: staat,
		viewport: config.viewport,
		deviceScaleFactor: config.scale,
		recordVideo: { dir: tijdelijk, size: config.viewport },
		locale: 'nl-NL',
		reducedMotion: 'reduce',
	});
	await Ui.install(context);

	const page = await context.newPage();
	const begin = Date.now();
	const log = [];
	const ui = new Ui(page, { scene: scene.naam, log });

	// De eerste pagina moet de overlay al dragen voordat de eerste stap valt.
	await page.goto(adminUrl('index.php'));
	await page.waitForLoadState('domcontentloaded');

	let fout = null;
	try {
		await scene.run({ page, ui, log });
	} catch (error) {
		fout = error;
		console.error(`  ✖︎ ${scene.naam} liep vast: ${error.message}`);
		await ui.schermafbeelding(`fout-${scene.naam}`, {}).catch(() => {});
	}

	const video = page.video();
	await context.close();

	mkdirSync(videoDir, { recursive: true });
	let videoBestand = '';
	let formaat = '';
	if (video) {
		const bron = await video.path();
		const doel = resolve(videoDir, `${scene.naam}.webm`);
		rmSync(doel, { force: true });
		rmSync(doel.replace(/\.webm$/, '.mp4'), { force: true });
		renameSync(bron, doel);
		const omgezet = verwerkVideo(doel);
		videoBestand = omgezet.bestand;
		formaat = omgezet.formaat;
		if (omgezet.reden) console.log(`  · video blijft zoals opgenomen (${omgezet.reden})`);
	}
	rmSync(tijdelijk, { recursive: true, force: true });

	meta.scenes.push({
		naam: scene.naam,
		titel: scene.titel,
		gelukt: !fout,
		fout: fout ? fout.message : null,
		video: videoBestand ? `media/video/${videoBestand.split('/').pop()}` : null,
		videoformaat: formaat,
		duur_seconden: Math.round((Date.now() - begin) / 1000),
		// Met de seconde erbij is een beeld uit de video terug te zoeken.
		stappen: log.filter((item) => item.type === 'stap').map((item) => ({ seconde: item.seconde, tekst: item.tekst })),
		schermafbeeldingen: log.filter((item) => item.type === 'schermafbeelding').map((item) => ({
			seconde: item.seconde,
			bestand: item.bestand,
			...(item.waarschuwing ? { waarschuwing: item.waarschuwing } : {}),
		})),
		opties: log.filter((item) => item.type === 'opties'),
		voorbeeldpagina: (log.find((item) => item.type === 'voorbeeldpagina') || {}).url || null,
	});

	const verdacht = log.filter((item) => item.waarschuwing);
	console.log(`  ✔︎ ${log.filter((i) => i.type === 'schermafbeelding').length} schermafbeeldingen in ${Math.round((Date.now() - begin) / 1000)} s, video: ${videoBestand || 'geen'}`);
	if (verdacht.length) {
		console.warn(`  ! ${verdacht.length} beeld(en) lijken leeg: ${verdacht.map((item) => item.naam).join(', ')}`);
	}
	if (fout) throw fout;
}

/**
 * Een gedeeltelijke opname mag het overzicht van de andere scène niet wissen:
 * alleen de opnieuw opgenomen scènes worden vervangen.
 */
function schrijfOverzicht(meta) {
	const bestand = resolve(outputDir, 'opname.json');
	let eerder = { scenes: [] };
	if (existsSync(bestand)) {
		try {
			eerder = JSON.parse(readFileSync(bestand, 'utf8'));
		} catch (error) {
			eerder = { scenes: [] };
		}
	}

	const namen = new Set(meta.scenes.map((scene) => scene.naam));
	const behouden = (eerder.scenes || []).filter((scene) => !namen.has(scene.naam));
	const volgorde = SCENES.map((scene) => scene.naam);
	meta.scenes = [...behouden, ...meta.scenes].sort((a, b) => volgorde.indexOf(a.naam) - volgorde.indexOf(b.naam));
	writeFileSync(bestand, `${JSON.stringify(meta, null, '\t')}\n`);
}

async function main() {
	const teSpelen = alleen ? SCENES.filter((scene) => scene.naam === alleen) : SCENES;
	if (!teSpelen.length) {
		throw new Error(`Onbekend scenario “${alleen}”. Kies uit: ${SCENES.map((s) => s.naam).join(', ')}.`);
	}

	if (alleenSeed) {
		teSpelen.forEach((scene) => {
			console.log(`· staat klaarzetten voor ${scene.naam}`);
			scene.seed();
		});
		return;
	}

	mkdirSync(shotDir, { recursive: true });
	mkdirSync(videoDir, { recursive: true });

	const meta = {
		opgenomen_op: new Date().toISOString(),
		basis_url: config.baseUrl,
		wordpress: wpOk(['core', 'version']),
		plugin: pluginversie(),
		taal: wpOk(['option', 'get', 'WPLANG']) || 'en_US',
		thema: wpOk(['option', 'get', 'stylesheet']),
		viewport: config.viewport,
		scenes: [],
	};

	const browser = await chromium.launch();
	let fout = null;
	try {
		const staat = await meldAan(browser);
		for (const scene of teSpelen) {
			try {
				await speel(browser, scene, staat, meta);
			} catch (error) {
				fout = fout || error;
			}
		}
	} finally {
		await browser.close();
		schrijfOverzicht(meta);
	}

	console.log(`\nOverzicht weggeschreven naar docs/handleiding/media/opname.json`);
	if (fout) process.exitCode = 1;
}

main().catch((error) => {
	console.error(error.message);
	process.exitCode = 1;
});
