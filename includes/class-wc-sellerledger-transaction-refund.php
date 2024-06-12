<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_SellerLedger_Transaction_Refund extends WC_SellerLedger_Transaction
{
  private $parent_order;

  public static function build( $data ) {
    $data[ 'record_type' ] = "refund";
    return self::populate( new self(), $data );
  }

  public function load() {
    $order = wc_get_order( $this->record_id );
    if ( $order instanceof WC_Order_Refund ) {
      $this->order = $order;
      $this->parent_order = wc_get_order( $order->get_parent_id() );
      $this->loaded = true;
    }

    return $this;
  }

  public function syncable_status() {
    return in_array( $this->parent_order->get_status(), self::SYNCABLE_STATUSES );
  }

  public function build_params() {
    $items_params = $this->line_items_to_params();

    return array(
      'id' => $this->record_id,
      'transaction_id' => $this->record_id,
      'transaction_reference_id' => $this->order->get_parent_id(),
      'transaction_date' => "{$this->order->get_date_created()}",
      'currency_code' => $this->order->get_currency(),
      'total_amount' => $this->order->get_total(),
      'goods_amount' => $this->goods_amount(),
      'shipping_amount' => $this->order->get_shipping_total(),
      'discount_amount' => $this->order->get_discount_total(),
      'tax_amount' => $this->order->get_total_tax(),
      'items' => $items_params
    );
  }

  public function build_optional_params() {
    return array(
      'ship_to_country_code' => $this->parent_order->get_shipping_country(),
      'ship_to_state' => $this->parent_order->get_shipping_state(),
      'ship_to_zip' => $this->parent_order->get_shipping_postcode(),
    );
  }

  public function required_fields_with_values() {
    return array(
      'id' => $this->record_id,
      'transaction_id' => $this->record_id,
      'transaction_reference_id' => $this->order->get_parent_id(),
      'transaction_date' => "{$this->order->get_date_created()}",
      'currency_code' => $this->order->get_currency(),
      'total_amount' => $this->order->get_total(),
      'goods_amount' => $this->goods_amount(),
      'ship_to_country_code' => $this->parent_order->get_shipping_country(),
      'ship_to_state' => $this->parent_order->get_shipping_state(),
      'ship_to_zip' => $this->parent_order->get_shipping_postcode(),
    );
  }

  public function root_path() {
    return "transactions/refunds";
  }
}
