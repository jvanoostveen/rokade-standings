#!/usr/bin/env node
import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs';
import { join, resolve } from 'node:path';

import { outputDir, projectDir } from './lib/config.mjs';

const handleiding = resolve(projectDir, 'docs/handleiding/README.md');
const pdf = resolve(projectDir, 'docs/handleiding/Handleiding-Rokade-Standen.pdf');
const shotDir = resolve(outputDir, 'schermafbeeldingen');

// Keuzelijsten die uit de export komen in plaats van uit de plugin.
const GEGEVENSKEUZES = new Set(['Bron', 'Seizoen']);

const klachten = [];
const opmerkingen = [];

function pluginversie() {
	const inhoud = readFileSync(resolve(projectDir, 'rokade-standings.php'), 'utf8');
	const gevonden = inhoud.match(/^\s*\*\s*Version:\s*(\S+)/m);
	return gevonden ? gevonden[1] : '';
}

const markdown = readFileSync(handleiding, 'utf8');
const verwijzingen = new Set([
	...[...markdown.matchAll(/\((media\/[^)]+)\)/g)].map((match) => match[1]),
	...[...markdown.matchAll(/src="(media\/[^"]+)"/g)].map((match) => match[1]),
]);

// 1. Alles waar de handleiding naar wijst, moet er ook zijn.
for (const verwijzing of [...verwijzingen].sort()) {
	if (!existsSync(resolve(projectDir, 'docs/handleiding', verwijzing))) {
		klachten.push(`De handleiding verwijst naar ${verwijzing}, maar dat bestand bestaat niet.`);
	}
}

// 2. Een beeld dat nergens in de tekst staat is bij een herziening blijven liggen.
for (const naam of readdirSync(shotDir).filter((bestand) => bestand.endsWith('.png')).sort()) {
	if (!verwijzingen.has(`media/schermafbeeldingen/${naam}`)) {
		klachten.push(`${naam} wordt in de handleiding niet gebruikt.`);
	}
}

const opnameBestand = resolve(outputDir, 'opname.json');
if (!existsSync(opnameBestand)) {
	klachten.push('Er is geen media/opname.json; neem het scenario eerst op met npm run capture.');
} else {
	const opname = JSON.parse(readFileSync(opnameBestand, 'utf8'));
	const versie = pluginversie();

	// 3. Beelden van een oudere pluginversie tonen mogelijk niet meer wat er nu gebeurt.
	if (versie && opname.plugin !== versie) {
		klachten.push(`De beelden zijn van plugin ${opname.plugin}, de code staat op ${versie}. Neem opnieuw op.`);
	}

	for (const scene of opname.scenes || []) {
		if (!scene.gelukt) {
			klachten.push(`Scène ${scene.naam} is vastgelopen: ${scene.fout}`);
		}
		for (const beeld of scene.schermafbeeldingen || []) {
			if (beeld.waarschuwing) {
				klachten.push(`${beeld.bestand} lijkt leeg (${beeld.waarschuwing}).`);
			}
		}
		if (scene.video && !existsSync(resolve(projectDir, 'docs/handleiding', scene.video))) {
			klachten.push(`De video van ${scene.naam} ontbreekt: ${scene.video}.`);
		}
		if (scene.videoformaat === 'webm') {
			opmerkingen.push(`De video van ${scene.naam} is webm gebleven; met Docker of ffmpeg wordt het een mp4.`);
		}

		// 4. Nieuwe keuzes in het blok horen ook in de tekst te staan. Bij bron en
		// seizoen is de lijst zelf gegevens — welke seizoenen er zijn verschilt per
		// installatie — dus daar telt alleen de standaardkeuze. Competitie en
		// weergave komen uit de plugin en horen wél volledig beschreven te staan.
		for (const keuze of scene.opties || []) {
			const alleenStandaard = GEGEVENSKEUZES.has(keuze.keuze);
			for (const optie of keuze.opties) {
				if (alleenStandaard && optie.waarde !== '') {
					continue;
				}
				const etiket = optie.label.replace(/\s*\(.*\)$/, '');
				if (etiket && !markdown.includes(etiket)) {
					klachten.push(`De keuze “${keuze.keuze}” biedt “${optie.label}” aan, maar de handleiding noemt die niet.`);
				}
			}
		}
	}

	// 5. De PDF hoort bij de nieuwste beelden.
	if (!existsSync(pdf)) {
		klachten.push('De PDF ontbreekt; bouw hem met npm run pdf.');
	} else {
		const nieuwsteBeeld = readdirSync(shotDir)
			.map((naam) => statSync(join(shotDir, naam)).mtimeMs)
			.reduce((hoogste, tijd) => Math.max(hoogste, tijd), 0);
		if (statSync(pdf).mtimeMs < nieuwsteBeeld) {
			klachten.push('De PDF is ouder dan de schermafbeeldingen; bouw hem opnieuw met npm run pdf.');
		}
	}
}

for (const opmerking of opmerkingen) {
	console.log(`· ${opmerking}`);
}

if (klachten.length) {
	console.error(`\n${klachten.length} punt(en) om na te lopen:`);
	for (const klacht of klachten) {
		console.error(`  ✖︎ ${klacht}`);
	}
	process.exitCode = 1;
} else {
	console.log('✔︎ Handleiding, beelden, video en PDF horen bij elkaar.');
}
