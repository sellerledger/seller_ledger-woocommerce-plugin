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

# 1. Export tracked source into the staged plugin. git archive naturally omits
# gitignored paths (vendor/, build/, dist/, node_modules/, local env files); the
# rm below drops tracked dev tooling, tests, and SVN assets that must not ship.
git -C "${ROOT}" archive --format=tar HEAD | tar -x -C "${STAGE}"
( cd "${STAGE}" && rm -rf \
	.github tests assets \
	composer.json composer.lock phpcs.xml.dist phpunit.xml.dist \
	scoper.inc.php build.sh .gitignore CLAUDE.md \
	.wp-env.json .wp-env.override.json .wp-env.override.json.example .claude )

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

# 5a. Ship composer.json for dependency transparency (WordPress.org asks for it),
# stripping the dev-only local path repository so the manifest resolves cleanly
# via the published VCS tag.
php -r '$f=$argv[1]; $c=json_decode(file_get_contents($f), true); if (isset($c["repositories"])) { $c["repositories"] = array_values(array_filter($c["repositories"], fn($r) => ($r["type"] ?? "") !== "path")); } file_put_contents($f, json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");' "${STAGE}/composer.json"

# 5b. Strip stray dev files that bundled packages ship in their source.
find "${STAGE}/vendor" -type d \( -name tests -o -name test -o -name .github -o -name docs \) -prune -exec rm -rf {} + 2>/dev/null || true
find "${STAGE}/vendor" -type f \( -iname 'phpunit*.xml*' -o -name '.gitattributes' -o -name '.editorconfig' -o -name 'Makefile' \) -delete 2>/dev/null || true

# 6. Zip it (zip CLI if present, otherwise Python's zipfile for portability).
if command -v zip >/dev/null 2>&1; then
	( cd "${BUILD}" && zip -r -q "${DIST}/${SLUG}.zip" "${SLUG}" )
else
	python3 -c "import shutil; shutil.make_archive('${DIST}/${SLUG}', 'zip', '${BUILD}', '${SLUG}')"
fi

echo "Built ${DIST}/${SLUG}.zip"
echo "Reminder: upload assets/screenshot-*.png to the WordPress.org SVN assets/ directory, not the zip."
