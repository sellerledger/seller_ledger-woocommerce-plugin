<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

class WC_SellerLedger_Transaction_Sync {
  private $integration;

  const QUEUE_NAME = "sellerledger_queue";
  const GROUP_NAME = "sellerledger_group";

  public static function init( $integration ) {
    $instance = new self( $integration );
    $instance->add_hooks();
    return $instance;
  }

  public static function schedule_queue_processing() {
    $job_runs_at = as_next_scheduled_action( self::QUEUE_NAME );

    if ( ! $job_runs_at ) {
      $next_job_runs_at = time() + 600;
      as_schedule_single_action( $next_job_runs_at, self::QUEUE_NAME, array(), self::GROUP_NAME );
    }
  }

  public function __construct( $integration ) {
    $this->integration = $integration;
  }

  public function add_hooks() {
    add_action( 'admin_init', array( __CLASS__, 'schedule_queue_processing' ) );
    add_action( self::QUEUE_NAME, array( $this, 'process_queue' ) );
    add_action( 'woocommerce_new_order', array( $this, 'new_order' ) );
    add_action( 'woocommerce_update_order', array( $this, 'new_order' ) );
    # add_action( 'woocommerce_order_refunded', array( $this, 'new_refund' ) );
    # add_action( 'wp_trash_post', array( $this, 'delete_order' ) );
    # add_action( 'before_delete_post', array( $this, 'delete_order' ) );
    # add_action( 'before_delete_post', array( $this, 'delete_refund' ) );
    # add_action( 'untrashed_post', array( $this, 'undelete_order' ) );
    # add_action( 'woocommerce_order_status_cancelled', array( $this, 'cancel_order' ) );
    # add_action( 'woocommerce_order_status_cancelled', array( $this, 'cancel_order' ) );
  }

  public function new_order( $order_id ) {
    global $wpdb;

    $data = array(
      'record_id' => $order_id,
      'record_type' => 'order',
      'status' => 'new',
      'created_at' => gmdate( 'Y-m-d H:i:s' )
    );

    $record = new WC_SellerLedger_Transaction( $data );
    $record->save();
  }

  public function process_queue() {
    $request = new WC_SellerLedger_API_Request( $this->integration->token );

    foreach ( WC_SellerLedger_Transaction::ready_for_sync() as $transaction ) {
      $path = "connections/" . $this->integration->connection->getConnectionID() . "/transactions/orders";

      $body = $transaction->to_json();

      # This does a POST-then-PUT thing for now; we should try and track create vs update
      # internally then fall back to PUT if we get a 406.

      $response = $request->post( $path, $body );
      if ( $response->not_unique() ) {
        $response = $request->put( $path . "/" . $transaction->record_id, $body );
      }

      if ( $response->success() ) {
        $transaction->sync_success();
      } else {
        $transaction->sync_fail( $response->error_message() );
      }
    }
  }
}
