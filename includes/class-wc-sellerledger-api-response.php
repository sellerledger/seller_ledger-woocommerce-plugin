<?php

if ( ! defined( "ABSPATH" ) ) {
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

  public function not_unique() {
    return $this->code() == 406 && $this->error_message() == "Record not unique";
  }

  public function debug() {
    return "CODE {$this->code()} ERROR {$this->error_message()}";
  }

  public function code() {
    return $this->raw_response[ 'response' ][ 'code' ];
  }

  public function error_message() {
    if ( $this->body() ) {
      return ($this->body()->error ?? "Unknown error");
    }
  }

  public function success() {
    return !$this->error();
  }

  public function error() {
    return ( is_wp_error( $this->raw_response ) || $this->code() >= 400 );
  }
}
