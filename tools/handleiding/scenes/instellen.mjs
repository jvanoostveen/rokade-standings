import { adminUrl } from '../lib/config.mjs';
import { INSTELLINGEN, voorInstellen } from '../lib/seed.mjs';

const veld = {
	bronnen: 'textarea[name="rokade_standings_settings[sources]"]',
	cache: 'input[name="rokade_standings_settings[cache_minutes]"]',
	groepen: 'textarea[name="rokade_standings_settings[internal_group_order]"]',
	blokknoppen: 'textarea[name="rokade_standings_settings[block_button_templates]"]',
	hoofdmap: 'input[name="rokade_standings_settings[source_root]"]',
};

function rij(page, selector) {
	return page.locator('tr').filter({ has: page.locator(selector) });
}

export default {
	naam: 'instellen',
	titel: 'De plugin installeren en instellen',
	seed: voorInstellen,

	async run({ page, ui }) {
		await ui.stap('Deel 1 — de plugin installeren en instellen.', { wacht: 1600 });

		await ui.ga(adminUrl('plugin-install.php?tab=upload'));
		await ui.stap('Plugins → Nieuwe plugin toevoegen → Plugin uploaden: kies hier het ZIP-bestand.', { wacht: 1800 });
		await ui.schermafbeelding('instellen-01-plugin-uploaden', { locator: page.locator('.upload-plugin') });

		await ui.ga(adminUrl('plugins.php'));
		await ui.stap('Na het uploaden staat Rokade Standen in de pluginlijst; activeer hem daar.', { wacht: 1600 });
		const pluginRij = page.locator('tr[data-slug="rokade-standings"], tr').filter({ hasText: 'Rokade Standen' }).first();
		await ui.schermafbeelding('instellen-02-plugin-actief', { locator: pluginRij, marge: 6 });

		await ui.stap('De instellingen staan onder Instellingen → Rokade Standen.', { wacht: 900 });
		const menu = page.locator('#menu-settings');
		await menu.hover();
		await ui.pauze(900);
		await ui.schermafbeelding('instellen-03-menu', { locator: [menu, menu.locator('.wp-submenu')], marge: 8 });

		await ui.wijs(page.locator('#menu-settings a[href="options-general.php?page=rokade-standings"]').first(), { klik: true });
		await ui.ga(adminUrl('options-general.php?page=rokade-standings'));
		await ui.stap('Een verse installatie kent nog geen bronpad.', { wacht: 1400 });
		await ui.schermafbeelding('instellen-04-leeg', {});

		await ui.stap('Vul per regel een pad vanaf de WordPress-map met een eigen label: “pad | label”.', { wacht: 1200 });
		await ui.typ(page.locator(veld.bronnen), INSTELLINGEN.sources);
		await ui.stap('Het label wordt ook de naam in de shortcode: Jeugd wordt bron="jeugd".', { wacht: 1600 });
		await ui.schermafbeelding('instellen-05-bronpaden', { locator: rij(page, veld.bronnen) });

		await ui.stap('De cacheduur bepaalt hoe lang de index blijft staan en hoe vaak WP-Cron hem opwarmt.', { wacht: 1200 });
		await ui.typ(page.locator(veld.cache), String(INSTELLINGEN.cache_minutes));
		await ui.schermafbeelding('instellen-06-cacheduur', { locator: rij(page, veld.cache) });

		await ui.stap('De groepen van de interne competitie bepalen de knopnamen en hun volgorde.', { wacht: 1200 });
		await ui.typ(page.locator(veld.groepen), INSTELLINGEN.internal_group_order);
		await ui.schermafbeelding('instellen-07-groepen', { locator: rij(page, veld.groepen) });

		await ui.stap('Doorgeefschaak en snelschaken krijgen hun knopnaam uit een sjabloon met {nummer}.', { wacht: 1200 });
		await ui.typ(page.locator(veld.blokknoppen), INSTELLINGEN.block_button_templates);
		await ui.schermafbeelding('instellen-08-blokknoppen', { locator: rij(page, veld.blokknoppen) });

		await ui.stap('Onder Geavanceerd staat de hoofdmap. Leeg is de WordPress-map; alleen invullen als de exports buiten de site staan.', { wacht: 1800 });
		const hoofdmap = page.locator(veld.hoofdmap);
		await hoofdmap.scrollIntoViewIfNeeded();
		await ui.wijs(hoofdmap);
		await ui.schermafbeelding('instellen-09-hoofdmap', {
			locator: [page.getByRole('heading', { name: 'Geavanceerd' }), rij(page, veld.hoofdmap)],
		});

		await ui.stap('Opslaan.', { wacht: 700 });
		const opslaan = page.getByRole('button', { name: 'Wijzigingen opslaan' });
		await ui.wijs(opslaan);
		await ui.wijs(opslaan, { klik: true });
		await ui.overgang(() => opslaan.click());
		await ui.stap('Na het opslaan toont de pagina per bron hoeveel seizoenen er zijn gevonden.', { wacht: 1800 });
		await ui.schermafbeelding('instellen-10-opgeslagen', {});

		const indexKop = page.getByRole('heading', { name: 'Index verversen' });
		const indexKnop = page.getByRole('button', { name: 'Nu opnieuw indexeren' });
		await ui.schermafbeelding('instellen-11-bronnen-gevonden', {
			locator: [indexKop, page.locator('.wrap ul').first(), indexKnop],
		});

		await ui.stap('Nieuwe export op de server? Dan haalt “Nu opnieuw indexeren” hem meteen binnen.', { wacht: 1400 });
		await ui.wijs(indexKnop);
		await ui.wijs(indexKnop, { klik: true });
		await ui.overgang(() => indexKnop.click());
		await ui.schermafbeelding('instellen-12-index-vernieuwd', { locator: page.locator('.notice-success').first() });

		await ui.stap('Onderaan staat de shortcode-variant voor pagina’s zonder blok-editor.', { wacht: 1600 });
		const shortcodeKop = page.getByRole('heading', { name: 'Shortcode' });
		await shortcodeKop.scrollIntoViewIfNeeded();
		await ui.pauze(500);
		await ui.schermafbeelding('instellen-13-shortcode', {
			locator: [shortcodeKop, page.locator('.wrap p').last()],
		});

		await ui.stap('De plugin is ingesteld. Deel 2 laat zien hoe je de standen op een pagina zet.', { wacht: 2000 });
	},
};
