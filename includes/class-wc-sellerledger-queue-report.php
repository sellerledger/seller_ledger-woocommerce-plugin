<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WP_List_Table' ) ) {
  require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class WC_SellerLedger_Queue_Report extends WP_List_Table {
  public function __construct() {
    parent::__construct(
      array(
        'singular' => 'record',
        'plural' => 'records',
        'ajax' => false
      )
    );
  }

  public function print() {
    echo "</form>";

    $this->prepare_items();

    echo '<div class="wrap">';
    echo $this->display();
    echo '</div>';
  }

  public function column_default( $record, $column_name ) {
    switch( $column_name ) {
      case 'record_type':
        return ucfirst( $record->record_type );
      case 'status':
        $status = $record->status;
        if ( $status == "new" ) {
          return "Pending";
        } else {
          return ucfirst( $status );
        }
      default:
        return $record->$column_name;
    }
  }

  public function prepare_items() {
    $page = absint( $this->get_pagenum() );
    $per_page = absint( 20 );
    $offset = absint( ( $page - 1 ) * $per_page );
    $records = WC_SellerLedger_Transaction_Queries::all_with_status( "", $per_page, $offset );
    $total_records = WC_SellerLedger_Transaction_Queries::count_with_status( "" );
    $this->set_pagination_args(
      array(
        'total_items' => $total_records,
        'per_page' => $per_page,
        'total_pages' => ceil( $total_records / $per_page )
      )
    );
    $this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
    $this->items = $records;
  }

  public function get_columns() {
    return array(
      "id" => __( "ID", "wc-sellerledger" ),
      "record_id" => __( "Record ID", "wc-sellerledger" ),
      "record_type" => __( "Record Type", "wc-sellerledger" ),
      "status" => __( "Status", "wc-sellerledger" ),
      "created_at" => __( "Created On", "wc-sellerledger" ),
      "updated_at" => __( "Updated On", "wc-sellerledger" ),
      "retry_count" => __( "Retry Count", "wc-sellerledger" ),
      "last_error" => __( "Error", "wc-sellerledger" )
    );
  }
}
