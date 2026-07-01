<?php
/**
 * Plugin Name: Seller Ledger
 * Plugin URI: https://github.com/sellerledger/seller_ledger-woocommerce-plugin
 * Description: Sync your WooCommerce orders and refunds to Seller Ledger and calculate sales tax at checkout.
 * Author: Seller Ledger
 * Author URI: https://www.sellerledger.com
 * Version: 0.1.0
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Requires Plugins: woocommerce
 * WC requires at least: 8.8
 * WC tested up to: 10.9
 * Text Domain: seller-ledger
 * Domain Path: /languages
 * License: GNU General Public License v2.0 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Seller_Ledger
 */

defined( 'ABSPATH' ) || exit;

// woocommerce_db_version is set whenever WooCommerce is active (single-site or
// network), so this also covers the WooCommerce-inactive case without the
// multisite-unsafe active_plugins lookup. WP enforces activation order via the
// Requires Plugins header.
if ( version_compare( (string) get_option( 'woocommerce_db_version' ), SellerLedger_Plugin::$minimum_woocommerce_version, '<' ) ) {
	add_action( 'admin_notices', 'SellerLedger_Plugin::display_inactive_notice' );
	return;
}

if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	add_action( 'admin_notices', array( 'SellerLedger_Plugin', 'display_missing_dependencies_notice' ) );
	return;
}

require __DIR__ . '/vendor/autoload.php';

final class SellerLedger_Plugin {

	public static $version                     = '0.1.0';
	public static $minimum_woocommerce_version = '8.8.0';

	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		add_action( 'before_woocommerce_init', array( __CLASS__, 'declare_compatibility' ) );
		register_activation_hook( __FILE__, array( __CLASS__, 'plugin_registration_hook' ) );
		register_deactivation_hook( __FILE__, array( __CLASS__, 'deactivate' ) );
	}

	public static function declare_compatibility() {
		if ( ! class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			return;
		}

		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}

	public static function deactivate() {
		include_once __DIR__ . '/includes/class-sellerledger-transaction-sync.php';
		SellerLedger_Transaction_Sync::unschedule();
	}

	public function init() {
		if ( class_exists( 'WC_Integration' ) ) {
			include_once 'includes/class-sellerledger-logger.php';
			include_once 'includes/class-sellerledger-business.php';
			include_once 'includes/class-sellerledger-connection.php';
			include_once 'includes/class-sellerledger-token.php';
			include_once 'includes/class-sellerledger-integration.php';
			include_once 'includes/class-sellerledger-settings.php';
			include_once 'includes/class-sellerledger-settings-queue.php';
			include_once 'includes/class-sellerledger-settings-backfill.php';
			include_once 'includes/class-sellerledger-ajax.php';
			include_once 'includes/class-sellerledger-install.php';
			include_once 'includes/class-sellerledger-transaction.php';
			include_once 'includes/class-sellerledger-transaction-order.php';
			include_once 'includes/class-sellerledger-transaction-refund.php';
			include_once 'includes/class-sellerledger-transaction-queries.php';
			include_once 'includes/class-sellerledger-transaction-sync.php';
			include_once 'includes/class-sellerledger-cart-tax-request.php';
			include_once 'includes/class-sellerledger-tax-calculator.php';
			include_once 'includes/class-sellerledger-order-status.php';

			add_action( 'woocommerce_integrations_init', array( $this, 'add_integration' ), 20 );
		}
	}

	public function add_integration() {
		SellerLedger();
	}

	public static function plugin_registration_hook() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			exit( '<strong>Please activate Woocommerce before activating SellerLedger.</strong>' );
		}
	}

	public static function display_inactive_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		/* translators: 1: opening strong tag, 2: closing strong tag, 3: minimum WooCommerce version */
		$notice = sprintf( __( '%1$sSeller Ledger has been disabled.%2$s This version of Seller Ledger requires WooCommerce %3$s or newer. Please install or update WooCommerce to version %3$s or newer.', 'seller-ledger' ), '<strong>', '</strong>', self::$minimum_woocommerce_version );

		echo '<div class="error"><p>' . wp_kses( $notice, array( 'strong' => array() ) ) . '</p></div>';
	}

	public static function display_missing_dependencies_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="error"><p>' . esc_html__( 'Seller Ledger could not load its bundled libraries. Please reinstall the plugin from a complete package.', 'seller-ledger' ) . '</p></div>';
	}
}

new SellerLedger_Plugin();

function SellerLedger() {
	return SellerLedger_Integration::instance();
}
