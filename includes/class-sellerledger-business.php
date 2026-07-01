<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class SellerLedger_Business {
	private $integration;
	private $sync_start_date;
	private $billing_status;
	private $name;
	private $needs_backfill = false;

	const SYNC_START_DATE_OPTION = 'sellerledger-sync-start-date';
	const DATE_FORMAT            = 'Y-m-d';

	public static function init( $integration ) {
		$instance = new self( $integration );
		$instance->load();
		return $instance;
	}

	public static function uninstall() {
		delete_option( self::SYNC_START_DATE_OPTION );
	}

	public function __construct( $integration ) {
		$this->integration     = $integration;
		$this->sync_start_date = new DateTime( '3000-01-01' );
	}

	public function load() {
		$business = $this->integration->business_data();

		if ( is_null( $business ) ) {
			return false;
		}

		if ( isset( $business->data_syncable_start_date ) ) {
			$this->sync_start_date = new DateTime( $business->data_syncable_start_date );
		}

		if ( isset( $business->billing_status ) ) {
			$this->billing_status = $business->billing_status;
		}

		if ( isset( $business->name ) ) {
			$this->name = $business->name;
		}

		$this->update_sync_start_date();
	}

	private function update_sync_start_date() {
		$stored_date = get_option( self::SYNC_START_DATE_OPTION );

		if ( ! $stored_date || ( $stored_date < $this->sync_start_date() ) ) {
			$this->needs_backfill = true;
		}

		update_option( self::SYNC_START_DATE_OPTION, $this->sync_start_date() );
	}

	public function sync_start_date() {
		return $this->sync_start_date->format( self::DATE_FORMAT );
	}

	public function billing_status() {
		return $this->billing_status;
	}

	public function name() {
		return $this->name;
	}

	public function needs_backfill() {
		return $this->needs_backfill;
	}
}
