import { spawnSync } from 'node:child_process';
import { projectDir } from './config.mjs';

/**
 * WP-CLI draait in de `wpcli`-service uit docker-compose.yml, zodat het
 * scenario de lokale installatie in een bekende staat kan zetten zonder dat er
 * iets buiten Docker geïnstalleerd hoeft te zijn.
 */
export function wp(args, { silent = false } = {}) {
	const result = spawnSync('docker', ['compose', 'run', '--rm', '-T', 'wpcli', 'wp', ...args], {
		cwd: projectDir,
		encoding: 'utf8',
	});

	if (result.status !== 0 && !silent) {
		const output = `${result.stdout || ''}${result.stderr || ''}`.trim();
		throw new Error(`wp ${args.join(' ')} mislukte:\n${output}`);
	}

	return (result.stdout || '').trim();
}

export function wpOk(args) {
	return wp(args, { silent: true });
}
