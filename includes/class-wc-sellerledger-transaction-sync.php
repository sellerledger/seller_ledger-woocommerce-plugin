<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Automattic\WooCommerce\Utilities\OrderUtil;

class WC_SellerLedger_Transaction_Sync {
	private $integration;

	const QUEUE_NAME = 'sellerledger_queue';
	const GROUP_NAME = 'sellerledger_group';

	public static function init( $integration ) {
		$instance = new self( $integration );
		$instance->add_hooks();
		$instance->initial_backfill();
		return $instance;
	}

	public static function schedule() {
		if ( as_has_scheduled_action( self::QUEUE_NAME ) == false ) {
			as_schedule_recurring_action( strtotime( 'now' ), 600, self::QUEUE_NAME, array(), self::GROUP_NAME );
		}
	}

	public static function unschedule() {
		if ( as_has_scheduled_action( self::QUEUE_NAME ) ) {
			as_unschedule_action( self::QUEUE_NAME, array(), self::GROUP_NAME );
		}
	}

	public function __construct( $integration ) {
		$this->integration = $integration;
	}

	public function add_hooks() {
		if ( ! $this->integration->active() ) {
			return;
		}

		add_action( 'admin_init', array( __CLASS__, 'schedule' ) );
		add_action( self::QUEUE_NAME, array( $this, 'process_queue' ) );
		add_action( 'woocommerce_new_order', array( $this, 'queue_order' ) );
		add_action( 'woocommerce_update_order', array( $this, 'queue_order' ) );
		add_action( 'woocommerce_order_refunded', array( $this, 'queue_refund' ), 10, 2 );
		add_action( 'woocommerce_trash_order', array( $this, 'delete_order' ), 9, 1 );
		add_action( 'woocommerce_delete_order', array( $this, 'delete_order' ), 9, 1 );
		add_action( 'woocommerce_delete_order_refund', array( $this, 'delete_refund' ), 9, 1 );
		add_action( 'woocommerce_untrash_order', array( $this, 'undelete_order' ), 11 );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'cancel_order' ), 10, 2 );
	}

	public function initial_backfill() {
		if ( $this->integration->business->needs_backfill() ) {
			$start_date = $this->integration->business->sync_start_date();
			$end_date   = current_time( 'Y-m-d' );
			$this->backfill( $start_date, $end_date );
		}
	}

	public function queue_order( $order_id ) {
		$order = WC_SellerLedger_Transaction_Order::build( array( 'record_id' => $order_id ) );

		if ( ! $order->can_queue() ) {
			return;
		}

		$refunds_data = $order->refunds();

		foreach ( $refunds_data as $refund_data ) {
			$data   = array( 'record_id' => $refund_data->get_id() );
			$refund = WC_SellerLedger_Transaction_Refund::build( $data );

			if ( ! $refund->can_queue() ) {
				continue;
			}

			$refund->save();
		}

		$order->save();
	}

	public function queue_refund( $order_id, $refund_id ) {
		$refund = WC_SellerLedger_Transaction_Refund::build( array( 'record_id' => $refund_id ) );

		if ( ! $refund->can_queue() ) {
			return;
		}

		$refund->save();
	}

	public function delete_order( $id ) {
		if ( OrderUtil::get_order_type( $id ) != 'shop_order' ) {
			return;
		}

		$order         = WC_SellerLedger_Transaction_Order::build( array( 'record_id' => $id ) );
		$connection_id = $this->integration->connection->getConnectionID();
		$client        = SellerLedger\Client::withApiKey( $this->integration->token->get() );

		try {
			$client->deleteOrder( $connection_id, $order->record_id );
		} catch ( SellerLedger\Exception $e ) {
			SellerLedger()->log( "ERROR DELETING {$id} FROM SELLER LEDGER" );
			SellerLedger()->log( $e->getMessage() );
		}

		$refunds_data = $order->refunds();
		$order->delete();

		foreach ( $refunds_data as $refund_data ) {
			$data = array(
				'record_id' => $refund_data->get_id(),
			);

			$refund = WC_SellerLedger_Transaction_Refund::build( $data );

			try {
				$client->deleteRefund( $connection_id, $refund->record_id );
			} catch ( SellerLedger\Exception $e ) {
				SellerLedger()->log( "ERROR DELETING {$id} REFUND FROM SELLER LEDGER" );
				SellerLedger()->log( $e->getMessage() );
			}

			$refund->delete();
		}
	}

	public function delete_refund( $id ) {
		if ( OrderUtil::get_order_type( $id ) != 'shop_order_refund' ) {
			return;
		}

		$refund        = WC_SellerLedger_Transaction_Refund::build( array( 'record_id' => $id ) );
		$connection_id = $this->integration->connection->getConnectionID();
		$client        = SellerLedger\Client::withApiKey( $this->integration->token->get() );

		try {
			$client->deleteRefund( $connection_id, $refund->record_id );
		} catch ( SellerLedger\Exception $e ) {
		}

		$refund->delete();
	}

	public function undelete_order( $id ) {
		if ( ! $id ) {
			return;
		}

		if ( OrderUtil::get_order_type( $id ) != 'shop_order' ) {
			return;
		}

		return $this->queue_order( $id );
	}

	public function cancel_order( $id, $order ) {
		return $this->delete_order( $id );
	}

	public function process_queue() {
		$client = SellerLedger\Client::withApiKey( $this->integration->token->get() );

		foreach ( WC_SellerLedger_Transaction_Queries::ready_for_sync( 20 ) as $transaction ) {
			if ( ! $transaction->can_sync() ) {
				continue;
			}

			$connection_id = $this->integration->connection->getConnectionID();
			$body          = $transaction->to_params();
			$error         = false;

			try {
				if ( $transaction instanceof WC_SellerLedger_Transaction_Order ) {
					$client->createOrder( $connection_id, $body );
				} else {
					$client->createRefund( $connection_id, $body );
				}
			} catch ( SellerLedger\Exception $e ) {
				$error = $e;
			}

			if ( $error && $error->getCode() == 406 && strpos( $error->getMessage(), 'Record not unique' ) !== false ) {
				$error = false;
				try {
					if ( $transaction instanceof WC_SellerLedger_Transaction_Order ) {
						$client->updateOrder( $connection_id, $transaction->record_id, $body );
					} else {
						$client->updateRefund( $connection_id, $transaction->record_id, $body );
					}
				} catch ( SellerLedger\Exception $e ) {
					$error = $e;
				}
			}

			if ( $error === false ) {
				$transaction->sync_success();
				$transaction->add_note( __( 'Order synced to Seller Ledger', 'wc-sellerledger' ) );
			} else {
				$transaction->sync_fail( $error->getMessage() );
			}
		}
	}

	public function backfill( $start_date, $end_date ) {
		$earliest_start_date = $this->integration->business->sync_start_date();
		$bounded_start_date  = $start_date < $earliest_start_date ? $earliest_start_date : $start_date;

		$orders_and_refunds = wc_get_orders(
			array(
				'limit'          => -1,
				'type'           => array( 'shop_order', 'shop_order_refund' ),
				'status'         => array( 'completed', 'refunded' ),
				'date_completed' => $bounded_start_date . '...' . $end_date,
			)
		);

		foreach ( $orders_and_refunds as $order ) {
			if ( $order instanceof WC_Order ) {
				$this->queue_order( $order->get_id() );
			} else {
				$this->queue_refund( $order->get_id() );
			}
		}

		return count( $orders_and_refunds );
	}
}
