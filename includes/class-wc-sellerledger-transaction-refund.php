<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_SellerLedger_Transaction_Refund extends WC_SellerLedger_Transaction {

	private $parent_order;

	public static function build( $data ) {
		$data['record_type'] = 'refund';
		return self::populate( new self(), $data );
	}

	public function load() {
		$order = wc_get_order( $this->record_id );
		if ( $order instanceof WC_Order_Refund ) {
			$this->order        = $order;
			$this->parent_order = wc_get_order( $order->get_parent_id() );
			$this->loaded       = true;
		}

		return $this;
	}

	public function syncable_status() {
		return in_array( $this->parent_order->get_status(), self::SYNCABLE_STATUSES, true );
	}

	public function build_params() {
		$items_params = $this->line_items_to_params();

		$data = array(
			'id'                       => $this->record_id,
			'transaction_id'           => $this->record_id,
			'transaction_reference_id' => $this->order->get_parent_id(),
			'transaction_date'         => "{$this->order->get_date_created()}",
			'currency_code'            => $this->order->get_currency(),
			'total_amount'             => $this->order->get_total(),
			'items_subtotal'           => $this->order->get_total(),
			'shipping_amount'          => $this->order->get_shipping_total(),
			'discount_amount'          => $this->order->get_discount_total(),
			'tax_amount'               => $this->order->get_total_tax(),
		);

		if ( count( $items_params ) > 0 ) {
			$data['items_subtotal'] = $this->items_subtotal();
			$data['items']          = $items_params;
		}

		return $data;
	}

	public function build_optional_params() {
		$ship = self::ship_to( $this->parent_order );

		return array(
			'ship_to_country_code' => $ship['country'],
			'ship_to_state'        => $ship['state'],
			'ship_to_zip'          => $ship['zip'],
			'transacted_with'      => self::buyer_name( $this->parent_order ),
		);
	}

	public function required_fields_with_values() {
		$ship = self::ship_to( $this->parent_order );

		return array(
			'id'                       => $this->record_id,
			'transaction_id'           => $this->record_id,
			'transaction_reference_id' => $this->order->get_parent_id(),
			'transaction_date'         => "{$this->order->get_date_created()}",
			'currency_code'            => $this->order->get_currency(),
			'total_amount'             => $this->order->get_total(),
			'items_subtotal'           => $this->items_subtotal(),
			'ship_to_country_code'     => $ship['country'],
			'ship_to_state'            => $ship['state'],
			'ship_to_zip'              => $ship['zip'],
		);
	}

	public function add_note( $note ) {
		$this->parent_order->add_order_note( $note );
	}
}
