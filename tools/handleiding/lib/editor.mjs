import { adminUrl } from './config.mjs';

/** De bewerkcanvas van Gutenberg staat in een iframe. */
export function canvas(page) {
	return page.frameLocator('iframe[name="editor-canvas"]');
}

export function zijbalk(page) {
	return page.locator('.interface-interface-skeleton__sidebar');
}

export async function openNieuwePagina(page, ui) {
	// De editor laadt zijn canvas pas na het document; het doek gaat daarom pas
	// open als dat er staat, anders zie je de editor zichzelf opbouwen.
	await ui.overgang(async () => {
		await page.goto(adminUrl('post-new.php?post_type=page'));
		await page.waitForSelector('iframe[name="editor-canvas"]', { timeout: 60000 });
		await sluitWelkom(page);
		await page.waitForTimeout(900);
	});
	await ui.pauze(600);
}

/**
 * Een verse installatie opent de welkomstrondleiding over de editor heen. Die
 * hoort niet in de handleiding, en het scenario moet hem zelf wegnemen zodat
 * een herhaling op een andere installatie hetzelfde beeld geeft.
 */
export async function sluitWelkom(page) {
	await page.evaluate(() => {
		const voorkeuren = window.wp?.data?.dispatch('core/preferences');
		if (!voorkeuren) return;
		voorkeuren.set('core/edit-post', 'welcomeGuide', false);
		voorkeuren.set('core', 'welcomeGuide', false);
		voorkeuren.set('core/edit-post', 'welcomeGuideTemplate', false);
	}).catch(() => {});

	const modal = page.locator('.components-modal__frame');
	if (await modal.count()) {
		await modal.getByRole('button', { name: /sluiten|close/i }).first().click().catch(() => {});
	}
}

export async function zetTitel(page, ui, titel) {
	const veld = canvas(page).locator('.editor-post-title__input, [aria-label="Titel toevoegen"]').first();
	await ui.klik(veld, { wacht: 200 });
	await veld.pressSequentially(titel, { delay: 45 });
	await ui.pauze(700);
}

export async function voegBlokToe(page, ui, zoekterm, blokTitel) {
	await ui.klik(page.getByRole('button', { name: 'Blok-inserter' }).first(), { wacht: 900 });
	const zoekveld = page.getByRole('searchbox', { name: /zoeken/i }).first();
	await zoekveld.waitFor({ timeout: 15000 });
	await ui.klik(zoekveld, { wacht: 150 });
	await zoekveld.pressSequentially(zoekterm, { delay: 90 });
	await ui.pauze(1100);
	return page.getByRole('option', { name: blokTitel }).first();
}

export async function sluitInserter(page) {
	const knop = page.getByRole('button', { name: 'Blok-inserter' }).first();
	const open = await knop.getAttribute('aria-expanded');
	if (open === 'true') {
		await knop.click();
	}
}

export async function openBlokZijbalk(page, ui) {
	const instellingen = page.getByRole('button', { name: 'Instellingen', exact: true }).first();
	if (await instellingen.count()) {
		const open = await instellingen.getAttribute('aria-expanded');
		if (open === 'false') {
			await ui.klik(instellingen, { wacht: 700 });
		}
	}
	const tab = zijbalk(page).getByRole('tab', { name: 'Blok' });
	if (await tab.count()) {
		await ui.klik(tab, { wacht: 600 });
	}
	await zijbalk(page).getByRole('combobox', { name: 'Seizoen' }).first().waitFor({ timeout: 20000 });
}

export function keuze(page, label) {
	return zijbalk(page).getByRole('combobox', { name: label }).first();
}

/** De opties zoals de editor ze op dit moment aanbiedt. */
export async function keuzeOpties(page, label) {
	const select = keuze(page, label);
	if (!(await select.count())) return [];
	return select.evaluate((node) => Array.from(node.options).map((option) => ({ waarde: option.value, label: option.label })));
}

/**
 * De voorvertoning van het blok staat in de bewerkcanvas, dus de wachtvoorwaarde
 * hoort ook in dat frame gesteld te worden; in het hoofddocument bestaat het
 * element niet en loopt elke wachttijd vol.
 */
export async function wachtOpVoorbeeld(page) {
	const frame = page.frame({ name: 'editor-canvas' }) || page.mainFrame();
	await frame.waitForFunction(() => {
		const voorbeeld = document.querySelector('.schaken-standen-block-preview');
		if (!voorbeeld) return false;
		if (voorbeeld.querySelector('.components-spinner')) return false;
		return voorbeeld.textContent.trim().length > 0 || Boolean(voorbeeld.querySelector('iframe'));
	}, null, { timeout: 15000 }).catch(() => {});
	// Na het vervangen van de inhoud heeft de editor nog een tel nodig om de
	// hoogte te laten uitzakken; anders valt een schermafbeelding middenin.
	await page.waitForTimeout(350);
}

export async function publiceer(page, ui) {
	await ui.klik(page.getByRole('button', { name: 'Publiceren', exact: true }).first(), { wacht: 900 });
	const paneel = page.locator('.editor-post-publish-panel');
	await paneel.waitFor({ timeout: 15000 });
	await ui.klik(paneel.getByRole('button', { name: 'Publiceren', exact: true }).first(), { wacht: 2500 });
	await paneel.getByRole('link', { name: /bekijk/i }).first().waitFor({ timeout: 30000 });
}
