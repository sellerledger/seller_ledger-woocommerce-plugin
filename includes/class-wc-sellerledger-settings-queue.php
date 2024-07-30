<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class WC_SellerLedger_Settings_Queue extends WP_List_Table {
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'record',
				'plural'   => 'records',
				'ajax'     => false,
			)
		);
	}

	public function print() {
		echo '</form>';
		echo '</br >';

		$this->prepare_items();

		echo '<div class="wrap">';
		echo esc_html($this->display());
		echo '</div>';
	}

	public function no_items() {
		return __( 'No orders have been queued.', 'wc-sellerledger' );
	}

	protected function display_tablenav( $which ) {
		return $this->pagination( $which );
	}

	public function column_default( $record, $column_name ) {
		switch ( $column_name ) {
			case 'record_type':
				return ucfirst( $record->record_type );
			case 'status':
				$status = $record->status;
				if ( $status == 'new' ) {
					return 'Pending';
				} else {
					return ucfirst( $status );
				}
			case 'order_status':
				$wc_order = wc_get_order( $record->record_id );
				return ucfirst( $wc_order->get_status() );
			default:
				return $record->$column_name;
		}
	}

	public function prepare_items() {
		$page          = absint( $this->get_pagenum() );
		$per_page      = absint( 20 );
		$offset        = absint( ( $page - 1 ) * $per_page );
		$records       = WC_SellerLedger_Transaction_Queries::all_with_status( '', $per_page, $offset );
		$total_records = WC_SellerLedger_Transaction_Queries::count_with_status( '' );
		$this->set_pagination_args(
			array(
				'total_items' => $total_records,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_records / $per_page ),
			)
		);
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$this->items           = $records;
	}

	public function get_columns() {
		return array(
			'id'           => __( 'ID', 'wc-sellerledger' ),
			'record_id'    => __( 'Record ID', 'wc-sellerledger' ),
			'record_type'  => __( 'Record Type', 'wc-sellerledger' ),
			'status'       => __( 'Queue Status', 'wc-sellerledger' ),
			'order_status' => __( 'Transaction Status', 'wc-sellerledger' ),
			'created_at'   => __( 'Created On', 'wc-sellerledger' ),
			'updated_at'   => __( 'Updated On', 'wc-sellerledger' ),
			'retry_count'  => __( 'Retry Count', 'wc-sellerledger' ),
			'last_error'   => __( 'Error', 'wc-sellerledger' ),
		);
	}
}
