<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_SellerLedger_Transaction_Order extends WC_SellerLedger_Transaction {

	public static function build( $data ) {
		$data['record_type'] = 'order';
		return self::populate( new self(), $data );
	}

	public function load() {
		$order = wc_get_order( $this->record_id );
		if ( $order instanceof WC_Order ) {
			$this->order  = $order;
			$this->loaded = true;
		}

		return $this;
	}

	public function syncable_status() {
		return in_array( $this->order->get_status(), self::SYNCABLE_STATUSES );
	}

	public function required_fields_with_values() {
		return array(
			'id'                   => $this->record_id,
			'transaction_id'       => $this->record_id,
			'transaction_date'     => "{$this->order->get_date_completed()}",
			'currency_code'        => $this->order->get_currency(),
			'total_amount'         => $this->order->get_total(),
			'goods_amount'         => $this->goods_amount(),
			'ship_to_country_code' => $this->order->get_shipping_country(),
			'ship_to_state'        => $this->order->get_shipping_state(),
			'ship_to_zip'          => $this->order->get_shipping_postcode(),
		);
	}

	public function root_path() {
		return '/transactions/orders';
	}

	public function add_note( $note ) {
		$this->order->add_order_note( $note );
	}
}
