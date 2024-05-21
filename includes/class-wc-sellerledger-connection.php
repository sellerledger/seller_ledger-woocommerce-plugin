<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_SellerLedger_Connection {
  public function validToken() {
    $api_token = WC_SellerLedger_Settings::get_setting( 'api_token' );
    if ( $api_token != null ) {
      return true;
    } else {
      return false;
    }
  }
}
