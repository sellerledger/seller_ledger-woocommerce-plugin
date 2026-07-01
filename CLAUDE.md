# Seller Ledger for WooCommerce

WooCommerce plugin that connects a store to [Seller Ledger](https://www.sellerledger.com)
for bookkeeping: it syncs orders/refunds to the Seller Ledger API and calculates sales tax at
checkout. Distributed via the **WordPress.org plugin directory**, so all code must pass
WordPress.org review (Plugin Check + WordPress Coding Standards).

## Build, Test & Lint

- `composer install` — install dependencies (run before anything else)
- `composer test` / `vendor/bin/phpunit` — run the unit suite (`tests/`, brain/monkey-stubbed,
  no WP runtime needed)
- `composer phpcs` / `vendor/bin/phpcs` — run WordPress + WooCommerce coding-standards checks
  (config in `phpcs.xml.dist`)
- `composer phpcbf` / `vendor/bin/phpcbf` — auto-fix what phpcs can
- `php -l <file>` — quick syntax lint

There is no live WP/WooCommerce environment in this repo. Unit tests cover pure logic
(request building, cache keys, rate-injection decisions); hook wiring is verified manually in
a real WooCommerce install (e.g. [`wp-env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/)).

## Local development (wp-env)

Local dev uses [`wp-env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/)
(Docker), the environment WooCommerce core uses. `.wp-env.json` mounts this plugin and installs
WooCommerce.

- `composer install` (the mounted plugin needs `vendor/`), then `npm -g i @wordpress/env`
- `wp-env start` → store at http://localhost:8888/wp-admin (admin / password); activate Seller Ledger
- To point the plugin at a **local Seller Ledger Rails app** instead of production, copy
  `.wp-env.override.json.example` to `.wp-env.override.json` (gitignored) — it sets
  `SELLERLEDGER_APP_URL`. The plugin reads this via `SellerLedger_Integration::app_url()` /
  `::api_client()` (also overridable with the `sellerledger_app_url` filter), so the connect link
  and every API call hit your local app.
- `wp-env run cli wp ...` runs WP-CLI; `wp-env stop` / `wp-env destroy` to tear down.

**`seller_ledger-php` during dev — no tag needed.** `composer.json` has a glob *path* repository
(`../seller_ledger-php*`): if the lib is checked out as a sibling, Composer **mirrors (copies)** your
working copy into `vendor/`, so no tag/publish is needed. It's a copy rather than a symlink on
purpose — a symlink points outside the plugin dir and would break inside the wp-env container
(whose bind mount doesn't include the sibling). After editing the lib, re-run
`composer update sellerledger/seller_ledger-php` to re-copy. If the sibling isn't present (CI, the
dist build), the path repo is skipped and Composer falls back to the published git tag via the VCS
repo. `composer.lock` is gitignored (it would otherwise pin a machine-specific path), `build.sh`
runs `composer update` to resolve the tagged release, and `config.platform.php` is pinned to the
plugin's minimum (8.0) so the dependency tree stays installable/loadable on the advertised PHP
floor. **Releasing the lib still means tagging it** — but local development never requires a tag.

**Linux networking note.** `SELLERLEDGER_APP_URL` stays `localhost:3000` (your browser follows
it). `SELLERLEDGER_API_URL` is what the *container* calls, and Linux has two gotchas:

1. **Reachability** — the simplest fix is to start Rails on all interfaces (`bin/rails server -b
   0.0.0.0`); then the container reaches it at `http://172.17.0.1:3000` (the `docker0` gateway).
2. **If you can't change how Rails starts** (it's `127.0.0.1`-only), run a host forwarder on the
   docker0 interface, e.g. `socat TCP-LISTEN:3000,bind=172.17.0.1,fork,reuseaddr
   TCP:127.0.0.1:3000`, and keep it running.

Use the **IP** (`http://172.17.0.1:3000`), not `host.docker.internal` — Rails' development
host-authorization rejects the `host.docker.internal` Host header with "Blocked hosts", but
allows bare IPs.

## Official standards (read these before changing code)

These are the authorities; when in doubt, follow the link, not memory.

- **WordPress Coding Standards** (PHP/HTML/CSS/JS/inline-docs):
  https://developer.wordpress.org/coding-standards/wordpress-coding-standards/
- **WPCS (the phpcs sniffs we run)**: https://github.com/WordPress/WordPress-Coding-Standards
- **WooCommerce coding standards / sniffs**: https://github.com/woocommerce/woocommerce-sniffs
  and developer docs https://developer.woocommerce.com/docs/
- **Plugin Developer Handbook**: https://developer.wordpress.org/plugins/
- **Detailed Plugin Guidelines (the .org review rules)**:
  https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/
- **Plugin Check (PCP)** — run before submission:
  https://wordpress.org/plugins/plugin-check/ · source https://github.com/WordPress/plugin-check
- **Security (escaping, sanitizing, nonces)**: https://developer.wordpress.org/apis/security/
- **`readme.txt` format** + validator: https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/
  · https://wordpress.org/plugins/developers/readme-validator/
- **WooCommerce High-Performance Order Storage (HPOS)**:
  https://developer.woocommerce.com/docs/features/high-performance-order-storage/
- **WooCommerce settings API** (the integration settings tab):
  https://developer.woocommerce.com/docs/settings-api/

## Code Rules (WordPress conventions — differ from the Rails app)

- **Tabs for indentation**, single-quoted strings unless interpolating, Yoda conditions, braces
  per WPCS. Run `phpcbf` then hand-fix the rest.
- **PHPDoc blocks** on classes/methods are expected (this is WordPress, not the Rails repo — do
  not strip comments here).
- **Prefix everything with a plugin-unique prefix — WordPress.org REJECTS common prefixes.**
  Classes `SellerLedger_*`, functions/hooks/options/transients/script+style handles/AJAX actions
  (`add_action( 'wp_ajax_...' )`) all `sellerledger_*`, constants `SELLERLEDGER_*`. **Never** use
  `WC_`, `wc_`, `woocommerce_`, or any prefix under 4 characters (e.g. `sl_`, `sl-`) — the review
  tool flags them as collisions and pends the submission. The only unavoidable exception is the
  WooCommerce-core-generated integration settings option (`woocommerce_{id}_settings`), which
  `WC_Integration` names for us. Class **filenames must match the class**:
  `class-sellerledger-<name>.php` for `SellerLedger_<Name>` (phpcs `InvalidClassFileName` enforces
  this). Text domain is **`seller-ledger`** (must match the plugin slug) — every
  `__()`/`_e()`/`esc_html__()` uses it.
- **Escape on output** (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`), **sanitize on input**
  (`sanitize_text_field` after `wp_unslash`), **verify nonces + capabilities** on every form/AJAX
  handler (`check_admin_referer`, `current_user_can( 'manage_woocommerce' )`).
- **All SQL through `$wpdb->prepare`** with the right placeholders: `%s` value, `%d` int,
  `%i` identifier (table/column, WP 6.2+). Never interpolate user input.
- **No `error_log` in shipped code** — use `wc_get_logger()` (see `Integration::log()`).
- **Don't swallow API errors silently.** Catch `SellerLedger\Exception`, surface the failure to
  the user (settings) or fall back safely (checkout), and log it.
- **Bump versions together**: the plugin header `Version`, `SellerLedger_Plugin::$version`, and
  `readme.txt` `Stable tag` must always agree.

## Dogfood the PHP client

All Seller Ledger API calls go through the official client lib
[`sellerledger/seller_ledger-php`](https://github.com/sellerledger/seller_ledger-php)
(`SellerLedger\Client::withApiKey( $token )`) — never hand-roll HTTP. If an endpoint is missing
from the lib, add it there and bump the lib, don't bypass it. The lib is pulled via a Composer
`vcs` repository pinned to a git tag, so releasing lib changes = tag the lib repo, then bump the
constraint here and `composer update sellerledger/seller_ledger-php`.

## Architecture

- **Entry**: `sellerledger-woocommerce.php` bootstraps `SellerLedger_Plugin`, which `include_once`s
  `includes/` and registers the WooCommerce integration. `SellerLedger_Integration` is the
  singleton wiring (`->token`, `->connection`, `->business`, `->transaction_sync`,
  `->tax_calculator`); access it via `SellerLedger()`.
- **Onboarding/connection**: `Settings` renders the WooCommerce settings tab; `Token` wraps the
  API key; `Connection` creates/verifies the Seller Ledger connection; `Business` pulls business
  metadata (sync start date, billing status).
- **Order/refund sync**: `Transaction` (abstract) + `Transaction_Order`/`Transaction_Refund`
  build API params and persist to a custom `{prefix}sellerledger_queue` table; `Transaction_Sync`
  hooks WooCommerce order events and drains the queue via Action Scheduler; `Transaction_Queries`
  reads the queue; `Settings_Queue` shows it. Uses HPOS-safe hooks (`OrderUtil`).
- **Sales tax at checkout**: `Cart_Tax_Request` builds a `/sales-tax/calculate` request from the
  cart + customer (mirrors the order param shape); `Tax_Calculator` calls the API on
  `woocommerce_before_calculate_totals`, injects the combined rate via the `woocommerce_find_rates`
  filter (so WooCommerce natively computes/persists/refunds the tax), caches per request +
  transient, and **falls back to native rates if the API fails so checkout never breaks**. Gated
  behind the `realtime_tax` setting + `wc_tax_enabled()`.
- **Install/uninstall**: `Install` manages the queue table via `dbDelta`; `uninstall.php` tears
  down on deletion.

## Distribution build

`vendor/` is gitignored but the WordPress.org zip **must** contain runtime deps (Guzzle + the SL
client). Run `./build.sh` — it installs `--no-dev`, runs **PHP-Scoper** (`scoper.inc.php`) to
namespace the bundled libraries under `SellerLedger\Vendor` so they can't collide with other
plugins' Guzzle, regenerates the autoloader, and zips `dist/seller-ledger.zip` excluding
`composer.lock`, `phpcs.xml.dist`, `tests/`, `scoper.inc.php`, `.git`, and the `assets/`
screenshots (those go in the SVN `assets/` dir, not the plugin zip). It **ships `composer.json`**
(WordPress.org asks for it when Composer is used, for dependency transparency), stripping only the
dev-only local `path` repository so the manifest resolves via the published VCS tag. The
plugin-facing `SellerLedger\` client namespace is intentionally left global so plugin code keeps
calling `SellerLedger\Client`. **`build.sh` archives `git HEAD`**, so commit before building — it
won't pick up uncommitted working-tree changes.
