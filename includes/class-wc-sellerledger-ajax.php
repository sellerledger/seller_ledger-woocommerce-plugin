<?php
/**
 * Seller Ledger AJAX actions.
 *
 * @package WC_SellerLedger_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_SellerLedger_AJAX {

	public function __construct() {
		add_action( 'wp_ajax_wc_sellerledger_run_transaction_sync', array( $this, 'run_transaction_sync' ) );
	}

	public function run_transaction_sync() {
		check_admin_referer( 'sellerledger-transaction-sync', 'security' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'error' => __( 'You are not allowed to do that.', 'seller-ledger' ) ), 403 );
		}

		$format     = 'Y-m-d';
		$start_date = $this->parse_date( 'start_date', $format );
		$end_date   = $this->parse_date( 'end_date', $format );

		try {
			SellerLedger()->transaction_sync->schedule_backfill( $start_date, $end_date );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'error' => $e->getMessage() ) );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Import started. Transactions will appear in the queue as they are processed.', 'seller-ledger' ),
			)
		);
	}

	private function parse_date( $key, $format ) {
		// Nonce is verified in run_transaction_sync() before this is called.
		if ( empty( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return current_time( $format );
		}

		$raw  = sanitize_text_field( wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$date = DateTime::createFromFormat( $format, $raw );

		return $date instanceof DateTime ? $date->format( $format ) : current_time( $format );
	}
}

new WC_SellerLedger_AJAX();
