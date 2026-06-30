<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_SellerLedger_Token {
	private $raw_token;

	public static function init( $raw_token ) {
		return new self( $raw_token );
	}

	public function __construct( $raw_token ) {
		$this->raw_token = is_string( $raw_token ) ? trim( $raw_token ) : $raw_token;
	}

	public function invalid() {
		return ! $this->valid();
	}

	public function valid() {
		return is_string( $this->raw_token ) && '' !== $this->raw_token;
	}

	public function get() {
		return $this->raw_token;
	}
}
