<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_SellerLedger_Transaction_Queries {

	public static function ready_for_sync( $per_page = 50, $offset = 0 ) {
		global $wpdb;

		$results = $wpdb->get_results( $wpdb->prepare("select * from %i where status in ('new', 'error') order by created_at asc limit %d, %d", WC_SellerLedger_Transaction::table_name(), $offset, $per_page), ARRAY_A );

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
		return $wpdb->get_results( $wpdb->prepare("select * from %i %i order by id asc limit %d, %d", WC_SellerLedger_Transaction::table_name(), $where, $offset, $per_page ) );
	}

	public static function count_with_status( $status = '' ) {
		global $wpdb;

		$where = 'where 1 = 1';

		if ( $status != '' ) {
			$where = 'where status = ' . $status;
		}

		$sql = 'select count(*) from ' . WC_SellerLedger_Transaction::table_name() . ' ' . $where . ' order by id asc';
		return $wpdb->get_var( $wpdb->prepare( "select count(*) from %i %i order by id asc", WC_SellerLedger_Transaction::table_name(), $where ) );
	}

	// Gross
	public static function klass_from_type( $type ) {
		return ( $type == 'order' ? 'WC_SellerLedger_Transaction_Order' : 'WC_SellerLedger_Transaction_Refund' );
	}
}
