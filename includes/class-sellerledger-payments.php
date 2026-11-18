<?php
/**
 * Describes how an order was paid in the shape the Seller Ledger API takes, so the
 * order's total can be booked to the right payment processor and later matched to
 * that processor's own records.
 *
 * @package SellerLedger
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Maps a WooCommerce order's gateway and transaction ids to Seller Ledger payments.
 */
class SellerLedger_Payments {

	/**
	 * Gateway ids (or id prefixes ending in "*") mapped to Seller Ledger processor keys.
	 */
	const PROCESSORS = array(
		'stripe'                   => 'stripe',
		'stripe_*'                 => 'stripe',
		'woocommerce_payments'     => 'woopayments',
		'woocommerce_payments_*'   => 'woopayments',
		'ppcp-*'                   => 'paypal',
		'paypal'                   => 'paypal',
		'square_*'                 => 'square',
		'braintree_*'              => 'braintree',
		'authorize_net_*'          => 'authorize_net',
		'klarna_payments'          => 'klarna',
		'affirm'                   => 'affirm',
		'amazon_payments_advanced' => 'amazon_pay',
		'afterpay'                 => 'afterpay',
		'clearpay'                 => 'afterpay',
		'cod'                      => 'cash',
		'cheque'                   => 'check',
		'bacs'                     => 'bank_deposit',
	);

	/**
	 * The Seller Ledger processor key for a WooCommerce gateway id.
	 *
	 * @param string $gateway_id The order's payment method id.
	 * @return string
	 */
	public static function processor_for( $gateway_id ) {
		$gateway_id = (string) $gateway_id;
		$processor  = '' === $gateway_id ? 'manual' : $gateway_id;

		foreach ( self::PROCESSORS as $pattern => $key ) {
			$is_prefix = '*' === substr( $pattern, -1 );
			$prefix    = rtrim( $pattern, '*' );

			if ( ( $is_prefix && 0 === strpos( $gateway_id, $prefix ) ) || ( ! $is_prefix && $gateway_id === $pattern ) ) {
				$processor = $key;
				break;
			}
		}

		/**
		 * Filters the Seller Ledger processor key for a gateway, for gateways the plugin does not know.
		 *
		 * @since 0.2.0
		 * @param string $processor  Processor key sent to Seller Ledger.
		 * @param string $gateway_id WooCommerce payment method id.
		 */
		return apply_filters( 'sellerledger_payment_processor', $processor, $gateway_id );
	}

	/**
	 * The processor's own ids for the payment on an order. Which id the gateway stores as
	 * the order's transaction id differs per gateway.
	 *
	 * @param string $processor Processor key.
	 * @param string $transaction_id The order's transaction id.
	 * @param array  $meta Order meta values: _stripe_intent_id, _charge_id, _intent_id.
	 * @return array processor_charge_id and processor_payment_id, either possibly empty.
	 */
	public static function processor_ids( $processor, $transaction_id, $meta ) {
		$transaction_id = (string) $transaction_id;

		switch ( $processor ) {
			case 'stripe':
				return array(
					'processor_charge_id'  => $transaction_id,
					'processor_payment_id' => (string) ( $meta['_stripe_intent_id'] ?? '' ),
				);
			case 'woopayments':
				return array(
					'processor_charge_id'  => (string) ( $meta['_charge_id'] ?? '' ),
					'processor_payment_id' => '' !== $transaction_id ? $transaction_id : (string) ( $meta['_intent_id'] ?? '' ),
				);
			case 'square':
				return array(
					'processor_charge_id'  => '',
					'processor_payment_id' => $transaction_id,
				);
			default:
				return array(
					'processor_charge_id'  => $transaction_id,
					'processor_payment_id' => '',
				);
		}
	}

	/**
	 * One payment entry, dropping empty ids.
	 *
	 * @param array $fields Payment fields.
	 * @return array
	 */
	public static function entry( $fields ) {
		return array_filter(
			$fields,
			function ( $value ) {
				return null !== $value && '' !== $value;
			}
		);
	}

	/**
	 * The payments for a paid order: one entry for the order's gateway.
	 *
	 * @param WC_Order $order The order.
	 * @return array
	 */
	public static function for_order( $order ) {
		$date_paid = $order->get_date_paid();
		if ( ! $date_paid ) {
			return array();
		}

		$processor = self::processor_for( $order->get_payment_method() );
		$ids       = self::processor_ids( $processor, $order->get_transaction_id(), self::order_meta( $order ) );

		return array(
			self::entry(
				array_merge(
					array(
						'transaction_id' => $order->get_id() . '-payment',
						'processor'      => $processor,
						'amount'         => (float) $order->get_total(),
						'currency_code'  => $order->get_currency(),
						'paid_at'        => $date_paid->format( DATE_ATOM ),
						'method'         => $order->get_payment_method_title(),
					),
					$ids
				)
			),
		);
	}

	/**
	 * The payment a refund returned, through the gateway that took the order's payment.
	 *
	 * @param WC_Order_Refund $refund The refund.
	 * @param WC_Order        $parent_order The order it refunds.
	 * @return array
	 */
	public static function for_refund( $refund, $parent_order ) {
		$amount = abs( (float) $refund->get_amount() );
		if ( $amount <= 0 ) {
			return array();
		}

		$processor = self::processor_for( $parent_order->get_payment_method() );
		$ids       = self::processor_ids( $processor, $parent_order->get_transaction_id(), self::order_meta( $parent_order ) );
		$created   = $refund->get_date_created();

		return array(
			self::entry(
				array_merge(
					array(
						'transaction_id' => $refund->get_id() . '-refund',
						'processor'      => $processor,
						'amount'         => $amount,
						'currency_code'  => $refund->get_currency(),
						'paid_at'        => $created ? $created->format( DATE_ATOM ) : gmdate( DATE_ATOM ),
					),
					$ids
				)
			),
		);
	}

	/**
	 * The meta values processor_ids() reads.
	 *
	 * @param WC_Order $order The order.
	 * @return array
	 */
	private static function order_meta( $order ) {
		$meta = array();
		foreach ( array( '_stripe_intent_id', '_charge_id', '_intent_id' ) as $key ) {
			$meta[ $key ] = $order->get_meta( $key );
		}
		return $meta;
	}
}
