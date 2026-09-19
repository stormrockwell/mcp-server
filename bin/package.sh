#!/usr/bin/env bash
# Builds dist/mcp-server.zip, the plugin as non developers install it.
# Stages the plugin in dist/ and never touches the working tree's vendor,
# so a failure partway through never leaves the developer's checkout without
# dev tools.
set -euo pipefail

SLUG="mcp-server"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST="$ROOT/dist"
STAGE="$DIST/$SLUG"

rm -rf "$DIST"
mkdir -p "$STAGE"

# Vendor is excluded here too, since dev packages must never reach the zip.
rsync -a --exclude-from="$ROOT/.distignore" --exclude=/vendor "$ROOT/" "$STAGE/"

# Composer needs the lock file present to install the locked versions.
# It is staged only, then removed, since .distignore keeps it out of the zip.
cp "$ROOT/composer.lock" "$STAGE/composer.lock"
composer install --no-dev --optimize-autoloader --working-dir="$STAGE"
rm "$STAGE/composer.lock"

( cd "$DIST" && zip -rq "$SLUG.zip" "$SLUG" )
echo "Built $DIST/$SLUG.zip"
