<?php
if ( ! defined( "ABSPATH" ) ) {
	exit; // Exit if accessed directly.
}

class WC_SellerLedger_Business {
  private $token;
  private $sync_start_date;
  private $billing_status;

  public static function init( $token ) {
    $instance = new self( $token );
    $instance->get_metadata();
    return $instance;
  }

  public function __construct( $token ) {
    $this->token = $token;
  }

  public function get_metadata() {
    $request = new WC_SellerLedger_API_Request( $this->token );
    $response = $request->get("business");

    if ( $response->success() ) {
      $business = $response->body()->business;
      $this->sync_start_date = $business->data_syncable_start_date;
      $this->billing_status = $business->billing_status;
    }
  }

  public function sync_start_date() {
    return $this->sync_start_date;
  }

  public function billing_status() {
    return $this->billing_status;
  }
}
