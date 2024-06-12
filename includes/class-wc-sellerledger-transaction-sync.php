<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

use Automattic\WooCommerce\Utilities\OrderUtil;

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
      $next_job_runs_at = time() + 30; # 600;
      as_schedule_single_action( $next_job_runs_at, self::QUEUE_NAME, array(), self::GROUP_NAME );
    }
  }

  public function __construct( $integration ) {
    $this->integration = $integration;
  }

  public function add_hooks() {
    if ( ! $this->integration->active() ) {
      return;
    }

    add_action( 'admin_init', array( __CLASS__, 'schedule_queue_processing' ) );
    add_action( self::QUEUE_NAME, array( $this, 'process_queue' ) );
    add_action( 'woocommerce_new_order', array( $this, 'queue_order' ) );
    add_action( 'woocommerce_update_order', array( $this, 'queue_order' ) );
    add_action( 'woocommerce_order_refunded', array( $this, 'queue_refund' ), 10, 2 );
    add_action( 'trashed_post', array( $this, 'delete_order' ), 9, 1 );
    add_action( 'before_delete_post', array( $this, 'delete_order' ), 9, 1 );
    add_action( 'before_delete_post', array( $this, 'delete_refund' ), 9, 1 );
    add_action( 'untrashed_post', array( $this, 'undelete_order' ), 11 );
    add_action( 'woocommerce_order_status_cancelled', array( $this, 'cancel_order' ), 10, 2 );
  }

  public function queue_order( $order_id ) {
    SellerLedger()->log( "QUEUING ORDER {$order_id}" );

    $order = WC_SellerLedger_Transaction_Order::build( array( 'record_id' => $order_id ) );

    if ( ! $order->can_queue() ) {
      return;
    }

    $refunds_data = $order->refunds();

    foreach ( $refunds_data as $refund_data ) {
      $data = array( 'record_id' => $refund_data->get_id() );
      $refund = WC_SellerLedger_Transaction_Refund::build( $data );

      if ( ! $refund->can_queue() ) {
        continue;
      }

      $refund->save();
    }

    $order->save();
  }

  public function queue_refund( $order_id, $refund_id ) {
    SellerLedger()->log( "QUEUING REFUND {$refund_id}" );

    $refund = WC_SellerLedger_Transaction_Refund::build( array( 'record_id' => $refund_id ) );

    if ( ! $refund->can_queue() ) {
      return;
    }

    $refund->save();
  }

  public function delete_order( $id ) {
    SellerLedger()->log( "DELETING ORDER {$id}" );

    if ( OrderUtil::get_order_type( $id ) != "shop_order" ) {
      return;
    }

    $order = WC_SellerLedger_Transaction_Order::build( array( "record_id" => $id ) );
    $request = new WC_SellerLedger_API_Request( $this->integration->token );
    $connection_id = $this->integration->connection->getConnectionID();

    $response = $request->delete( $order->record_uri( $connection_id ) );
    $refunds_data = $order->refunds();
    $order->delete();

    foreach ( $refunds_data as $refund_data ) {
      $data = array(
        'record_id' => $refund_data->get_id()
      );

      $refund = WC_SellerLedger_Transaction_Refund::build( $data );

      $request->delete( $refund->record_uri( $connection_id ) );
      $refund->delete();
    }
  }

  public function delete_refund( $id ) {
    SellerLedger()->log( "DELETING REFUND {$id}" );

    if ( OrderUtil::get_order_type( $id ) != "shop_order_refund" ) {
      return;
    }

    $refund = WC_SellerLedger_Transaction_Refund::build( array( "record_id" => $id ) );
    $request = new WC_SellerLedger_API_Request( $this->integration->token );
    $connection_id = $this->integration->connection->getConnectionID();

    $response = $request->delete( $refund->record_uri( $connection_id ) );
    $refund->delete();
  }

  public function undelete_order( $id ) {
    SellerLedger()->log( "UNDELETING ORDER {$id}" );

    if ( ! $id ) {
      return;
    }

    if ( OrderUtil::get_order_type( $id ) != "shop_order" ) {
      return;
    }

    return $this->queue_order( $id );
  }

  public function cancel_order( $id, $order ) {
    SellerLedger()->log( "CANCELING ORDER {$id}" );

    return $this->delete_order( $id );
  }

  public function process_queue() {
    $request = new WC_SellerLedger_API_Request( $this->integration->token );

    foreach ( WC_SellerLedger_Transaction_Queries::ready_for_sync( 20 ) as $transaction ) {
      if ( ! $transaction->can_sync() ) {
        continue;
      }

      $connection_id = $this->integration->connection->getConnectionID();
      $body = $transaction->to_json();
      $response = $request->post( $transaction->base_uri( $connection_id ), $body );

      if ( $response->not_unique() ) {
        $response = $request->put( $transaction->record_uri( $connection_id ), $body );
      }

      if ( $response->success() ) {
        $transaction->sync_success();
      } else {
        $transaction->sync_fail( $response->error_message() );
      }
    }
  }
}
