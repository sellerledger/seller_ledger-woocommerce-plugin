<?php
/**
 * Sends WooPayments payouts to Seller Ledger.
 *
 * A WooPayments account is a Stripe account owned by Automattic, so Seller Ledger cannot
 * read it directly. The store can, through WooPayments' own admin routes, so the plugin
 * reads each payout and the transactions in it and sends them to a payment processor
 * connection in Seller Ledger. There they clear the WooPayments clearing account that
 * the store's orders post to.
 *
 * @package SellerLedger
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WooPayments payouts sync.
 */
class SellerLedger_WooPayments {

	const CONNECTION_ID_OPTION  = 'sellerledger-woopayments-connection-id';
	const SENT_OPTION           = 'sellerledger-woopayments-sent-payouts';
	const SYNC_HOOK             = 'sellerledger_woopayments_sync';
	const SYNC_INTERVAL         = 3600;
	const PAYOUT_PAGE_SIZE      = 25;
	const TRANSACTION_PAGE_SIZE = 100;
	const SENT_LIMIT            = 500;

	/**
	 * Payout statuses worth sending: paid ones are final, in-transit ones are resent when they land.
	 */
	const SENDABLE_STATUSES = array( 'paid', 'in_transit' );

	/**
	 * Currencies with no minor unit, whose amounts WooPayments reports as whole units.
	 */
	const ZERO_DECIMAL_CURRENCIES = array( 'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' );

	/**
	 * WooPayments transaction types mapped to the Seller Ledger payout transaction types.
	 */
	const TYPES = array(
		'charge'           => 'charge',
		'payment'          => 'charge',
		'refund'           => 'refund',
		'dispute'          => 'dispute',
		'dispute_reversal' => 'dispute_reversal',
		'fee'              => 'fee',
		'fee_refund'       => 'fee',
	);

	/**
	 * Integration.
	 *
	 * @var SellerLedger_Integration
	 */
	private $integration;

	public static function init( $integration ) {
		$instance = new self( $integration );
		$instance->add_hooks();
		return $instance;
	}

	public function __construct( $integration ) {
		$this->integration = $integration;
	}

	/**
	 * Whether WooPayments is installed and active on this store.
	 *
	 * @return bool
	 */
	public static function available() {
		return class_exists( 'WC_Payments' );
	}

	public static function connection_id() {
		return get_option( self::CONNECTION_ID_OPTION );
	}

	public static function forget() {
		delete_option( self::CONNECTION_ID_OPTION );
		delete_option( self::SENT_OPTION );
	}

	public static function unschedule() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::SYNC_HOOK, array(), SellerLedger_Transaction_Sync::GROUP_NAME );
		}
	}

	public function add_hooks() {
		if ( ! $this->integration->active() || ! self::available() ) {
			return;
		}

		add_action( 'admin_init', array( $this, 'ensure_connection' ) );
		add_action( 'admin_init', array( __CLASS__, 'schedule' ) );
		add_action( self::SYNC_HOOK, array( $this, 'sync' ) );
	}

	public static function schedule() {
		if ( ! as_has_scheduled_action( self::SYNC_HOOK ) ) {
			as_schedule_recurring_action( time(), self::SYNC_INTERVAL, self::SYNC_HOOK, array(), SellerLedger_Transaction_Sync::GROUP_NAME );
		}
	}

	/**
	 * Create the WooPayments payment processor connection in Seller Ledger once. Asking
	 * again with the same external id returns the same connection.
	 *
	 * @return string|null The connection id.
	 */
	public function ensure_connection() {
		if ( self::connection_id() ) {
			return self::connection_id();
		}

		$host = SellerLedger_Connection::store_host();

		try {
			$response = SellerLedger_Integration::api_client( $this->integration->token->get() )->createConnection(
				array(
					/* translators: %s: the store's domain */
					'name'                       => sprintf( __( 'WooPayments (%s)', 'seller-ledger' ), $host ),
					'balance_sheet_account_type' => 'asset',
					'kind'                       => 'payment_processor',
					'platform'                   => 'woopayments',
					'external_id'                => 'woopayments:' . $host,
				)
			);
			update_option( self::CONNECTION_ID_OPTION, $response->id );
			return $response->id;
		} catch ( SellerLedger\Exception $e ) {
			SellerLedger_Logger::error( 'Could not create the WooPayments connection: ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Send every recent payout that is new or has changed status since it was last sent.
	 */
	public function sync() {
		$connection_id = $this->ensure_connection();
		if ( ! $connection_id || ! $this->act_as_connected_user() ) {
			return;
		}

		$client = SellerLedger_Integration::api_client( $this->integration->token->get() );
		$sent   = (array) get_option( self::SENT_OPTION, array() );

		foreach ( $this->recent_payouts() as $payout ) {
			$status = (string) ( $payout['status'] ?? '' );
			$id     = (string) ( $payout['id'] ?? '' );

			if ( '' === $id || ! in_array( $status, self::SENDABLE_STATUSES, true ) || ( $sent[ $id ] ?? '' ) === $status ) {
				continue;
			}

			if ( 'withdrawal' === ( $payout['type'] ?? '' ) ) {
				SellerLedger_Logger::warning( 'Skipped a WooPayments withdrawal; Seller Ledger records payouts only.', array( 'payout' => $id ) );
				$sent[ $id ] = $status;
				continue;
			}

			$currency     = strtoupper( (string) ( $payout['currency'] ?? '' ) );
			$transactions = array_map(
				function ( $transaction ) use ( $currency ) {
					return self::map_transaction( $transaction, $currency );
				},
				$this->payout_transactions( $id )
			);

			try {
				$client->createPayout( $connection_id, array_merge( self::map_payout( $payout ), array( 'transactions' => $transactions ) ) );
				$sent[ $id ] = $status;
			} catch ( SellerLedger\Exception $e ) {
				SellerLedger_Logger::error( 'Could not send WooPayments payout ' . $id . ': ' . $e->getMessage(), array( 'payout' => $id ) );
			}
		}

		update_option( self::SENT_OPTION, array_slice( $sent, -self::SENT_LIMIT, null, true ), false );
	}

	/**
	 * A WooPayments payout in the shape POST /connections/:id/payouts takes.
	 *
	 * @param array $payout WooPayments payout.
	 * @return array
	 */
	public static function map_payout( $payout ) {
		$currency = strtoupper( (string) ( $payout['currency'] ?? '' ) );
		$date     = isset( $payout['date'] ) ? (int) $payout['date'] : 0;

		return array(
			'payout_id'     => (string) $payout['id'],
			'amount'        => self::to_major( $payout['amount'] ?? 0, $currency ),
			'currency_code' => $currency,
			'paid_out_at'   => gmdate( DATE_ATOM, (int) floor( $date / 1000 ) ),
			'status'        => (string) $payout['status'],
		);
	}

	/**
	 * A WooPayments report transaction in the shape a payout's transactions take.
	 *
	 * @param array  $transaction WooPayments transaction from reports/transactions.
	 * @param string $payout_currency The payout's currency, used when the transaction omits it.
	 * @return array
	 */
	public static function map_transaction( $transaction, $payout_currency ) {
		$currency = strtoupper( (string) ( $transaction['deposit_currency'] ?? $payout_currency ) );
		$amount   = self::to_major( $transaction['amount'] ?? 0, $currency );
		$fee      = abs( self::to_major( $transaction['fees'] ?? 0, $currency ) );
		$net      = isset( $transaction['net_amount'] ) ? self::to_major( $transaction['net_amount'], $currency ) : $amount - $fee;
		$type     = self::TYPES[ (string) ( $transaction['type'] ?? '' ) ] ?? 'adjustment';

		return SellerLedger_Payments::entry(
			array(
				'transaction_id'       => (string) $transaction['transaction_id'],
				'type'                 => $type,
				'amount'               => $amount,
				'fee'                  => $fee,
				'net'                  => $net,
				'currency_code'        => $currency,
				'created_at'           => gmdate( DATE_ATOM, strtotime( (string) ( $transaction['date'] ?? 'now' ) . ' UTC' ) ),
				'processor_payment_id' => (string) ( $transaction['payment_id'] ?? '' ),
				'processor_charge_id'  => (string) ( $transaction['charge_id'] ?? '' ),
				'order_reference'      => (string) ( $transaction['order_id'] ?? '' ),
				'description'          => 'adjustment' === $type ? (string) ( $transaction['type'] ?? '' ) : '',
			)
		);
	}

	/**
	 * An amount in a currency's minor units, in major units.
	 *
	 * @param int|float|string $amount Minor units.
	 * @param string           $currency ISO currency code.
	 * @return float
	 */
	public static function to_major( $amount, $currency ) {
		if ( in_array( strtoupper( (string) $currency ), self::ZERO_DECIMAL_CURRENCIES, true ) ) {
			return (float) $amount;
		}

		return round( (float) $amount / 100, 2 );
	}

	/**
	 * WooPayments' routes check manage_woocommerce, and a background job has no user, so the
	 * job acts as the admin who connected the store.
	 *
	 * @return bool
	 */
	private function act_as_connected_user() {
		if ( current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}

		$user_id = SellerLedger_Connection::connected_user_id();
		if ( ! $user_id || ! user_can( $user_id, 'manage_woocommerce' ) ) {
			SellerLedger_Logger::warning( 'WooPayments payouts were not sent: reconnect Seller Ledger as a store manager.' );
			return false;
		}

		wp_set_current_user( $user_id );
		return true;
	}

	private function recent_payouts() {
		return $this->rest_get(
			'/wc/v3/payments/deposits',
			array(
				'page'      => 1,
				'pagesize'  => self::PAYOUT_PAGE_SIZE,
				'sort'      => 'date',
				'direction' => 'desc',
			)
		);
	}

	private function payout_transactions( $payout_id ) {
		$all  = array();
		$page = 1;

		do {
			$batch = $this->rest_get(
				'/wc/v3/payments/reports/transactions',
				array(
					'deposit_id' => $payout_id,
					'page'       => $page,
					'per_page'   => self::TRANSACTION_PAGE_SIZE,
				)
			);
			$all   = array_merge( $all, $batch );
			$more  = count( $batch ) >= self::TRANSACTION_PAGE_SIZE;
			++$page;
		} while ( $more );

		return $all;
	}

	private function rest_get( $route, $params ) {
		$request = new WP_REST_Request( 'GET', $route );
		$request->set_query_params( $params );
		$response = rest_do_request( $request );

		if ( $response->is_error() ) {
			SellerLedger_Logger::error( 'WooPayments request failed: ' . $route, array( 'status' => $response->get_status() ) );
			return array();
		}

		$data = $response->get_data();
		if ( is_array( $data ) && isset( $data['data'] ) && is_array( $data['data'] ) ) {
			$data = $data['data'];
		}

		return is_array( $data ) ? array_values( array_map( array( __CLASS__, 'to_array' ), $data ) ) : array();
	}

	private static function to_array( $value ) {
		return is_object( $value ) ? json_decode( wp_json_encode( $value ), true ) : (array) $value;
	}
}
