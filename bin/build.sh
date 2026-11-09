#!/bin/sh
# Builds the distributable plugin from the committed HEAD into $1 (default: ../corinthian-admin-column-manager-build).
set -eu
src=$(cd "$(dirname "$0")/.." && pwd)
out=${1:-"$src/../corinthian-admin-column-manager-build"}
rm -rf "$out" && mkdir -p "$out/corinthian-admin-column-manager"
git -C "$src" archive HEAD | tar -x -C "$out/corinthian-admin-column-manager"
cd "$out/corinthian-admin-column-manager"
composer install --no-dev --optimize-autoloader --no-interaction --quiet
cp -R "$src/node_modules" ./node_modules 2>/dev/null || npm ci --silent
npm run build --silent >/dev/null
rm -rf node_modules
while IFS= read -r entry; do
	[ -n "$entry" ] && rm -rf "./$entry"
done < "$src/.distignore"
( cd "$out" && rm -f corinthian-admin-column-manager.zip && zip -qr corinthian-admin-column-manager.zip corinthian-admin-column-manager )
echo "$out/corinthian-admin-column-manager"
