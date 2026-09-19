import { existsSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));

export const toolDir = resolve(here, '..');
export const projectDir = resolve(toolDir, '..', '..');
export const outputDir = resolve(projectDir, 'docs/handleiding/media');
export const shotDir = resolve(outputDir, 'schermafbeeldingen');
export const videoDir = resolve(outputDir, 'video');

// Een .env naast dit gereedschap houdt het wachtwoord uit de repository.
const envFile = resolve(toolDir, '.env');
if (existsSync(envFile)) {
	process.loadEnvFile(envFile);
}

export const config = {
	baseUrl: (process.env.WP_BASE_URL || 'http://localhost:8080').replace(/\/$/, ''),
	user: process.env.WP_ADMIN_USER || 'admin',
	password: process.env.WP_ADMIN_PASSWORD || '',
	locale: process.env.WP_LOCALE || 'nl_NL',
	// De opname is trager dan een test hoeft te zijn: de video moet te volgen
	// blijven voor iemand die de stappen voor het eerst ziet.
	pace: Number(process.env.CAPTURE_PACE || 1),
	viewport: { width: 1440, height: 900 },
	// Schermafbeeldingen op retina-resolutie; de video volgt de viewport.
	scale: Number(process.env.CAPTURE_SCALE || 2),
};

export function adminUrl(path) {
	return `${config.baseUrl}/wp-admin/${path.replace(/^\//, '')}`;
}
