<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_SellerLedger_Transaction_Queries {

	public static function active_ids( $limit = 0 ) {
		global $wpdb;

		$table = WC_SellerLedger_Transaction::table_name();

		if ( $limit > 0 ) {
			$rows = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM %i WHERE status IN ( 'new', 'error' ) ORDER BY created_at ASC LIMIT %d", $table, $limit ) );
		} else {
			$rows = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM %i WHERE status IN ( 'new', 'error' ) ORDER BY created_at ASC", $table ) );
		}

		return array_map( 'intval', (array) $rows );
	}

	public static function for_ids( array $ids ) {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );

		if ( empty( $ids ) ) {
			return array();
		}

		$table        = WC_SellerLedger_Transaction::table_name();
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

		// $placeholders is a list of %d built from the id count; the values are bound by prepare().
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, record_id, record_type, status, created_at, updated_at, retry_count, last_error FROM %i WHERE id IN ( $placeholders ) ORDER BY created_at ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( array( $table ), $ids )
			),
			ARRAY_A
		);

		return self::reify( $results );
	}

	public static function all_with_status( $status = '', $per_page = 50, $offset = 0 ) {
		global $wpdb;

		$table = WC_SellerLedger_Transaction::table_name();

		if ( '' === $status ) {
			return $wpdb->get_results( $wpdb->prepare( 'SELECT id, record_id, record_type, status, created_at, updated_at, retry_count, last_error FROM %i ORDER BY id ASC LIMIT %d, %d', $table, $offset, $per_page ) );
		}

		return $wpdb->get_results( $wpdb->prepare( 'SELECT id, record_id, record_type, status, created_at, updated_at, retry_count, last_error FROM %i WHERE status = %s ORDER BY id ASC LIMIT %d, %d', $table, $status, $offset, $per_page ) );
	}

	public static function count_with_status( $status = '' ) {
		global $wpdb;

		$table = WC_SellerLedger_Transaction::table_name();

		if ( '' === $status ) {
			return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
		}

		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s', $table, $status ) );
	}

	public static function status_counts() {
		global $wpdb;

		$table = WC_SellerLedger_Transaction::table_name();
		$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT status, COUNT(*) AS total FROM %i GROUP BY status', $table ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$counts = array(
			'synced'  => 0,
			'pending' => 0,
			'failed'  => 0,
			'total'   => 0,
		);

		foreach ( (array) $rows as $row ) {
			$count            = (int) $row['total'];
			$counts['total'] += $count;

			if ( 'complete' === $row['status'] ) {
				$counts['synced'] += $count;
			} elseif ( 'failed' === $row['status'] ) {
				$counts['failed'] += $count;
			} else {
				$counts['pending'] += $count;
			}
		}

		return $counts;
	}

	public static function latest_for_record( $record_id, $record_type ) {
		global $wpdb;

		$table = WC_SellerLedger_Transaction::table_name();
		return $wpdb->get_row( $wpdb->prepare( 'SELECT status, updated_at, last_error FROM %i WHERE record_id = %d AND record_type = %s ORDER BY id DESC LIMIT 1', $table, $record_id, $record_type ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	public static function last_synced_at() {
		global $wpdb;

		$table = WC_SellerLedger_Transaction::table_name();
		return $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(updated_at) FROM %i WHERE status = %s', $table, 'complete' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	public static function reify( $results ) {
		$records = array();

		foreach ( (array) $results as $result ) {
			$type      = self::klass_from_type( $result['record_type'] );
			$records[] = WC_SellerLedger_Transaction::populate( new $type(), $result );
		}

		return $records;
	}

	public static function klass_from_type( $type ) {
		return ( 'order' === $type ? 'WC_SellerLedger_Transaction_Order' : 'WC_SellerLedger_Transaction_Refund' );
	}
}
