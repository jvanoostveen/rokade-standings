#!/usr/bin/env bash

# Build a WordPress-uploadable plugin archive without development files.
set -euo pipefail

plugin_slug="rokade-standings"
script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
project_dir="$(cd "${script_dir}/.." && pwd)"
plugin_file="${project_dir}/rokade-standings.php"

if ! command -v zip >/dev/null 2>&1; then
	printf 'Fout: het commando "zip" is vereist om een pluginpakket te maken.\n' >&2
	exit 1
fi

if [ ! -f "${plugin_file}" ]; then
	printf 'Fout: pluginbestand niet gevonden: %s\n' "${plugin_file}" >&2
	exit 1
fi

version="$(sed -nE 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*([^[:space:]]+).*/\1/p' "${plugin_file}" | head -n 1)"
if [ -z "${version}" ]; then
	printf 'Fout: geen pluginversie gevonden in %s.\n' "${plugin_file}" >&2
	exit 1
fi

dist_dir="${project_dir}/dist"
archive="${dist_dir}/${plugin_slug}-${version}.zip"
staging_dir="$(mktemp -d "${TMPDIR:-/tmp}/${plugin_slug}.XXXXXX")"
trap 'rm -rf "${staging_dir}"' EXIT

mkdir -p "${staging_dir}/${plugin_slug}" "${dist_dir}"
cp "${plugin_file}" "${staging_dir}/${plugin_slug}/"
cp -R "${project_dir}/assets" "${project_dir}/includes" "${staging_dir}/${plugin_slug}/"

if [ -f "${project_dir}/README.md" ]; then
	cp "${project_dir}/README.md" "${staging_dir}/${plugin_slug}/"
fi

rm -f "${archive}"
(
	cd "${staging_dir}"
	zip -qr "${archive}" "${plugin_slug}" -x '*/.DS_Store'
)

printf 'Pluginpakket gemaakt: %s\n' "${archive}"
