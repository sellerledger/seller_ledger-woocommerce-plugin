<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

# This should probably do something

class WC_SellerLedger_Transaction_Sync {
  private $integration;

  public function __construct( $integration ) {
    $this->integration = $integration;
  }
}
