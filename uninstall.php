<?php

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/includes/class-sellerledger-transaction-sync.php';
SellerLedger_Transaction_Sync::unschedule();

/**
 * Stored data (connection, settings, sync history) is left intact on uninstall
 * unless a site explicitly defines SELLERLEDGER_UNINSTALL.
 */
if ( defined( 'SELLERLEDGER_UNINSTALL' ) && true === SELLERLEDGER_UNINSTALL ) {
	include_once __DIR__ . '/includes/class-sellerledger-install.php';
	include_once __DIR__ . '/includes/class-sellerledger-connection.php';
	include_once __DIR__ . '/includes/class-sellerledger-settings.php';
	include_once __DIR__ . '/includes/class-sellerledger-business.php';

	SellerLedger_Install::uninstall();
	SellerLedger_Connection::destroy();
	SellerLedger_Settings::destroy();
	SellerLedger_Business::uninstall();
	delete_option( 'sellerledger-business-name' );
	delete_option( 'sellerledger-tax-rate-id' );
}
