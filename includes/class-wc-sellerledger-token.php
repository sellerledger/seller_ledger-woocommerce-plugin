<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

class WC_SellerLedger_Token {
  public function invalid() {
    return !$this->valid();
  }

  public function valid() {
    return ($this->apiToken() != null);
  }

  public function apiToken() {
    $settings = SellerLedger()->settings();
    return ($settings[ 'api_token' ] ?? null);
  }
}
