<?php
/**
* Plugin Name: Seller Ledger
* Plugin URI: http://github.com/sellerledger/seller_ledger-woocommerce-plugin
* Description: Seller Ledger's Woocommerce integration
* Author: Seller Ledger
* Version: 0.0.1
* Author URI: https://www.sellerledger.com
* @package Seller Ledger
* @version 0.0.1
*/

defined( 'ABSPATH' ) || exit;

final class WC_SellerLedger {

  public static $version = '0.0.1';

  public function __construct() {
    add_action( 'plugins_loaded', array( $this, 'init' ) );
    register_activation_hook( __FILE__, array( __CLASS__, 'plugin_registration_hook' ) );
  }

  public function init() {
    if ( class_exists( 'WC_Integration' ) ) {
      include_once 'includes/class-wc-sellerledger-connection.php';
      include_once 'includes/class-wc-sellerledger-token.php';
      include_once 'includes/class-wc-sellerledger-integration.php';
      include_once 'includes/class-wc-sellerledger-settings.php';
      include_once 'includes/class-wc-sellerledger-api-response.php';
      include_once 'includes/class-wc-sellerledger-api-request.php';
      include_once 'includes/class-wc-sellerledger-ajax.php';
      include_once 'includes/class-wc-sellerledger-install.php';
      include_once 'includes/class-wc-sellerledger-transaction.php';
      include_once 'includes/class-wc-sellerledger-transaction-order.php';
      include_once 'includes/class-wc-sellerledger-transaction-refund.php';
      include_once 'includes/class-wc-sellerledger-transaction-queries.php';
      include_once 'includes/class-wc-sellerledger-transaction-sync.php';
      include_once 'includes/class-wc-sellerledger-transaction-backfill.php';
      include_once 'includes/class-wc-sellerledger-queue-report.php';

      add_action( 'woocommerce_integrations_init', array( $this, 'add_integration' ), 20 );
    }
  }

  public function add_integration() {
    SellerLedger();
  }

  public static function plugin_registration_hook() {
    if ( !class_exists( 'Woocommerce' ) ) {
      exit( '<strong>Please activate Woocommerce before activating SellerLedger.</strong>' );
    }
  }
}

$WC_SellerLedger = new WC_SellerLedger( __FILE__ );

function SellerLedger() {
  return WC_SellerLedger_Integration::instance();
}
