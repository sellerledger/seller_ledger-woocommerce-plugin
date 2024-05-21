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

$active_plugins = (array) get_option( 'active_plugins', array() );
if ( is_multisite() ) {
  $active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
}

$woocommerce_active = in_array( 'woocommerce/woocommerce.php', $active_plugins ) || array_key_exists( 'woocommerce/woocommerce.php', $active_plugins );

final class WC_SellerLedger {

  public static $version = '0.0.1';

  public function __construct() {
    add_action( 'plugins_loaded', array( $this, 'init' ) );
    add_filter( 'plugin_action_links_' . plugin_basename ( __FILE__ ), array( $this, 'plugin_settings_link' ) );
    register_activation_hook( __FILE__, array( __CLASS__, 'plugin_registration_hook' ) );
  }

  public function init() {
    if ( class_exists( 'WC_Integration' ) ) {
    include_once 'includes/class-wc-sellerledger-connection.php';
    include_once 'includes/class-wc-sellerledger-integration.php';
    include_once 'includes/class-wc-sellerledger-settings.php';
    include_once 'includes/class-wc-sellerledger-api-request.php';

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

  public function plugin_settings_link( $links ) {
    $settings_link = '<a href="admin.php?page-wc-settings&tab=sellerledger-integration">Settings</a>';
    array_unshift( $links, $settings_link );
    return $links;
  }
}

$WC_SellerLedger = new WC_SellerLedger( __FILE__ );

function SellerLedger() {
  return WC_SellerLedger_Integration::instance();
}
