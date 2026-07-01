<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Thin wrapper over the WooCommerce logger.
 *
 * Failures are always logged (error / warning) so problems are never silent.
 * Verbose activity (info / debug) is only written when "Debug logging" is
 * enabled in the plugin settings. Everything is tagged with the seller-ledger
 * source and appears under WooCommerce > Status > Logs.
 */
class SellerLedger_Logger {

	const SOURCE = 'seller-ledger';

	public static function error( $message, $context = array() ) {
		self::write( 'error', $message, $context );
	}

	public static function warning( $message, $context = array() ) {
		self::write( 'warning', $message, $context );
	}

	public static function info( $message, $context = array() ) {
		if ( self::debug_enabled() ) {
			self::write( 'info', $message, $context );
		}
	}

	public static function debug( $message, $context = array() ) {
		if ( self::debug_enabled() ) {
			self::write( 'debug', $message, $context );
		}
	}

	public static function debug_enabled() {
		return class_exists( 'SellerLedger_Settings' ) && SellerLedger_Settings::debug_logging_enabled();
	}

	private static function write( $level, $message, $context ) {
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		if ( ! empty( $context ) ) {
			$message .= ' ' . wp_json_encode( $context );
		}

		wc_get_logger()->log( $level, $message, array( 'source' => self::SOURCE ) );
	}
}
