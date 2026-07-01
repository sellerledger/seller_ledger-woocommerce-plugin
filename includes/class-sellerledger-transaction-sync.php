<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Automattic\WooCommerce\Utilities\OrderUtil;

class SellerLedger_Transaction_Sync {
	private $integration;

	const QUEUE_NAME         = 'sellerledger_queue';
	const GROUP_NAME         = 'sellerledger_group';
	const PROCESS_BATCH_HOOK = 'sellerledger_process_batch';
	const BACKFILL_HOOK      = 'sellerledger_backfill';
	const BATCH_SIZE         = 100;
	const BACKFILL_PAGE_SIZE = 100;

	public static function init( $integration ) {
		$instance = new self( $integration );
		$instance->add_hooks();
		$instance->initial_backfill();
		return $instance;
	}

	public static function schedule() {
		if ( ! as_has_scheduled_action( self::QUEUE_NAME ) ) {
			as_schedule_recurring_action( time(), 600, self::QUEUE_NAME, array(), self::GROUP_NAME );
		}
	}

	public static function unschedule() {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		as_unschedule_all_actions( self::QUEUE_NAME, array(), self::GROUP_NAME );
		as_unschedule_all_actions( self::PROCESS_BATCH_HOOK, array(), self::GROUP_NAME );
		as_unschedule_all_actions( self::BACKFILL_HOOK, array(), self::GROUP_NAME );
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
		add_action( self::PROCESS_BATCH_HOOK, array( $this, 'process_batch' ), 10, 1 );
		add_action( self::BACKFILL_HOOK, array( $this, 'run_backfill' ), 10, 3 );
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
			$this->schedule_backfill( $start_date, $end_date );
		}
	}

	/**
	 * Queue a date range for import as a background job. Each run processes one page
	 * and schedules the next until the range is exhausted, so a large history never
	 * blocks a web request.
	 *
	 * @param string $start_date Inclusive start date (Y-m-d).
	 * @param string $end_date   Inclusive end date (Y-m-d).
	 * @param int    $page       Page of results to process.
	 * @return bool Whether a job was scheduled (or run).
	 */
	public function schedule_backfill( $start_date, $end_date, $page = 1 ) {
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			if ( 1 === $page && as_has_scheduled_action( self::BACKFILL_HOOK, null, self::GROUP_NAME ) ) {
				return false;
			}

			as_enqueue_async_action( self::BACKFILL_HOOK, array( $start_date, $end_date, $page ), self::GROUP_NAME );
			return true;
		}

		$this->run_backfill( $start_date, $end_date, $page );
		return true;
	}

	public function run_backfill( $start_date, $end_date, $page = 1 ) {
		$earliest_start_date = $this->integration->business->sync_start_date();
		$bounded_start_date  = $start_date < $earliest_start_date ? $earliest_start_date : $start_date;
		$offset              = ( max( 1, (int) $page ) - 1 ) * self::BACKFILL_PAGE_SIZE;

		$orders_and_refunds = wc_get_orders(
			array(
				'limit'          => self::BACKFILL_PAGE_SIZE,
				'offset'         => $offset,
				'orderby'        => 'date',
				'order'          => 'ASC',
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

		$count = count( $orders_and_refunds );

		if ( $count >= self::BACKFILL_PAGE_SIZE ) {
			$this->schedule_backfill( $start_date, $end_date, $page + 1 );
		}

		return $count;
	}

	public function queue_order( $order_id ) {
		$order = SellerLedger_Transaction_Order::build( array( 'record_id' => $order_id ) );

		if ( ! $order->can_queue() ) {
			return;
		}

		$refunds_data = $order->refunds();

		foreach ( $refunds_data as $refund_data ) {
			$data   = array( 'record_id' => $refund_data->get_id() );
			$refund = SellerLedger_Transaction_Refund::build( $data );

			if ( ! $refund->can_queue() ) {
				continue;
			}

			$refund->save();
		}

		$order->save();
	}

	public function queue_refund( $order_id, $refund_id = null ) {
		$record_id = is_null( $refund_id ) ? $order_id : $refund_id;
		$refund    = SellerLedger_Transaction_Refund::build( array( 'record_id' => $record_id ) );

		if ( ! $refund->can_queue() ) {
			return;
		}

		$refund->save();
	}

	public function delete_order( $id ) {
		if ( 'shop_order' !== OrderUtil::get_order_type( $id ) ) {
			return;
		}

		$order         = SellerLedger_Transaction_Order::build( array( 'record_id' => $id ) );
		$connection_id = $this->integration->connection->get_connection_id();
		$client        = SellerLedger_Integration::api_client( $this->integration->token->get() );

		try {
			$client->deleteOrder( $connection_id, $order->record_id );
		} catch ( SellerLedger\Exception $e ) {
			SellerLedger()->log( "ERROR DELETING {$id} FROM SELLER LEDGER: " . $e->getMessage() );
		}

		$refunds_data = $order->refunds();
		$order->delete();

		foreach ( $refunds_data as $refund_data ) {
			$data = array(
				'record_id' => $refund_data->get_id(),
			);

			$refund = SellerLedger_Transaction_Refund::build( $data );

			try {
				$client->deleteRefund( $connection_id, $refund->record_id );
			} catch ( SellerLedger\Exception $e ) {
				SellerLedger()->log( "ERROR DELETING {$id} REFUND FROM SELLER LEDGER: " . $e->getMessage() );
			}

			$refund->delete();
		}
	}

	public function delete_refund( $id ) {
		if ( 'shop_order_refund' !== OrderUtil::get_order_type( $id ) ) {
			return;
		}

		$refund        = SellerLedger_Transaction_Refund::build( array( 'record_id' => $id ) );
		$connection_id = $this->integration->connection->get_connection_id();
		$client        = SellerLedger_Integration::api_client( $this->integration->token->get() );

		try {
			$client->deleteRefund( $connection_id, $refund->record_id );
		} catch ( SellerLedger\Exception $e ) {
			SellerLedger()->log( "ERROR DELETING REFUND {$id} FROM SELLER LEDGER: " . $e->getMessage() );
		}

		$refund->delete();
	}

	public function undelete_order( $id ) {
		if ( ! $id ) {
			return;
		}

		if ( 'shop_order' !== OrderUtil::get_order_type( $id ) ) {
			return;
		}

		$this->queue_order( $id );
	}

	public function cancel_order( $id, $order ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- $order is part of the woocommerce_order_status_cancelled action signature.
		$this->delete_order( $id );
	}

	/**
	 * Recurring job: split the active queue into batches and schedule a dedicated
	 * background action for each, so every batch is retried and observable on its
	 * own in the Scheduled Actions screen.
	 */
	public function process_queue() {
		if ( as_has_scheduled_action( self::PROCESS_BATCH_HOOK, null, self::GROUP_NAME ) ) {
			return;
		}

		$ids = SellerLedger_Transaction_Queries::active_ids();

		if ( empty( $ids ) ) {
			return;
		}

		foreach ( array_chunk( $ids, self::BATCH_SIZE ) as $chunk ) {
			as_schedule_single_action( time(), self::PROCESS_BATCH_HOOK, array( 'queue_ids' => $chunk ), self::GROUP_NAME );
		}
	}

	public function process_batch( $args ) {
		$ids = isset( $args['queue_ids'] ) ? array_map( 'intval', (array) $args['queue_ids'] ) : array();

		if ( empty( $ids ) ) {
			return;
		}

		$client = SellerLedger_Integration::api_client( $this->integration->token->get() );

		foreach ( SellerLedger_Transaction_Queries::for_ids( $ids ) as $transaction ) {
			if ( ! $transaction->can_sync() ) {
				continue;
			}

			$this->sync_transaction( $transaction, $client );
		}
	}

	private function sync_transaction( $transaction, $client ) {
		$connection_id = $this->integration->connection->get_connection_id();
		$body          = $transaction->to_params();
		$error         = false;

		try {
			if ( $transaction instanceof SellerLedger_Transaction_Order ) {
				$client->createOrder( $connection_id, $body );
			} else {
				$client->createRefund( $connection_id, $body );
			}
		} catch ( SellerLedger\Exception $e ) {
			$error = $e;
		}

		if ( $error && 406 === (int) $error->getCode() && false !== strpos( $error->getMessage(), 'Record not unique' ) ) {
			$error = false;
			try {
				if ( $transaction instanceof SellerLedger_Transaction_Order ) {
					$client->updateOrder( $connection_id, $transaction->record_id, $body );
				} else {
					$client->updateRefund( $connection_id, $transaction->record_id, $body );
				}
			} catch ( SellerLedger\Exception $e ) {
				$error = $e;
			}
		}

		if ( false === $error ) {
			$transaction->sync_success();
			$transaction->add_note( __( 'Order synced to Seller Ledger', 'seller-ledger' ) );
		} else {
			$transaction->sync_fail( $error->getMessage() );
		}
	}
}
