<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_SellerLedger_API_Response {
  private $raw_response;

  public function __construct( $raw_response ) {
    $this->raw_response = $raw_response;
  }

  public function body() {
    return json_decode( $this->raw_response['body'] );
  }

  public function success() {
    return !$this->error();
  }

  public function error() {
    return ( is_wp_error( $this->raw_response ) || $this->fourHundredCode() );
  }

  private function fourHundredCode() {
    return ( $this->raw_response['response']['code'] >= 400 );
  }
}
