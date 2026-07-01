<?php
/**
 * PHPUnit bootstrap for plugin unit tests.
 *
 * These are isolated unit tests: WordPress and WooCommerce functions are stubbed
 * with brain/monkey, so no live WP runtime is required. They cover the pure logic
 * (request building, cache keys, rate-injection decisions) — the actual hook wiring
 * is verified manually in a real WooCommerce install.
 */

define( 'ABSPATH', __DIR__ . '/' );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once dirname( __DIR__ ) . '/includes/class-sellerledger-cart-tax-request.php';
require_once dirname( __DIR__ ) . '/includes/class-sellerledger-tax-calculator.php';
