import { mkdirSync, readFileSync, statSync } from 'node:fs';
import { resolve } from 'node:path';
import { config, shotDir } from './config.mjs';

const OVERLAY_SCRIPT = `
// Dit script draait in elk frame, ook in de bewerkcanvas van de editor. Daar
// hoort geen bijschrift, geen cursor en al helemaal geen doek: dat laatste zou
// de canvas wit laten omdat alleen het hoofdvenster hem weer opendoet.
if (window.top === window.self) {
window.__handleiding = window.__handleiding || {};
window.__handleiding.mount = function () {
	if (document.getElementById('handleiding-overlay')) return;
	var style = document.createElement('style');
	style.id = 'handleiding-overlay-style';
	style.textContent = [
		'#handleiding-overlay{position:fixed;inset:auto 0 0 0;z-index:2147483646;display:flex;justify-content:center;pointer-events:none;opacity:1;transition:opacity .28s ease;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;}',
		// Een doek over de pagina vangt de witte flits van een paginawissel op.
		'#handleiding-doek{position:fixed;inset:0;z-index:2147483645;background:#fff;opacity:0;pointer-events:none;transition:opacity .32s ease;}',
		'#handleiding-doek.is-direct{transition:none;}',
		'#handleiding-overlay .handleiding-bijschrift{margin:0 0 22px;max-width:76%;padding:12px 22px;border-radius:10px;background:rgba(17,23,33,.92);color:#fff;font-size:17px;line-height:1.35;box-shadow:0 8px 28px rgba(0,0,0,.35);opacity:0;transform:translateY(8px);transition:opacity .25s ease,transform .25s ease;}',
		'#handleiding-overlay.is-zichtbaar .handleiding-bijschrift{opacity:1;transform:none;}',
		'#handleiding-cursor{position:fixed;top:0;left:0;z-index:2147483647;width:22px;height:22px;margin:-11px 0 0 -11px;border-radius:50%;border:2px solid rgba(17,23,33,.85);background:rgba(240,180,41,.55);pointer-events:none;opacity:0;transition:transform .45s cubic-bezier(.22,.61,.36,1),opacity .28s ease;}',
		'#handleiding-cursor.is-zichtbaar{opacity:1;}',
		'#handleiding-cursor.is-klik{transform-origin:center;animation:handleiding-klik .4s ease;}',
		'@keyframes handleiding-klik{0%{box-shadow:0 0 0 0 rgba(240,180,41,.7);}100%{box-shadow:0 0 0 22px rgba(240,180,41,0);}}',
		'.handleiding-markering{outline:3px solid #f0b429 !important;outline-offset:3px !important;border-radius:4px;}',
		// Meldingen over de omgeving zelf (updates van WordPress) horen niet op
		// een schermafbeelding van de plugin; de meldingen van de plugin wel.
		'.update-nag,#wp-admin-bar-updates,#wpfooter,.notice-warning.update-nag{display:none !important;}'
	].join('');
	document.documentElement.appendChild(style);

	var overlay = document.createElement('div');
	overlay.id = 'handleiding-overlay';
	overlay.innerHTML = '<p class="handleiding-bijschrift"></p>';
	document.documentElement.appendChild(overlay);

	var cursor = document.createElement('div');
	cursor.id = 'handleiding-cursor';
	document.documentElement.appendChild(cursor);

	var doek = document.createElement('div');
	doek.id = 'handleiding-doek';
	document.documentElement.appendChild(doek);
	// De vorige pagina heeft het doek dichtgetrokken; dan hoort deze pagina er al
	// achter te beginnen in plaats van er doorheen te flitsen.
	try {
		if (sessionStorage.getItem('handleiding-doek') === '1') {
			doek.classList.add('is-direct');
			doek.style.opacity = '1';
			requestAnimationFrame(function () { doek.classList.remove('is-direct'); });
		}
	} catch (fout) { /* sessionStorage kan geblokkeerd zijn; dan maar zonder doek. */ }
};
window.__handleiding.doek = function (dicht) {
	window.__handleiding.mount();
	try {
		if (dicht) {
			sessionStorage.setItem('handleiding-doek', '1');
		} else {
			sessionStorage.removeItem('handleiding-doek');
		}
	} catch (fout) { /* zie boven */ }
	document.getElementById('handleiding-doek').style.opacity = dicht ? '1' : '0';
};
window.__handleiding.bijschrift = function (tekst) {
	window.__handleiding.mount();
	var overlay = document.getElementById('handleiding-overlay');
	overlay.querySelector('.handleiding-bijschrift').textContent = tekst || '';
	overlay.classList.toggle('is-zichtbaar', Boolean(tekst));
};
window.__handleiding.cursor = function (x, y, klik) {
	window.__handleiding.mount();
	var cursor = document.getElementById('handleiding-cursor');
	cursor.classList.add('is-zichtbaar');
	cursor.style.transform = 'translate(' + x + 'px,' + y + 'px)';
	if (klik) {
		cursor.classList.remove('is-klik');
		void cursor.offsetWidth;
		cursor.classList.add('is-klik');
	}
};
window.__handleiding.adminbalk = function (verbergen) {
	var id = 'handleiding-zonder-adminbalk';
	var bestaand = document.getElementById(id);
	if (!verbergen) {
		if (bestaand) bestaand.remove();
		return;
	}
	if (bestaand) return;
	var style = document.createElement('style');
	style.id = id;
	style.textContent = '#wpadminbar{display:none !important;}html{margin-top:0 !important;}';
	document.documentElement.appendChild(style);
};
window.__handleiding.verberg = function (verbergen) {
	var overlay = document.getElementById('handleiding-overlay');
	var cursor = document.getElementById('handleiding-cursor');
	[overlay, cursor].forEach(function (node) {
		if (node) node.style.opacity = verbergen ? '0' : '';
	});
};
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', window.__handleiding.mount);
} else {
	window.__handleiding.mount();
}
}
`;

/**
 * Alles wat een opname anders maakt dan een test: een bijschrift onderin beeld,
 * een zichtbare cursor en rustige pauzes, plus schermafbeeldingen die die
 * hulpmiddelen juist niet tonen.
 */
export class Ui {
	constructor(page, { scene, log }) {
		this.page = page;
		this.scene = scene;
		this.log = log;
		this.laatsteBijschrift = '';
		this.start = Date.now();
	}

	/** Seconden sinds het begin van de scène; zo is een trage stap terug te vinden. */
	get seconden() {
		return Math.round((Date.now() - this.start) / 100) / 10;
	}

	static async install(context) {
		await context.addInitScript(OVERLAY_SCRIPT);
	}

	async herstel() {
		// Na een paginawissel is de overlay weg; het bijschrift hoort te blijven
		// staan zolang de stap loopt.
		await this.page.evaluate((tekst) => {
			window.__handleiding && window.__handleiding.bijschrift(tekst);
		}, this.laatsteBijschrift).catch(() => {});
	}

	async stap(tekst, { wacht = 900 } = {}) {
		this.laatsteBijschrift = tekst;
		this.log.push({ type: 'stap', tekst, seconde: this.seconden });
		await this.page.evaluate((waarde) => {
			window.__handleiding.bijschrift(waarde);
		}, tekst);
		await this.pauze(wacht);
	}

	/**
	 * Een paginawissel achter een doek: opkomen, navigeren, en pas weer opendoen
	 * als de nieuwe pagina staat. Zonder dat zie je de witte flits van de browser
	 * en springt het bijschrift weg en terug.
	 */
	async overgang(actie, { na = 450, navigeert = true } = {}) {
		await this.page.evaluate(() => window.__handleiding.doek(true)).catch(() => {});
		await this.pauze(360);
		if (navigeert) {
			// De klik en de navigatie samen afwachten; anders kijkt het wachten nog
			// naar de oude pagina en gaat het doek te vroeg open.
			await Promise.all([
				this.page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 20000 }).catch(() => {}),
				actie(),
			]);
		} else {
			await actie();
		}
		await this.page.waitForLoadState('domcontentloaded').catch(() => {});
		await this.herstel();
		await this.pauze(200);
		await this.page.evaluate(() => window.__handleiding.doek(false)).catch(() => {});
		await this.pauze(na);
	}

	async ga(url, opties) {
		await this.overgang(() => this.page.goto(url), opties);
	}

	/**
	 * De beheerbalk hoort niet bij wat een bezoeker ziet. Hem per schermafbeelding
	 * weghalen zou in de video een sprong geven, dus gaat hij in één keer weg —
	 * het beste achter het doek van een overgang.
	 */
	async zonderBeheerbalk() {
		await this.page.evaluate(() => window.__handleiding.adminbalk(true)).catch(() => {});
	}

	async pauze(ms = 600) {
		await this.page.waitForTimeout(Math.round(ms * config.pace));
	}

	async wijs(locator, { klik = false } = {}) {
		const box = await locator.boundingBox();
		if (!box) return null;
		const punt = { x: box.x + box.width / 2, y: box.y + box.height / 2 };
		await this.page.evaluate(({ x, y, klik: isKlik }) => {
			window.__handleiding.cursor(x, y, isKlik);
		}, { ...punt, klik });
		await this.pauze(450);
		return punt;
	}

	async klik(locator, { wacht = 500 } = {}) {
		await locator.scrollIntoViewIfNeeded().catch(() => {});
		await this.wijs(locator);
		await this.wijs(locator, { klik: true });
		await locator.click();
		await this.pauze(wacht);
	}

	async typ(locator, tekst, { leeg = true, delay = 28 } = {}) {
		await this.klik(locator, { wacht: 120 });
		if (leeg) {
			await locator.fill('');
		}
		await locator.pressSequentially(tekst, { delay });
		await this.pauze(400);
	}

	async kies(locator, waarde, { wacht = 1200 } = {}) {
		await this.wijs(locator);
		await locator.selectOption(waarde);
		await this.pauze(wacht);
	}

	async markeer(locator) {
		await locator.evaluate((node) => node.classList.add('handleiding-markering')).catch(() => {});
	}

	async wisMarkering() {
		await this.page.evaluate(() => {
			document.querySelectorAll('.handleiding-markering').forEach((node) => node.classList.remove('handleiding-markering'));
		}).catch(() => {});
	}

	/**
	 * Een effen vlak comprimeert tot bijna niets. Dat is geen bewijs, maar wel het
	 * goedkoopste alarm voor een beeld waar een overlay overheen stond. Een
	 * volledig witte PNG komt rond 4 byte per 1000 pixels uit en het magerste
	 * echte beeld hier rond 16; acht ligt daar ruim tussenin.
	 */
	lijktLeeg(bestand) {
		const bytes = statSync(bestand).size;
		const kop = readFileSync(bestand).subarray(16, 24);
		const pixels = kop.readUInt32BE(0) * kop.readUInt32BE(4);
		if (!pixels) return '';
		const perPixel = bytes / pixels;
		return perPixel < 0.008 ? `${(perPixel * 1000).toFixed(2)} byte per 1000 pixels` : '';
	}

	/** Het omhullende kader van een of meer elementen. */
	async omvat(locator) {
		const lijst = Array.isArray(locator) ? locator : [locator];
		const kaders = [];
		for (const item of lijst) {
			const kader = await item.boundingBox().catch(() => null);
			if (kader) kaders.push(kader);
		}
		if (!kaders.length) return null;
		const x = Math.min(...kaders.map((k) => k.x));
		const y = Math.min(...kaders.map((k) => k.y));
		const rechts = Math.max(...kaders.map((k) => k.x + k.width));
		const onder = Math.max(...kaders.map((k) => k.y + k.height));
		return { x, y, width: rechts - x, height: onder - y };
	}

	/**
	 * Een schermafbeelding is een naslagbeeld: de bijschriften en de cursor van
	 * de video horen er niet op te staan.
	 */
	async schermafbeelding(naam, { locator = null, marge = 12, volledigePagina = false, maxHoogte = 0 } = {}) {
		mkdirSync(shotDir, { recursive: true });
		const bestand = resolve(shotDir, `${naam}.png`);
		await this.page.evaluate(() => window.__handleiding.verberg(true)).catch(() => {});
		await this.pauze(250);

		if (locator) {
			const eerste = Array.isArray(locator) ? locator[0] : locator;
			await eerste.scrollIntoViewIfNeeded().catch(() => {});
			await this.pauze(200);
			const box = await this.omvat(locator);
			if (box) {
				const zicht = await this.page.evaluate(() => ({
					x: window.scrollX,
					y: window.scrollY,
					breedte: document.documentElement.clientWidth,
					hoogte: window.innerHeight,
				}));
				const kader = {
					x: Math.max(0, box.x - marge),
					y: box.y - marge,
					width: Math.min(zicht.breedte - Math.max(0, box.x - marge), box.width + marge * 2),
					height: box.height + marge * 2,
				};
				// Een paneel dat tot onderaan het scherm doorloopt levert een beeld met
				// veel leegte op; dan is alleen de bovenkant het naslag waard.
				if (maxHoogte) {
					kader.height = Math.min(kader.height, maxHoogte);
				}
				// Past de uitsnede niet in het zichtbare deel, dan is een opname van
				// de hele pagina de enige manier om het kader compleet te krijgen.
				const buitenBeeld = kader.y < 0 || kader.y + kader.height > zicht.hoogte;
				if (buitenBeeld) {
					await this.page.screenshot({
						path: bestand,
						fullPage: true,
						clip: { ...kader, x: kader.x + zicht.x, y: Math.max(0, kader.y + zicht.y) },
					});
				} else {
					await this.page.screenshot({ path: bestand, clip: kader });
				}
			} else {
				await eerste.screenshot({ path: bestand });
			}
		} else {
			await this.page.screenshot({ path: bestand, fullPage: volledigePagina });
		}

		await this.page.evaluate(() => window.__handleiding.verberg(false)).catch(() => {});
		await this.page.waitForTimeout(320);
		const verdacht = this.lijktLeeg(bestand);
		if (verdacht) {
			console.warn(`  ! ${naam} lijkt leeg (${verdacht}); staat er iets overheen?`);
		}
		this.log.push({
			type: 'schermafbeelding',
			naam,
			bestand: `media/schermafbeeldingen/${naam}.png`,
			seconde: this.seconden,
			...(verdacht ? { waarschuwing: verdacht } : {}),
		});
		return bestand;
	}
}
