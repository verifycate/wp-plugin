#!/usr/bin/env bash
#
# Build the distributable plugin zip.
#
# Requires the Verifycate PHP SDK checkout at ../php-sdk (Composer path
# repository) or access to the private repository through Composer auth.

set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
out="${1:-$root/build}"
target="$out/verifycate"

cd "$root"

composer install --no-dev --classmap-authoritative --no-interaction --prefer-dist

rm -rf "$target"
mkdir -p "$target"

rsync -a --exclude-from="$root/.distignore" "$root/" "$target/"

rm -f "$out/verifycate.zip"
(
	cd "$out"
	zip -qr verifycate.zip verifycate
)

echo "Built $out/verifycate.zip"
