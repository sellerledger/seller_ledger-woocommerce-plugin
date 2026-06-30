<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_SellerLedger_Cart_Tax_Request {

	const SUPPORTED_COUNTRIES      = array( 'US' );
	const MARKETPLACE_REMITTED_TAX = false;

	const PARAM_KEYS = array(
		'transaction_date',
		'currency_code',
		'ship_to_country_code',
		'ship_to_state',
		'ship_to_zip',
		'ship_to_city',
		'total_amount',
		'items_subtotal',
		'shipping_amount',
		'discount_amount',
		'tax_amount',
		'marketplace_remitted_tax',
		'items',
	);

	private $cart;
	private $customer;

	public static function from_cart( $cart, $customer ) {
		return new self( $cart, $customer );
	}

	public function __construct( $cart, $customer ) {
		$this->cart     = $cart;
		$this->customer = $customer;
	}

	public function is_calculable() {
		$address = $this->address();

		return in_array( $address['ship_to_country_code'], self::SUPPORTED_COUNTRIES, true )
			&& '' !== $address['ship_to_state']
			&& '' !== $address['ship_to_zip']
			&& ! empty( $this->line_items() );
	}

	public function to_params() {
		return self::build_params( $this->address(), $this->line_items(), $this->totals() );
	}

	public function ship_to_state() {
		return $this->address()['ship_to_state'];
	}

	public function cache_key() {
		return 'sl_tax_' . md5( wp_json_encode( $this->canonical() ) );
	}

	public static function build_params( array $address, array $lines, array $totals ) {
		return array(
			'transaction_date'         => $totals['transaction_date'],
			'currency_code'            => $totals['currency_code'],
			'ship_to_country_code'     => $address['ship_to_country_code'],
			'ship_to_state'            => $address['ship_to_state'],
			'ship_to_zip'              => $address['ship_to_zip'],
			'ship_to_city'             => $address['ship_to_city'],
			'total_amount'             => $totals['total_amount'],
			'items_subtotal'           => $totals['items_subtotal'],
			'shipping_amount'          => $totals['shipping_amount'],
			'discount_amount'          => $totals['discount_amount'],
			'tax_amount'               => 0,
			'marketplace_remitted_tax' => self::MARKETPLACE_REMITTED_TAX,
			'items'                    => $lines,
		);
	}

	private function address() {
		$country = $this->customer->get_shipping_country();
		$state   = $this->customer->get_shipping_state();
		$zip     = $this->customer->get_shipping_postcode();
		$city    = $this->customer->get_shipping_city();

		if ( '' === $country ) {
			$country = $this->customer->get_billing_country();
			$state   = $this->customer->get_billing_state();
			$zip     = $this->customer->get_billing_postcode();
			$city    = $this->customer->get_billing_city();
		}

		return array(
			'ship_to_country_code' => (string) $country,
			'ship_to_state'        => (string) $state,
			'ship_to_zip'          => (string) $zip,
			'ship_to_city'         => (string) $city,
		);
	}

	private function line_items() {
		$items = array();

		foreach ( $this->cart->get_cart() as $cart_item ) {
			$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;

			$items[] = array(
				'product_name' => $product ? $product->get_name() : '',
				'sku'          => $product ? $product->get_sku() : '',
				'quantity'     => (int) $cart_item['quantity'],
				'item_amount'  => (float) $cart_item['line_subtotal'],
				'total_amount' => (float) $cart_item['line_total'],
			);
		}

		foreach ( $this->cart->get_fees() as $fee ) {
			$items[] = array(
				'product_name' => $fee->name,
				'quantity'     => 1,
				'item_amount'  => (float) $fee->amount,
				'total_amount' => (float) $fee->total,
			);
		}

		return $items;
	}

	private function fees_total() {
		$total = 0.0;

		foreach ( $this->cart->get_fees() as $fee ) {
			$total += (float) $fee->total;
		}

		return $total;
	}

	private function totals() {
		$subtotal = (float) $this->cart->get_subtotal() + $this->fees_total();
		$shipping = (float) $this->cart->get_shipping_total();
		$discount = (float) $this->cart->get_discount_total();

		return array(
			'transaction_date' => gmdate( 'Y-m-d' ),
			'currency_code'    => get_woocommerce_currency(),
			'items_subtotal'   => $subtotal,
			'shipping_amount'  => $shipping,
			'discount_amount'  => $discount,
			'total_amount'     => $subtotal + $shipping - $discount,
		);
	}

	private function canonical() {
		$address = $this->address();

		$lines = array_map(
			function ( $item ) {
				return array(
					$item['sku'],
					$item['product_name'],
					$item['quantity'],
					$item['item_amount'],
					$item['total_amount'],
				);
			},
			$this->line_items()
		);

		sort( $lines );

		$totals = $this->totals();
		unset( $totals['transaction_date'] );

		return array(
			'address' => $address,
			'lines'   => $lines,
			'totals'  => $totals,
		);
	}
}
