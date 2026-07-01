<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Automattic\WooCommerce\Utilities\OrderUtil;

class SellerLedger_Order_Status {

	public static function init( $integration ) {
		if ( ! is_admin() || ! $integration->active() ) {
			return;
		}

		add_action( 'add_meta_boxes', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		$screen = OrderUtil::custom_orders_table_usage_is_enabled()
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';

		add_meta_box(
			'sellerledger-order-status',
			__( 'Seller Ledger', 'seller-ledger' ),
			array( __CLASS__, 'render' ),
			$screen,
			'side'
		);
	}

	public static function render( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		echo wp_kses_post( self::status_html( $order ) );
	}

	private static function status_html( $order ) {
		$transaction = SellerLedger_Transaction_Order::build( array( 'record_id' => $order->get_id() ) );

		if ( ! $transaction->syncable_status() ) {
			return '<p>' . esc_html__( 'This order will sync to Seller Ledger once it is marked completed.', 'seller-ledger' ) . '</p>';
		}

		if ( ! $transaction->required_fields_present() ) {
			return '<p class="sellerledger-status-warning">' . esc_html__( 'Not synced: this order is missing a shipping or billing address, which Seller Ledger requires.', 'seller-ledger' ) . '</p>';
		}

		$row = SellerLedger_Transaction_Queries::latest_for_record( $order->get_id(), 'order' );

		if ( $row && 'complete' === $row->status ) {
			return self::synced( $row->updated_at );
		}

		if ( $row && 'failed' === $row->status ) {
			/* translators: %s: error message */
			return '<p class="sellerledger-status-error">' . sprintf( esc_html__( 'Sync failed: %s', 'seller-ledger' ), esc_html( $row->last_error ) ) . '</p>';
		}

		if ( $row && 'error' === $row->status ) {
			/* translators: %s: error message */
			return '<p class="sellerledger-status-warning">' . sprintf( esc_html__( 'Sync error, will retry: %s', 'seller-ledger' ), esc_html( $row->last_error ) ) . '</p>';
		}

		if ( $row && 'new' === $row->status ) {
			return '<p>' . esc_html__( 'Queued — this order will sync to Seller Ledger shortly.', 'seller-ledger' ) . '</p>';
		}

		$synced_at = $order->get_meta( 'sellerledger_sync' );
		if ( $synced_at ) {
			return self::synced( $synced_at );
		}

		return '<p>' . esc_html__( 'Eligible to sync — this order will be queued shortly.', 'seller-ledger' ) . '</p>';
	}

	private static function synced( $when ) {
		$html = '<p class="sellerledger-status-connected">&#10004; ' . esc_html__( 'Synced to Seller Ledger', 'seller-ledger' ) . '</p>';

		if ( $when ) {
			$timestamp = strtotime( $when . ' UTC' );
			if ( $timestamp ) {
				/* translators: %s: human-readable time since the order synced */
				$html .= '<p>' . sprintf( esc_html__( '%s ago', 'seller-ledger' ), esc_html( human_time_diff( $timestamp, time() ) ) ) . '</p>';
			}
		}

		return $html;
	}
}
