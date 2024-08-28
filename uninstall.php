<?php

defined( "WP_UNINSTALL_PLUGIN" ) || exit;

include_once dirname( __FILE__ ) . "/includes/class-wc-sellerledger-transaction-sync.php";
WC_SellerLedger_Transaction_Sync::unschedule();

if ( defined( "SELLERLEDGER_UNINSTALL" ) && SELLERLEDGER_UNINSTALL == true ) {
  include_once dirname( __FILE__ ) . "/includes/class-wc-sellerledger-install.php";
  include_once dirname( __FILE__ ) . "/includes/class-wc-sellerledger-connection.php";
  include_once dirname( __FILE__ ) . "/includes/class-wc-sellerledger-settings.php";
  include_once dirname( __FILE__ ) . "/includes/class-wc-sellerledger-business.php";

  WC_SellerLedger_Install::uninstall();
  WC_SellerLedger_Connection::destroy();
  WC_SellerLedger_Settings::destroy();
  WC_SellerLedger_Business::uninstall();
}
