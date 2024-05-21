<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

class WC_SellerLedger_AJAX {

  public function __construct() {
		add_action( 'wp_ajax_wc_sellerledger_run_transaction_sync', array( $this, 'wc_sellerledger_run_transaction_sync' ) );
  }

  private function run_transaction_sync() {
    # do things
    return true;
  }
}
