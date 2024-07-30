<?php
/**
* Plugin Name: Seller Ledger
* Plugin URI: http://github.com/sellerledger/seller_ledger-woocommerce-plugin
* Description: Seller Ledger's Woocommerce integration
* Author: Seller Ledger
* Version: 0.0.2
* Author URI: https://www.sellerledger.com
* @package Seller Ledger
* @version 0.0.2
* License: GNU General Public License v2.0 or later
* License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

defined( "ABSPATH" ) || exit;

$active_plugins = (array) get_option( "active_plugins", array() );
$woo_active = in_array( "woocommerce/woocommerce.php", $active_plugins );
if ( !$woo_active || version_compare( get_option( "woocommerce_db_version" ), WC_SellerLedger::$minimum_woocommerce_version, "<" ) ) {
  add_action( "admin_notices", "WC_SellerLedger::display_inactive_notice" );
  return;
}

require __DIR__ . '/vendor/autoload.php';

final class WC_SellerLedger {

  public static $version = "0.0.1";
  public static $minimum_woocommerce_version = "8.8.0";

  public function __construct() {
    add_action( "plugins_loaded", array( $this, "init" ) );
    register_activation_hook( __FILE__, array( __CLASS__, "plugin_registration_hook" ) );
  }

  public function init() {
    if ( class_exists( "WC_Integration" ) ) {
      include_once "includes/class-wc-sellerledger-business.php";
      include_once "includes/class-wc-sellerledger-connection.php";
      include_once "includes/class-wc-sellerledger-token.php";
      include_once "includes/class-wc-sellerledger-integration.php";
      include_once "includes/class-wc-sellerledger-settings.php";
      include_once "includes/class-wc-sellerledger-settings-queue.php";
      include_once "includes/class-wc-sellerledger-settings-backfill.php";
      include_once "includes/class-wc-sellerledger-ajax.php";
      include_once "includes/class-wc-sellerledger-install.php";
      include_once "includes/class-wc-sellerledger-transaction.php";
      include_once "includes/class-wc-sellerledger-transaction-order.php";
      include_once "includes/class-wc-sellerledger-transaction-refund.php";
      include_once "includes/class-wc-sellerledger-transaction-queries.php";
      include_once "includes/class-wc-sellerledger-transaction-sync.php";

      add_action( "woocommerce_integrations_init", array( $this, "add_integration" ), 20 );
    }
  }

  public function add_integration() {
    SellerLedger();
  }

  public static function plugin_registration_hook() {
    if ( !class_exists( "Woocommerce" ) ) {
      exit( "<strong>Please activate Woocommerce before activating SellerLedger.</strong>" );
    }
  }

  public static function display_inactive_notice() {
    if ( !current_user_can( "activate_plugins" ) ) {
      return;
    }

    /* translators: %s: HTML <strong> tags and Woocommerce version number */
    $notice = sprintf( __( "%1\$1sSeller Ledger has been disabled.%2\$2s This version of Seller Ledger requires WooCommerce %3\$3s or newer. Please install or update WooCommerce to version %3\$3s or newer.", "wc-sellerledger" ), "<strong>", "</strong>", self::$minimum_woocommerce_version, self::$minimum_woocommerce_version );

    ?>
      <div class="error">
        <p><?php echo esc_html($notice); ?></p>
      </div>
    <?php
  }
}

$WC_SellerLedger = new WC_SellerLedger( __FILE__ );

function SellerLedger() {
  return WC_SellerLedger_Integration::instance();
}
