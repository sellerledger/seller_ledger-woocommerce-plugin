<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

abstract class WC_SellerLedger_Transaction {

	public $id;
	public $record_id;
	public $record_type;
	public $status;
	public $created_at;
	public $updated_at;
	public $retry_count;
	public $last_error;

	public $new_record;

	public $order;
	public $loaded = false;

	const SYNCABLE_STATUSES = array( 'completed', 'refunded' );

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'sellerledger_queue';
	}

	public static function populate( $instance, $data ) {
		$instance->id          = $data['id'] ?? null;
		$instance->record_id   = $data['record_id'] ?? null;
		$instance->record_type = $data['record_type'] ?? null;
		$instance->status      = $data['status'] ?? 'new';
		$instance->created_at  = $data['created_at'] ?? gmdate( 'Y-m-d H:i:s' );
		$instance->updated_at  = $data['updated_at'] ?? gmdate( 'Y-m-d H:i:s' );
		$instance->retry_count = $data['retry_count'] ?? 0;
		$instance->last_error  = $data['last_error'] ?? '';
		$instance->new_record  = is_null( $instance->id );
		$instance->load();
		return $instance;
	}

	public function load() {
		return false;
	}

	public function can_queue() {
		return ! $this->is_queued() && $this->can_sync();
	}

	public function can_sync() {
		return $this->loaded && $this->syncable_status() && $this->required_fields_present();
	}

	public function required_fields_present() {
		foreach ( $this->required_fields_with_values() as $key => $val ) {
			if ( is_null( $val ) || '' === $val ) {
				return false;
			}
		}

		return true;
	}

	public function syncable_status() {
		return false;
	}

	public function refunds() {
		if ( ! $this->loaded ) {
			return array();
		}

		return $this->order->get_refunds();
	}

	public function to_json() {
		return wp_json_encode( $this->to_params() );
	}

	public function to_params() {
		if ( ! $this->loaded ) {
			return;
		}

		$data = $this->build_params();
		$data = $this->apply_optional_params( $data );

		return $data;
	}

	public function build_params() {
		$items_params = $this->line_items_to_params();

		return array(
			'id'               => $this->record_id,
			'transaction_id'   => $this->record_id,
			'transaction_date' => "{$this->order->get_date_completed()}",
			'currency_code'    => $this->order->get_currency(),
			'total_amount'     => $this->order->get_total(),
			'items_subtotal'   => $this->items_subtotal(),
			'shipping_amount'  => $this->order->get_shipping_total(),
			'discount_amount'  => $this->order->get_discount_total(),
			'tax_amount'       => $this->order->get_total_tax(),
			'items'            => $items_params,
		);
	}

	public function items_subtotal() {
		return $this->order->get_subtotal() + $this->order->get_total_fees();
	}

	public function apply_optional_params( $data ) {
		foreach ( $this->build_optional_params() as $key => $val ) {
			if ( ! is_null( $val ) && '' !== $val ) {
				$data[ $key ] = $val;
			}
		}

		return $data;
	}

	public function build_optional_params() {
		$ship = self::ship_to( $this->order );

		return array(
			'ship_to_country_code' => $ship['country'],
			'ship_to_state'        => $ship['state'],
			'ship_to_zip'          => $ship['zip'],
		);
	}

	public static function ship_to( $order ) {
		// Local pickup is taxed at the store's location, not the customer's.
		if ( self::is_local_pickup( $order ) ) {
			return self::store_address();
		}

		if ( '' !== $order->get_shipping_country() ) {
			return array(
				'country' => $order->get_shipping_country(),
				'state'   => $order->get_shipping_state(),
				'zip'     => $order->get_shipping_postcode(),
			);
		}

		return array(
			'country' => $order->get_billing_country(),
			'state'   => $order->get_billing_state(),
			'zip'     => $order->get_billing_postcode(),
		);
	}

	private static function is_local_pickup( $order ) {
		foreach ( $order->get_shipping_methods() as $method ) {
			if ( 'local_pickup' === $method->get_method_id() ) {
				return true;
			}
		}

		return false;
	}

	private static function store_address() {
		return array(
			'country' => WC()->countries->get_base_country(),
			'state'   => WC()->countries->get_base_state(),
			'zip'     => WC()->countries->get_base_postcode(),
		);
	}

	public static function buyer_name( $order ) {
		$name = trim( $order->get_formatted_billing_full_name() );
		if ( '' !== $name ) {
			return $name;
		}

		$company = $order->get_billing_company();
		if ( '' !== $company ) {
			return $company;
		}

		$email = $order->get_billing_email();
		if ( '' !== $email ) {
			return $email;
		}

		return __( 'Guest', 'seller-ledger' );
	}

	public function line_items_to_params() {
		$data = array();

		foreach ( $this->order->get_items( array( 'line_item', 'fee' ) ) as $item ) {
			if ( $item instanceof WC_Order_Item_Fee ) {
				$amount = (float) $item->get_amount();
				$tax    = (float) $item->get_total_tax();

				$data[] = array(
					'product_name'    => $item->get_name(),
					'quantity'        => 1,
					'item_amount'     => round( $amount, 2 ),
					'discount_amount' => 0,
					'tax_amount'      => round( $tax, 2 ),
					'total_amount'    => round( $amount + $tax, 2 ),
				);
			} else {
				$product  = $item->get_product();
				$subtotal = (float) $item->get_subtotal();
				$total    = (float) $item->get_total();
				$tax      = (float) $item->get_total_tax();

				$data[] = array(
					'product_name'    => $product ? $product->get_name() : $item->get_name(),
					'quantity'        => $item->get_quantity(),
					'item_amount'     => round( $subtotal, 2 ),
					'discount_amount' => round( $subtotal - $total, 2 ),
					'tax_amount'      => round( $tax, 2 ),
					'total_amount'    => round( $total + $tax, 2 ),
				);
			}
		}

		return $data;
	}

	public function sync_success() {
		$this->updated_at = gmdate( 'Y-m-d H:i:s' );
		$this->last_error = '';
		$this->status     = 'complete';
		$this->order->update_meta_data( 'sellerledger_sync', $this->updated_at );
		$this->order->save_meta_data();
		$this->save();

		WC_SellerLedger_Logger::info(
			'Synced ' . $this->record_type . ' #' . $this->record_id,
			array(
				'record_id' => $this->record_id,
				'type'      => $this->record_type,
			)
		);
	}

	public function sync_fail( $reason ) {
		$this->updated_at  = gmdate( 'Y-m-d H:i:s' );
		$this->last_error  = $reason;
		$this->status      = 'error';
		$this->retry_count = $this->retry_count + 1;

		if ( $this->retry_count >= 5 ) {
			$this->status = 'failed';
		}

		$this->order->update_meta_data( 'sellerledger_sync_error', $reason );
		$this->order->save_meta_data();
		$this->save();

		$context = array(
			'record_id' => $this->record_id,
			'type'      => $this->record_type,
			'retry'     => $this->retry_count,
		);
		if ( 'failed' === $this->status ) {
			WC_SellerLedger_Logger::error( 'Sync failed permanently: ' . $reason, $context );
		} else {
			WC_SellerLedger_Logger::warning( 'Sync failed, will retry: ' . $reason, $context );
		}
	}

	public function is_queued() {
		global $wpdb;

		$results = $wpdb->get_results( $wpdb->prepare( "SELECT record_id FROM %i WHERE record_id = %d AND record_type = %s AND status IN ( 'new', 'error' )", self::table_name(), $this->record_id, $this->record_type ), ARRAY_A );

		return ! empty( $results );
	}

	public function save() {
		global $wpdb;
		$data = array(
			'record_id'   => $this->record_id,
			'record_type' => $this->record_type,
			'status'      => $this->status,
			'created_at'  => $this->created_at,
			'updated_at'  => $this->updated_at,
			'retry_count' => $this->retry_count,
			'last_error'  => $this->last_error,
		);

		if ( $this->new_record ) {
			$result           = $wpdb->insert( self::table_name(), $data );
			$this->id         = $wpdb->insert_id;
			$this->new_record = false;
		} else {
			$where  = array( 'id' => $this->id );
			$result = $wpdb->update( self::table_name(), $data, $where );
		}

		return $this;
	}

	public function delete() {
		global $wpdb;

		if ( empty( $this->id ) ) {
			return;
		}

		return $wpdb->delete( self::table_name(), array( 'id' => $this->id ) );
	}

	abstract public function add_note( $note );
}
