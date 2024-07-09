<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_SellerLedger_Transaction_Queries {

	public static function ready_for_sync( $per_page = 50, $offset = 0 ) {
		global $wpdb;

		$sql     = 'select * from ' . WC_SellerLedger_Transaction::table_name() . " where status in ('new', 'error') order by created_at asc limit " . $offset . ', ' . $per_page;
		$results = $wpdb->get_results( $sql, ARRAY_A );

		return self::reify( $results );
	}

	public static function reify( $results ) {
		$records = array();
		foreach ( $results as $result ) {
			$type      = self::klass_from_type( $result['record_type'] );
			$records[] = WC_SellerLedger_Transaction::populate( new $type(), $result );
		}

		return $records;
	}

	public static function all_with_status( $status = '', $per_page = 50, $offset = 0 ) {
		global $wpdb;

		$where = 'where 1 = 1';

		if ( $status != '' ) {
			$where = 'where status = ' . $status;
		}

		$sql = 'select * from ' . WC_SellerLedger_Transaction::table_name() . ' ' . $where . ' order by id asc limit ' . $offset . ', ' . $per_page;
		return $wpdb->get_results( $sql );
	}

	public static function count_with_status( $status = '' ) {
		global $wpdb;

		$where = 'where 1 = 1';

		if ( $status != '' ) {
			$where = 'where status = ' . $status;
		}

		$sql = 'select count(*) from ' . WC_SellerLedger_Transaction::table_name() . ' ' . $where . ' order by id asc';
		return $wpdb->get_var( $sql );
	}

	// Gross
	public static function klass_from_type( $type ) {
		return ( $type == 'order' ? 'WC_SellerLedger_Transaction_Order' : 'WC_SellerLedger_Transaction_Refund' );
	}
}
