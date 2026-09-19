import { adminUrl, config } from '../lib/config.mjs';
import { VOORBEELD, voorGebruiken } from '../lib/seed.mjs';
import {
	canvas,
	keuze,
	keuzeOpties,
	openBlokZijbalk,
	openNieuwePagina,
	publiceer,
	sluitInserter,
	voegBlokToe,
	wachtOpVoorbeeld,
	zetTitel,
	zijbalk,
} from '../lib/editor.mjs';

const PANEEL = '.components-panel__body:has-text("Standen instellen")';

/** Loopt elke keuze van een dropdown langs, zodat de video ze allemaal toont. */
async function loopOptiesLangs({ page, ui, log }, label, { eindwaarde, toelichting = {} }) {
	const opties = await keuzeOpties(page, label);
	log.push({ type: 'opties', keuze: label, opties });
	const select = keuze(page, label);

	for (const optie of opties) {
		await ui.stap(toelichting[optie.waarde] || `${label}: ${optie.label}`, { wacht: 200 });
		await ui.kies(select, optie.waarde, { wacht: 400 });
		await wachtOpVoorbeeld(page);
		await ui.pauze(900);
	}

	if (eindwaarde !== undefined) {
		await ui.kies(select, eindwaarde, { wacht: 400 });
		await wachtOpVoorbeeld(page);
	}
	return opties;
}

export default {
	naam: 'gebruiken',
	titel: 'Standen op een pagina zetten',
	seed: voorGebruiken,

	async run({ page, ui, log }) {
		await ui.stap('Deel 2 — de standen op een pagina zetten.', { wacht: 1600 });

		await ui.ga(adminUrl('edit.php?post_type=page'));
		await ui.stap('Pagina’s → Nieuwe pagina.', { wacht: 800 });
		await page.locator('#menu-pages').hover();
		await ui.pauze(600);
		const nieuwLink = page.locator('#menu-pages a[href="post-new.php?post_type=page"]').first();
		if (await nieuwLink.count()) {
			await ui.wijs(nieuwLink, { klik: true });
		}
		await openNieuwePagina(page, ui);
		await ui.schermafbeelding('gebruiken-01-nieuwe-pagina', {});

		await ui.stap('Geef de pagina eerst een titel.', { wacht: 700 });
		await zetTitel(page, ui, VOORBEELD.titel);
		await ui.schermafbeelding('gebruiken-02-titel', {
			locator: canvas(page).locator('.editor-post-title__input').first(),
			marge: 40,
		});

		await ui.stap('Open het invoegmenu linksboven en zoek op “Rokade”.', { wacht: 700 });
		const blokOptie = await voegBlokToe(page, ui, 'Rokade', 'Rokade standen');
		await ui.schermafbeelding('gebruiken-03-blok-invoegen', {
			locator: page.locator('.block-editor-inserter__menu, .block-editor-tabbed-sidebar').first(),
			marge: 8,
			maxHoogte: 420,
		});
		await ui.klik(blokOptie, { wacht: 1500 });
		await sluitInserter(page);
		await wachtOpVoorbeeld(page);
		await ui.pauze(1200);

		await ui.stap('Het blok toont meteen een voorbeeld van wat bezoekers straks zien.', { wacht: 1400 });
		await openBlokZijbalk(page, ui);
		await ui.schermafbeelding('gebruiken-04-blok-toegevoegd', {});

		const paneel = page.locator(PANEEL).first();
		await ui.stap('In de zijbalk staan vier keuzes: bron, seizoen, competitie en weergave.', { wacht: 1600 });
		await ui.schermafbeelding('gebruiken-05-paneel', { locator: paneel, marge: 10 });

		await loopOptiesLangs({ page, ui, log }, 'Bron', {
			eindwaarde: 'jeugd',
			toelichting: {
				'': 'Bron — “Eerste bron” volgt de bovenste regel uit de instellingen.',
				jeugd: 'Bron — Jeugd: de export met de jeugdcompetities.',
				senioren: 'Bron — Senioren: een tweede export met een eigen seizoenenlijst.',
			},
		});
		await ui.stap('Voor dit voorbeeld kiezen we de bron Jeugd.', { wacht: 1200 });
		await ui.schermafbeelding('gebruiken-06-bron-jeugd', { locator: paneel, marge: 10 });

		await loopOptiesLangs({ page, ui, log }, 'Seizoen', {
			eindwaarde: '',
			toelichting: {
				'': 'Seizoen — “Meest recente seizoen” schuift vanzelf mee met een nieuwe export.',
			},
		});
		await ui.stap('We laten het op “Meest recente seizoen” staan; dan blijft de pagina vanzelf actueel.', { wacht: 1600 });
		await ui.schermafbeelding('gebruiken-07-seizoen', { locator: paneel, marge: 10 });

		await loopOptiesLangs({ page, ui, log }, 'Competitie', {
			eindwaarde: '',
			toelichting: {
				'': 'Competitie — “Alle competities” toont elke soort met eigen knoppen erboven.',
			},
		});
		await ui.stap('“Alle competities” laat bezoekers zelf wisselen tussen de soorten.', { wacht: 1500 });
		await ui.schermafbeelding('gebruiken-08-competitie', { locator: paneel, marge: 10 });

		await ui.stap('De weergave bepaalt hoe de standen in de pagina landen.', { wacht: 900 });
		await ui.kies(keuze(page, 'Weergave'), 'iframe', { wacht: 800 });
		await wachtOpVoorbeeld(page);
		await ui.pauze(1400);
		await ui.stap('“Oorspronkelijke Rokade-weergave” toont het exportbestand zoals Rokade het maakt.', { wacht: 1600 });
		await ui.schermafbeelding('gebruiken-09-weergave-iframe', {});

		await ui.kies(keuze(page, 'Weergave'), 'inline', { wacht: 800 });
		await wachtOpVoorbeeld(page);
		await ui.pauze(1200);
		await ui.stap('“Inline” neemt de lettertypes, kleuren en links van het thema over. Dat blijft de standaard.', { wacht: 1700 });
		await ui.schermafbeelding('gebruiken-10-weergave-inline', {});
		await ui.schermafbeelding('gebruiken-11-instellingen-samen', { locator: paneel, marge: 10 });

		await ui.stap('Publiceren.', { wacht: 700 });
		await publiceer(page, ui);
		await ui.schermafbeelding('gebruiken-12-publiceren', {
			locator: page.locator('.editor-post-publish-panel'),
			marge: 6,
			maxHoogte: 330,
		});

		const bekijk = page.locator('.editor-post-publish-panel').getByRole('link', { name: /bekijk/i }).first();
		const url = await bekijk.getAttribute('href');
		await ui.stap('En zo ziet de pagina eruit voor bezoekers.', { wacht: 900 });
		await ui.overgang(async () => {
			await page.goto(url.startsWith('http') ? url : `${config.baseUrl}${url}`);
			await page.waitForSelector('.schaken-standen', { timeout: 30000 });
			await ui.zonderBeheerbalk();
		});
		await ui.pauze(1600);
		log.push({ type: 'voorbeeldpagina', url });
		await ui.schermafbeelding('resultaat-01-pagina', {});

		const standen = page.locator('.schaken-standen').first();
		const soorten = standen.locator('.schaken-standen__categories');
		if (await soorten.count()) {
			await ui.stap('Bovenaan staan de soorten competitie.', { wacht: 1100 });
			await ui.schermafbeelding('resultaat-02-soorten', { locator: soorten, marge: 10 });
		}

		const tabs = standen.locator('.schaken-standen__group.is-active .schaken-standen__tab');
		if (await tabs.count()) {
			await ui.stap('Daaronder staat een knop per groep of blok.', { wacht: 1100 });
			await ui.schermafbeelding('resultaat-03-groepen', {
				locator: standen.locator('.schaken-standen__group.is-active .schaken-standen__tabs').first(),
				marge: 10,
			});
			if ((await tabs.count()) > 1) {
				await ui.klik(tabs.nth(1), { wacht: 1600 });
			}
		}

		const weergaven = standen.locator('.schaken-standen__group.is-active .schaken-standen__view');
		if (await weergaven.count()) {
			await ui.stap('Bevat de export een kruistabel of scoretabel, dan verschijnen die knoppen vanzelf.', { wacht: 1300 });
			await ui.schermafbeelding('resultaat-04-weergaveknoppen', {
				locator: standen.locator('.schaken-standen__group.is-active .schaken-standen__views').first(),
				marge: 10,
			});
			const kruis = weergaven.filter({ hasText: 'Kruistabel' }).first();
			if (await kruis.count()) {
				await ui.klik(kruis, { wacht: 1800 });
				await ui.schermafbeelding('resultaat-05-kruistabel', {});
				await ui.klik(weergaven.first(), { wacht: 1600 });
			}
		}

		const spelerLink = standen.locator('.schaken-standen__content a').first();
		if (await spelerLink.count()) {
			await ui.stap('Een naam of detailverwijzing wisselt alleen dit vak; de rest van de pagina blijft staan.', { wacht: 1200 });
			await ui.klik(spelerLink, { wacht: 2000 });
			await ui.schermafbeelding('resultaat-06-detail', {});
			const terug = standen.locator('.schaken-standen__back').first();
			if (await terug.count()) {
				await ui.schermafbeelding('resultaat-07-terugknop', { locator: terug, marge: 14 });
				await ui.klik(terug, { wacht: 1600 });
			}
		}

		await ui.stap('Klaar: een pagina die met elke nieuwe export vanzelf meegaat.', { wacht: 2200 });
	},
};
