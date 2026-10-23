#!/usr/bin/env bash
#
# Regenerate the translation template.
#
# Requires WP-CLI with the i18n command. Falls back to wp-env when WP-CLI is
# not available on the host.

set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
plugin_dir="$(basename "$root")"
headers='{"Report-Msgid-Bugs-To":"https://verifycate.com/support"}'

if command -v wp >/dev/null 2>&1; then
	wp i18n make-pot "$root" "$root/languages/verifycate.pot" \
		--domain=verifycate \
		--package-name=Verifycate \
		--exclude=vendor,build,node_modules,tests,.git,.github \
		--headers="$headers"
	exit 0
fi

if command -v npx >/dev/null 2>&1; then
	cd "$root"
	npx wp-env run cli -- \
		wp i18n make-pot "wp-content/plugins/$plugin_dir" "wp-content/plugins/$plugin_dir/languages/verifycate.pot" \
		--domain=verifycate \
		--package-name=Verifycate \
		--exclude=vendor,build,node_modules,tests,.git,.github \
		--headers="$headers"
	exit 0
fi

echo "WP-CLI or npx is required to generate the POT file." >&2
exit 1
