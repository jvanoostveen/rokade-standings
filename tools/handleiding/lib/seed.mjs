import { config } from './config.mjs';
import { wp, wpOk } from './wp.mjs';

export const VOORBEELD = {
	titel: 'Standen jeugd',
	slugs: ['standen-jeugd', 'standen-jeugd-2', 'standen-jeugd-3'],
};

export const INSTELLINGEN = {
	sources: '/standen | Jeugd\n/senioren | Senioren',
	cache_minutes: 15,
	internal_group_order: 'Starters\nPupillen\nJunioren\nVerkenners\nMeester-/Kroon\nMeester\nKroon',
	block_button_templates: 'doorgeefschaak | Blok {nummer}\nsnelschaken | Blok {nummer}',
};

function zetInstellingen(waarden) {
	wp(['option', 'update', 'schaken_standen_settings', JSON.stringify(waarden), '--format=json']);
	wpOk(['transient', 'delete', 'schaken_standen_index_v3']);
}

/** Gemeenschappelijke staat: de plugin actief en de admin in het Nederlands. */
export function basis() {
	wpOk(['plugin', 'activate', 'rokade-standings']);
	const taal = wpOk(['option', 'get', 'WPLANG']);
	if (taal !== config.locale) {
		wpOk(['language', 'core', 'install', config.locale, '--activate']);
	}
}

/** Het instelscenario begint bij een plugin die nog geen bronpad kent. */
export function voorInstellen() {
	basis();
	zetInstellingen({ ...INSTELLINGEN, sources: '' });
}

/** Het gebruiksscenario begint bij een ingestelde plugin en geen voorbeeldpagina. */
export function voorGebruiken() {
	basis();
	zetInstellingen(INSTELLINGEN);
	verwijderVoorbeeldpagina();
}

export function verwijderVoorbeeldpagina() {
	for (const slug of VOORBEELD.slugs) {
		const ids = wpOk(['post', 'list', '--post_type=page', `--name=${slug}`, '--field=ID', '--post_status=any']);
		for (const id of ids.split(/\s+/).filter(Boolean)) {
			wpOk(['post', 'delete', id, '--force']);
		}
	}
	// Automatisch opgeslagen concepten van eerdere opnames laten de editor met
	// een herstelmelding openen; die hoort niet op een schermafbeelding.
	const concepten = wpOk(['post', 'list', '--post_type=page', '--post_status=auto-draft', '--field=ID']);
	for (const id of concepten.split(/\s+/).filter(Boolean)) {
		wpOk(['post', 'delete', id, '--force']);
	}
}
