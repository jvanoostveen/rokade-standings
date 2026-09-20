#!/usr/bin/env node
import { existsSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { chromium } from 'playwright';
import { marked } from 'marked';

import { config, outputDir, projectDir } from './lib/config.mjs';

const bron = resolve(projectDir, 'docs/handleiding/README.md');
const doel = resolve(projectDir, 'docs/handleiding/Handleiding-Rokade-Standen.pdf');
const basis = resolve(projectDir, 'docs/handleiding');

/**
 * De PDF is de handleiding zonder video: alles tussen de web-markeringen valt
 * eruit, zodat één bron beide vormen voedt zonder dat de PDF naar beelden
 * verwijst die je op papier niet kunt afspelen.
 */
function zonderWebdelen(markdown) {
	return markdown
		.replace(/<!--\s*alleen-web:start\s*-->[\s\S]*?<!--\s*alleen-web:eind\s*-->\n?/g, '')
		.replace(/\n{3,}/g, '\n\n');
}

function slug(tekst) {
	return tekst
		.toLowerCase()
		.replace(/<[^>]+>/g, '')
		.normalize('NFD')
		.replace(/[̀-ͯ]/g, '')
		.replace(/[^a-z0-9]+/g, '-')
		.replace(/^-|-$/g, '');
}

function opname() {
	const bestand = resolve(outputDir, 'opname.json');
	if (!existsSync(bestand)) return null;
	try {
		return JSON.parse(readFileSync(bestand, 'utf8'));
	} catch (error) {
		return null;
	}
}

// Breedte van de tekstkolom op A4 met de marges hieronder.
const KOLOM_MM = 178;
const MAX_HOOGTE_MM = 130;
// Een beeld van een heel venster is context; op ware grootte zou het de pagina
// opeten terwijl het maar een paar velden toelicht.
const VENSTER_MM = 116;

/** Breedte en hoogte in pixels uit de PNG-kop. */
function pngFormaat(bestand) {
	const kop = readFileSync(bestand).subarray(16, 24);
	return { breedte: kop.readUInt32BE(0), hoogte: kop.readUInt32BE(4) };
}

/**
 * Hoe groot een schermafbeelding op papier hoort te staan. Uitsnedes krijgen
 * ruwweg hun schermgrootte, zodat de tekst leesbaar blijft; hele vensters
 * worden bewust kleiner afgedrukt, want daar gaat het om de plek, niet om de
 * letters.
 */
function afdrukmaat(bestand) {
	const pixels = pngFormaat(bestand);
	const css = { breedte: pixels.breedte / config.scale, hoogte: pixels.hoogte / config.scale };
	const heelVenster = css.breedte >= 1200 && css.hoogte >= 700;
	const mmPerPixel = css.breedte < 500 ? 0.24 : 0.19;

	let breedte = heelVenster ? VENSTER_MM : Math.min(KOLOM_MM, css.breedte * mmPerPixel);
	let hoogte = (breedte * css.hoogte) / css.breedte;
	if (hoogte > MAX_HOOGTE_MM) {
		hoogte = MAX_HOOGTE_MM;
		breedte = (hoogte * css.breedte) / css.hoogte;
	}
	return { breedte: Math.round(breedte), hoogte: Math.round(hoogte) };
}

const STIJL = `
	@page { size: A4; margin: 18mm 16mm 20mm; }
	:root { --tekst: #16202c; --grijs: #5b6773; --lijn: #d6dce3; --accent: #1d4f2f; }
	* { box-sizing: border-box; }
	body { margin: 0; color: var(--tekst); font: 10.5pt/1.55 -apple-system, "Helvetica Neue", Arial, sans-serif; }
	h1, h2, h3, h4 { line-height: 1.25; break-after: avoid; }
	h1 { font-size: 21pt; margin: 0 0 6pt; }
	h2 { font-size: 15pt; margin: 22pt 0 6pt; padding-bottom: 4pt; border-bottom: 1px solid var(--lijn); }
	h3 { font-size: 12pt; margin: 14pt 0 4pt; }
	p, ul, ol, table { break-inside: avoid-page; }
	p { margin: 0 0 7pt; }
	a { color: var(--accent); text-decoration: none; }
	code { font: 9.5pt/1.4 "SF Mono", Menlo, Consolas, monospace; background: #f1f4f7; padding: 1pt 3pt; border-radius: 3px; }
	pre { background: #f1f4f7; border: 1px solid var(--lijn); border-radius: 5px; padding: 8pt 10pt; overflow: hidden; break-inside: avoid; }
	pre code { background: none; padding: 0; }
	blockquote { margin: 10pt 0; padding: 7pt 12pt; border-left: 3px solid #e0a800; background: #fdf8e8; }
	blockquote p:last-child { margin-bottom: 0; }
	table { width: 100%; border-collapse: collapse; margin: 8pt 0 12pt; font-size: 9.5pt; }
	th, td { border: 1px solid var(--lijn); padding: 5pt 7pt; text-align: left; vertical-align: top; }
	th { background: #f1f4f7; }
	hr { border: 0; border-top: 1px solid var(--lijn); margin: 18pt 0; }
	img { max-width: 100%; height: auto; border: 1px solid var(--lijn); border-radius: 4px; display: block; margin: 4pt 0 12pt; }
	img.venster { box-shadow: 0 1pt 4pt rgba(22, 32, 44, .10); }
	p:has(> img) { break-inside: avoid; }
	.omslag { height: 247mm; display: flex; flex-direction: column; justify-content: center; break-after: page; }
	.omslag .merk { font-size: 10pt; letter-spacing: .14em; text-transform: uppercase; color: var(--grijs); }
	.omslag h1 { font-size: 30pt; margin: 6pt 0 10pt; }
	.omslag .onder { font-size: 11pt; color: var(--grijs); max-width: 120mm; }
	.omslag dl { margin: 22pt 0 0; font-size: 10pt; color: var(--grijs); }
	.omslag dt { float: left; width: 34mm; clear: left; }
	.omslag dd { margin: 0 0 3pt 34mm; }
	.inhoud { break-after: page; }
	/* De koppen dragen hun eigen nummering al; een lijstnummer erbij leest dubbel. */
	.inhoud ol { margin: 0; padding: 0; list-style: none; }
	.inhoud > ol > li { margin-bottom: 6pt; font-weight: 600; }
	.inhoud ol ol { padding-left: 14pt; margin: 2pt 0 8pt; }
	.inhoud ol ol li { font-weight: 400; color: var(--grijs); margin-bottom: 1pt; }
	h2 { break-before: auto; }
	/* Deel 2 is een zelfstandig hoofdstuk in de gedrukte handleiding. */
	h2#deel-2-standen-op-een-pagina-zetten { break-before: page; }
`;

function inhoudsopgave(tokens) {
	const koppen = tokens.filter((token) => token.type === 'heading' && (token.depth === 2 || token.depth === 3));
	let html = '<nav class="inhoud"><h2>Inhoud</h2><ol>';
	let open = false;
	for (const kop of koppen) {
		const regel = `<a href="#${slug(kop.text)}">${marked.parseInline(kop.text)}</a>`;
		if (kop.depth === 2) {
			if (open) html += '</ol></li>';
			html += `<li>${regel}<ol>`;
			open = true;
		} else if (open) {
			html += `<li>${regel}</li>`;
		}
	}
	if (open) html += '</ol></li>';
	return `${html}</ol></nav>`;
}

async function main() {
	const markdown = zonderWebdelen(readFileSync(bron, 'utf8'));
	const tokens = marked.lexer(markdown);
	const meta = opname();

	const renderer = new marked.Renderer();
	renderer.heading = function ({ tokens: koptokens, depth }) {
		const tekst = this.parser.parseInline(koptokens);
		return `<h${depth} id="${slug(tekst)}">${tekst}</h${depth}>\n`;
	};
	renderer.image = function ({ href, title, text }) {
		if (!href.startsWith('media/')) {
			return `<img src="${href}" alt="${text || ''}">`;
		}
		const bestand = join(basis, href);
		const maat = afdrukmaat(bestand);
		const soort = maat.breedte === VENSTER_MM ? ' class="venster"' : '';
		return `<img src="file://${bestand}" alt="${text || ''}"${title ? ` title="${title}"` : ''}${soort} style="width:${maat.breedte}mm;height:${maat.hoogte}mm;">`;
	};

	// De titel staat op de omslag; in de inhoud zou hij hem dubbel maken.
	const zonderTitel = tokens.filter((token, index) => !(index === 0 && token.type === 'heading' && token.depth === 1));
	const body = marked.parser(zonderTitel, { renderer });

	const datum = new Date(meta?.opgenomen_op || Date.now()).toLocaleDateString('nl-NL', { day: 'numeric', month: 'long', year: 'numeric' });
	const omslag = `
		<header class="omslag">
			<p class="merk">Schaken in Hoogland</p>
			<h1>Handleiding Rokade Standen</h1>
			<p class="onder">De WordPress-plugin instellen en de standen uit een Rokade-export op een pagina zetten.</p>
			<dl>
				<dt>Plugin</dt><dd>versie ${meta?.plugin || '—'}</dd>
				<dt>Getoetst met</dt><dd>WordPress ${meta?.wordpress || '—'}</dd>
				<dt>Beelden van</dt><dd>${datum}</dd>
			</dl>
		</header>`;

	const html = `<!DOCTYPE html><html lang="nl"><head><meta charset="utf-8"><title>Handleiding Rokade Standen</title><style>${STIJL}</style></head><body>${omslag}${inhoudsopgave(tokens)}<main>${body}</main></body></html>`;

	const tijdelijk = mkdtempSync(join(tmpdir(), 'handleiding-pdf-'));
	const htmlBestand = join(tijdelijk, 'handleiding.html');
	writeFileSync(htmlBestand, html);

	const browser = await chromium.launch();
	const page = await browser.newPage();
	await page.goto(`file://${htmlBestand}`, { waitUntil: 'load' });
	await page.emulateMedia({ media: 'print' });
	await page.pdf({
		path: doel,
		format: 'A4',
		printBackground: true,
		margin: { top: '18mm', bottom: '20mm', left: '16mm', right: '16mm' },
		displayHeaderFooter: true,
		headerTemplate: '<div></div>',
		footerTemplate: `<div style="width:100%;padding:0 16mm;font:8pt -apple-system,Arial,sans-serif;color:#5b6773;display:flex;justify-content:space-between;">
			<span>Handleiding Rokade Standen ${meta?.plugin ? `· versie ${meta.plugin}` : ''}</span>
			<span class="pageNumber"></span>
		</div>`,
	});
	await browser.close();
	rmSync(tijdelijk, { recursive: true, force: true });

	console.log(`PDF gemaakt: ${doel}`);
}

main().catch((error) => {
	console.error(error.message);
	process.exitCode = 1;
});
