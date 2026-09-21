#!/usr/bin/env bash

# Build the update feed that installed copies of the plugin poll for a newer
# version. The release workflow publishes the output as update.json next to the
# zip, so https://github.com/<repo>/releases/latest/download/update.json always
# points at the most recent release.
#
#   scripts/release-manifest.sh [--repo owner/name] [--output pad]
#
# Written without jq so it also runs on a machine that only has bash and git.
set -euo pipefail

plugin_slug="rokade-standings"
script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
project_dir="$(cd "${script_dir}/.." && pwd)"
header() { "${script_dir}/plugin-header.sh" "$1"; }

repo="${REPO:-}"
output=''
while [ "$#" -gt 0 ]; do
	case "$1" in
		--repo) repo="${2:?--repo verwacht owner/name}"; shift 2 ;;
		--output) output="${2:?--output verwacht een pad}"; shift 2 ;;
		*) printf 'Fout: onbekende optie %s\n' "$1" >&2; exit 1 ;;
	esac
done

if [ -z "${repo}" ]; then
	# Both remote forms end in owner/name(.git); strip everything around it.
	remote="$(git -C "${project_dir}" remote get-url origin 2>/dev/null || true)"
	remote="${remote%.git}"
	repo="$(printf '%s' "${remote}" | sed -nE 's#^(https?://[^/]+/|git@[^:]+:|ssh://git@[^/]+/)(.+)$#\2#p')"
fi
if [ -z "${repo}" ]; then
	printf 'Fout: geen repository gevonden; geef --repo owner/name op.\n' >&2
	exit 1
fi

json_escape() {
	local s="$1"
	s="${s//\\/\\\\}"
	s="${s//\"/\\\"}"
	s="${s//$'\r'/}"
	s="${s//$'\t'/\\t}"
	s="${s//$'\n'/\\n}"
	printf '%s' "${s}"
}

html_escape() {
	local s="$1"
	s="${s//&/&amp;}"
	s="${s//</&lt;}"
	s="${s//>/&gt;}"
	printf '%s' "${s}"
}

version="$(header Version)"
tag="v${version}"
base="https://github.com/${repo}"

# The commits since the previous release tag are the changelog. On the very
# first release there is no previous tag, so fall back to the recent history.
previous_tag="$(git -C "${project_dir}" tag --list 'v*' --sort=-v:refname | grep -v "^${tag}\$" | head -n 1 || true)"
if [ -n "${previous_tag}" ]; then
	subjects="$(git -C "${project_dir}" log --no-merges --pretty=format:'%s' "${previous_tag}..HEAD" || true)"
else
	subjects="$(git -C "${project_dir}" log --no-merges --max-count=20 --pretty=format:'%s' || true)"
fi

changelog="<h4>${version}</h4>"
if [ -n "${subjects}" ]; then
	changelog="${changelog}<ul>"
	while IFS= read -r subject; do
		[ -n "${subject}" ] || continue
		changelog="${changelog}<li>$(html_escape "${subject}")</li>"
	done <<< "${subjects}"
	changelog="${changelog}</ul>"
else
	changelog="${changelog}<p>Geen wijzigingen vastgelegd.</p>"
fi

manifest="$(cat <<EOF
{
	"name": "$(json_escape "$(header 'Plugin Name')")",
	"slug": "${plugin_slug}",
	"plugin": "${plugin_slug}/${plugin_slug}.php",
	"version": "$(json_escape "${version}")",
	"author": "<a href=\"${base}\">$(json_escape "$(header Author)")</a>",
	"homepage": "${base}",
	"url": "${base}/releases/tag/${tag}",
	"download_url": "${base}/releases/download/${tag}/${plugin_slug}-${version}.zip",
	"package": "${base}/releases/download/${tag}/${plugin_slug}-${version}.zip",
	"requires": "$(json_escape "$(header 'Requires at least')")",
	"tested": "$(json_escape "$(header 'Tested up to')")",
	"requires_php": "$(json_escape "$(header 'Requires PHP')")",
	"last_updated": "$(date -u '+%Y-%m-%d %H:%M:%S')",
	"sections": {
		"description": "$(json_escape "$(header Description)")",
		"changelog": "$(json_escape "${changelog}")"
	}
}
EOF
)"

if [ -n "${output}" ]; then
	mkdir -p "$(dirname "${output}")"
	printf '%s\n' "${manifest}" > "${output}"
	printf 'Update-feed gemaakt: %s\n' "${output}" >&2
else
	printf '%s\n' "${manifest}"
fi
