<?php

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/includes/class-wc-sellerledger-transaction-sync.php';
WC_SellerLedger_Transaction_Sync::unschedule();

/**
 * Stored data (connection, settings, sync history) is left intact on uninstall
 * unless a site explicitly defines SELLERLEDGER_UNINSTALL.
 */
if ( defined( 'SELLERLEDGER_UNINSTALL' ) && true === SELLERLEDGER_UNINSTALL ) {
	include_once __DIR__ . '/includes/class-wc-sellerledger-install.php';
	include_once __DIR__ . '/includes/class-wc-sellerledger-connection.php';
	include_once __DIR__ . '/includes/class-wc-sellerledger-settings.php';
	include_once __DIR__ . '/includes/class-wc-sellerledger-business.php';

	WC_SellerLedger_Install::uninstall();
	WC_SellerLedger_Connection::destroy();
	WC_SellerLedger_Settings::destroy();
	WC_SellerLedger_Business::uninstall();
	delete_option( 'sellerledger-business-name' );
	delete_option( 'sellerledger-tax-rate-id' );
}
