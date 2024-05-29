<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_SellerLedger_Transaction
{
  public $id;
  public $record_id;
  private $record_type;
  public $status;
  private $created_at;
  private $updated_at;
  private $retry_count;
  private $last_error;

  private $new_record;

  public $order;
  private $loaded = false;

  public static function table_name() {
    global $wpdb;
    return $wpdb->prefix . 'sellerledger_queue';
  }

  public static function build( $result ) {
    $instance = new self();
    $instance->id = $result[ 'id' ];
    $instance->record_id = $result[ 'record_id' ];
    $instance->record_type = $result[ 'record_type' ];
    $instance->status = $result[ 'status' ];
    $instance->created_at = $result[ 'created_at' ];
    $instance->updated_at = $result[ 'updated_at' ];
    $instance->retry_count = $result[ 'retry_count' ];
    $instance->last_error = $result[ 'last_error' ];
    $instance->new_record = is_null( $instance->id );
    $instance->load();
    return $instance;
  }

  public static function ready_for_sync() {
    global $wpdb;

    $sql = "select * from " . self::table_name() . " where status in ('new', 'error') order by created_at asc";
    $results = $wpdb->get_results( $sql, ARRAY_A );

    $records = array();

    foreach( $results as $result ) {
      $records[] = self::build( $result );
    }

    return $records;
  }

  public function load() {
    $order = wc_get_order( $this->record_id );
    if ( $order instanceof WC_Order ) {
      $this->order = $order;
      $this->loaded = true;
    }

    return $this;
  }

  public function to_json() {
    return json_encode( $this->to_params() );
  }

  public function to_params() {
    if ( ! $this->loaded ) {
      return null;
    }

    $items_params = $this->line_items_to_post_params();

    $data = array(
      'id' => $this->record_id,
      'transaction_id' => $this->record_id,
      'transaction_date' => "{$this->order->get_date_completed()}",
      'currency_code' => $this->order->get_currency(),
      'ship_to_country_code' => $this->order->get_shipping_country(),
      'ship_to_state' => $this->order->get_shipping_state(),
      'ship_to_zip' => $this->order->get_shipping_postcode(),
      'total_amount' => $this->order->get_total(),
      'goods_amount' => $this->order->get_subtotal(),
      'shipping_amount' => $this->order->get_shipping_total(),
      'discount_amount' => $this->order->get_discount_total(),
      'fees_amount' => $this->order->get_total_fees(),
      'tax_amount' => $this->order->get_total_tax(),
      'items' => $items_params
    );

    return $data;
  }

  public function line_items_to_post_params() {
    $data = array();

    foreach ( $this->order->get_items() as $item ) {
      $product = $item->get_product();

      $data[] = array(
        'product_name' => $product->get_name(),
        'quantity' => $item->get_quantity(),
        'total_amount' => $item->get_total(),
        'item_amount' => $item->get_subtotal()
      );
    }

    return $data;
  }

  public function sync_success() {
    $this->updated_at = gmdate( 'Y-m-d H:i:s' );
    $this->last_error = '';
    $this->status = 'complete';
    $this->save();
  }

  public function sync_fail( $reason ) {
    $this->updated_at = gmdate( 'Y-m-d H:i:s' );
    $this->last_error = $reason;
    $this->status = 'error';
    $this->retry_count = $this->retry_count + 1;

    if ( $this->retry_count >= 5 ) {
      $this->status = 'failed';
    }

    $this->save();
  }

  public function save() {
    global $wpdb;
    $data = array(
      'record_id' => $this->record_id,
      'record_type' => $this->record_type,
      'status' => $this->status,
      'created_at' => $this->created_at,
      'updated_at' => $this->updated_at,
      'retry_count' => $this->retry_count,
      'last_error' => $this->last_error
    );

    if ( $this->new_record ) {
      $result = $wpdb->insert( self::table_name(), $data );
      $this->id = $wpdb->insert_id;
      $this->new_record = false;
    } else {
      $where = array( 'id' => $this->id );
      $result = $wpdb->update( self::table_name(), $data, $where );
    }

    return $this;
  }

}
