<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

class WC_SellerLedger_AJAX {

  public function __construct() {
    add_action( 'wp_ajax_wc_sellerledger_run_transaction_sync', array( $this, 'run_transaction_sync' ) );
  }

  public function run_transaction_sync() {
    check_admin_referer( 'sellerledger-transaction-sync', 'security' );

    $format = 'Y-m-d';
    $start_date = current_time( $format );
    $end_date = current_time( $format );

    if ( isset( $_POST[ "start_date" ] ) ) {
      $start_date = DateTime::createFromFormat( $format, $_POST[ "start_date" ] );
    }

    if ( isset( $_POST[ "end_date" ] ) ) {
      $end_date = DateTime::createFromFormat( $format, $_POST[ "end_date" ] );
    }

    $record_count = SellerLedger()->transaction_sync->backfill( $start_date->format( $format ), $end_date->format( $format ) );

    $response = array(
      'count' => $record_count,
      'error' => null
    );

    wp_send_json( $response );
  }
}

new WC_SellerLedger_AJAX();
