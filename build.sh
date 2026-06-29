#!/usr/bin/env bash
#
# Build a WordPress.org-ready plugin zip.
#
# Produces dist/seller-ledger.zip containing the runtime files plus a production
# (no-dev) vendor/ whose third-party libraries (Guzzle, PSR, ...) are namespaced
# under SellerLedger\Vendor via PHP-Scoper, so they can't collide with copies
# shipped by other plugins. Dev tooling, tests, and the SVN `assets/`
# screenshots are excluded from the zip.
#
# Usage: ./build.sh

set -euo pipefail

SLUG="seller-ledger"
SCOPER_VERSION="0.18.18"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BUILD="${ROOT}/build"
DIST="${ROOT}/dist"
STAGE="${BUILD}/${SLUG}"
DEPS="${BUILD}/deps"
SCOPER="${BUILD}/php-scoper.phar"

rm -rf "${BUILD}" "${DIST}"
mkdir -p "${STAGE}" "${DIST}" "${DEPS}"

# 1. Copy tracked source (no vendor/dev/tooling) into the staged plugin.
rsync -a --exclude-from=- "${ROOT}/" "${STAGE}/" <<'EXCLUDES'
.git/
.github/
build/
dist/
tests/
node_modules/
vendor/
assets/
composer.json
composer.lock
phpcs.xml.dist
phpunit.xml.dist
scoper.inc.php
build.sh
.gitignore
CLAUDE.md
EXCLUDES

# 2. Install production dependencies in isolation. The lock is intentionally not
# copied: in this isolated directory the local path repo for seller_ledger-php
# does not resolve, so Composer falls back to the published VCS tag for the build.
cp "${ROOT}/composer.json" "${DEPS}/composer.json"
( cd "${DEPS}" && composer update --no-dev --optimize-autoloader --no-interaction )

# 3. Fetch PHP-Scoper if not already cached.
if [ ! -f "${SCOPER}" ]; then
	curl -sSL -o "${SCOPER}" "https://github.com/humbug/php-scoper/releases/download/${SCOPER_VERSION}/php-scoper.phar"
fi

# 4. Scope the dependencies into the staged plugin's vendor/.
php "${SCOPER}" add-prefix \
	--config="${ROOT}/scoper.inc.php" \
	--working-dir="${DEPS}" \
	--output-dir="${STAGE}/vendor" \
	--force --quiet

# 5. Regenerate an optimized autoloader for the scoped vendor.
cp "${DEPS}/composer.json" "${STAGE}/composer.json"
( cd "${STAGE}" && composer dump-autoload --no-dev --classmap-authoritative --no-interaction )
rm -f "${STAGE}/composer.json"

# 6. Zip it.
( cd "${BUILD}" && zip -r -q "${DIST}/${SLUG}.zip" "${SLUG}" )

echo "Built ${DIST}/${SLUG}.zip"
echo "Reminder: upload assets/screenshot-*.png to the WordPress.org SVN assets/ directory, not the zip."
