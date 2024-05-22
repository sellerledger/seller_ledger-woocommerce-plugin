<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

class WC_SellerLedger_AJAX {

  public function __construct() {
    add_action( 'wp_ajax_wc_sellerledger_activate_plugin', array( $this, 'activate_plugin' ) );
    add_action( 'wp_ajax_wc_sellerledger_run_transaction_sync', array( $this, 'run_transaction_sync' ) );
  }

  public function activate_plugin() {
    check_admin_referer( 'sellerledger-transaction-sync', 'security' );

    $integration = SellerLedger();

    if ( isset( $_POST[ 'api_token' ] ) && "" != $_POST[ 'api_token' ] ) {
      $integration.activate();

      if ( $integration.activated() ) {
        $response = array(
          'status' => 'active'
        );
      } else {
        $response = array(
          'status' => 'invalid'
        );
      }
    } else {
      $response = array(
        'error' => 'Please enter an API token.'
      );
    }
  }

  public function run_transaction_sync() {
    return true;
  }
}

new WC_SellerLedger_AJAX();
