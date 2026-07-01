<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class SellerLedger_Settings_Queue extends WP_List_Table {
	private $orders = array();

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'record',
				'plural'   => 'records',
				'ajax'     => false,
			)
		);
	}

	public function render() {
		$this->prepare_items();

		echo '<div class="wrap">';
		$this->display();
		echo '</div>';
	}

	public function no_items() {
		return __( 'No orders have been queued.', 'seller-ledger' );
	}

	protected function display_tablenav( $which ) {
		return $this->pagination( $which );
	}

	public function column_default( $record, $column_name ) {
		switch ( $column_name ) {
			case 'record_type':
				return esc_html( ucfirst( $record->record_type ) );
			case 'status':
				$status = $record->status;
				if ( 'new' === $status ) {
					return esc_html__( 'Pending', 'seller-ledger' );
				}
				return esc_html( ucfirst( $status ) );
			case 'order_status':
				$wc_order = isset( $this->orders[ $record->record_id ] ) ? $this->orders[ $record->record_id ] : null;
				return $wc_order ? esc_html( ucfirst( $wc_order->get_status() ) ) : '';
			default:
				return esc_html( $record->$column_name );
		}
	}

	public function prepare_items() {
		$page          = absint( $this->get_pagenum() );
		$per_page      = absint( 20 );
		$offset        = absint( ( $page - 1 ) * $per_page );
		$records       = SellerLedger_Transaction_Queries::all_with_status( '', $per_page, $offset );
		$total_records = SellerLedger_Transaction_Queries::count_with_status( '' );
		$this->set_pagination_args(
			array(
				'total_items' => $total_records,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_records / $per_page ),
			)
		);
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$this->items           = $records;

		$this->orders = array();
		$ids          = array_filter( array_map( 'intval', wp_list_pluck( $records, 'record_id' ) ) );
		if ( ! empty( $ids ) ) {
			$orders = wc_get_orders(
				array(
					'limit'   => -1,
					'include' => $ids,
					'type'    => array( 'shop_order', 'shop_order_refund' ),
				)
			);
			foreach ( $orders as $order ) {
				$this->orders[ $order->get_id() ] = $order;
			}
		}
	}

	public function get_columns() {
		return array(
			'id'           => __( 'ID', 'seller-ledger' ),
			'record_id'    => __( 'Record ID', 'seller-ledger' ),
			'record_type'  => __( 'Record Type', 'seller-ledger' ),
			'status'       => __( 'Queue Status', 'seller-ledger' ),
			'order_status' => __( 'Transaction Status', 'seller-ledger' ),
			'created_at'   => __( 'Created On', 'seller-ledger' ),
			'updated_at'   => __( 'Updated On', 'seller-ledger' ),
			'retry_count'  => __( 'Retry Count', 'seller-ledger' ),
			'last_error'   => __( 'Error', 'seller-ledger' ),
		);
	}
}
