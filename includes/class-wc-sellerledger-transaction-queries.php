<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_SellerLedger_Transaction_Queries
{
  public static function ready_for_sync( $limit ) {
    global $wpdb;

    $sql = "select * from " . WC_SellerLedger_Transaction::table_name() . " where status in ('new', 'error') order by created_at asc limit " . $limit;
    $results = $wpdb->get_results( $sql, ARRAY_A );

    $records = array();

    foreach( $results as $result ) {
      $type = self::klass_from_type( $result[ 'record_type' ] );
      $records[] = WC_SellerLedger_Transaction::populate( new $type(), $result );
    }

    return $records;
  }

  # Gross
  public static function klass_from_type( $type ) {
    return ($type == "order" ? "WC_SellerLedger_Transaction_Order" : "WC_SellerLedger_Transaction_Refund");
  }
}
