#!/usr/bin/env bash

# Print one header field from the plugin's main file, for example:
#   scripts/plugin-header.sh Version
# The packaging script, the release manifest and the workflow all need to agree
# on the version, so they read it here instead of each carrying their own regex.
set -euo pipefail

field="${1:-}"
if [ -z "${field}" ]; then
	printf 'Gebruik: plugin-header.sh <Header-veld> [pluginbestand]\n' >&2
	exit 1
fi

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
plugin_file="${2:-${script_dir}/../rokade-standings.php}"

if [ ! -f "${plugin_file}" ]; then
	printf 'Fout: pluginbestand niet gevonden: %s\n' "${plugin_file}" >&2
	exit 1
fi

# No pipe to head: with `pipefail` a closing reader turns a fine read into a
# failing one. Take the first line from the captured output instead.
matches="$(sed -nE "s/^[[:space:]]*\*[[:space:]]*${field}:[[:space:]]*(.*)\$/\1/p" "${plugin_file}")"
value="${matches%%$'\n'*}"
value="${value%$'\r'}"
# shellcheck disable=SC2001 # Trailing whitespace only; a parameter expansion cannot trim a class.
value="$(printf '%s' "${value}" | sed -e 's/[[:space:]]*$//')"

if [ -z "${value}" ]; then
	printf 'Fout: header "%s" niet gevonden in %s.\n' "${field}" "${plugin_file}" >&2
	exit 1
fi

printf '%s\n' "${value}"
