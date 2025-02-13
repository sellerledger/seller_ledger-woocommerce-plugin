<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_SellerLedger_Business {
	private $token;
	private $sync_start_date;
	private $billing_status;
	private $needs_backfill = false;

	const SYNC_START_DATE_OPTION = 'sellerledger-sync-start-date';
	const DATE_FORMAT            = 'Y-m-d';

	public static function init( $token ) {
		$instance = new self( $token );
		$instance->get_metadata();
		return $instance;
	}

	public static function uninstall() {
		delete_option( self::SYNC_START_DATE_OPTION );
	}

	public function __construct( $token ) {
		$this->token           = $token;
		$this->sync_start_date = new DateTime( '3000-01-01' );
	}

	public function get_metadata() {
		if ( $this->token->invalid() ) {
			return false;
		}

		try {
			$client                = SellerLedger\Client::withApiKey( $this->token->get() );
			$business              = $client->getBusiness();
			$this->sync_start_date = new DateTime( $business->data_syncable_start_date );
			$this->billing_status  = $business->billing_status;
			$this->update_sync_start_date();
		} catch ( SellerLedger\Exception $e ) {
		}
	}

	private function update_sync_start_date() {
		$stored_date = get_option( self::SYNC_START_DATE_OPTION );

		if ( ! $stored_date || ( $stored_date < $this->sync_start_date() ) ) {
			$this->needs_backfill = true;
		}

		add_option( self::SYNC_START_DATE_OPTION, $this->sync_start_date() );
	}

	public function sync_start_date() {
		return $this->sync_start_date->format( self::DATE_FORMAT );
	}

	public function billing_status() {
		return $this->billing_status;
	}

	public function needs_backfill() {
		return $this->needs_backfill;
	}
}
