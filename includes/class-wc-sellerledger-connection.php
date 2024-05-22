<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_SellerLedger_Connection {
  public function invalid() {
    $result = $this->verify();
    return ($this->verify() == false);
  }

  private function verify() {
    $request = new WC_SellerLedger_API_Request();
    $response = $request->get("categories");
    return $response->success();
  }
}
