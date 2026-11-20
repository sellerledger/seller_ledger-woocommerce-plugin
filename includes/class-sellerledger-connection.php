<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class SellerLedger_Connection {
	private $token;
	private $connection_id;

	const CONNECTION_ID_OPTION  = 'sellerledger-connection-id';
	const CONNECTED_USER_OPTION = 'sellerledger-connected-user-id';
	const ERROR_TRANSIENT       = 'sellerledger_connection_error';
	const KIND                  = 'order_management';
	const PLATFORM              = 'woocommerce';

	public static function init( $token ) {
		$instance = new self( $token );
		$instance->create();
		return $instance;
	}

	public static function destroy() {
		delete_option( self::CONNECTION_ID_OPTION );
		delete_option( self::CONNECTED_USER_OPTION );
	}

	/**
	 * The store's identity in Seller Ledger: its host, which is also what the one-click
	 * connect uses, so both ways of connecting land on the same connection.
	 *
	 * @return string
	 */
	public static function store_host() {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		return is_string( $host ) && '' !== $host ? $host : sanitize_title( get_bloginfo( 'name' ) );
	}

	/**
	 * Store a connection id. When it differs from the one already stored, the sync history
	 * belongs to another connection, so it is cleared and the store's history is sent again.
	 *
	 * @param string $id Connection id.
	 */
	public static function store_connection_id( $id ) {
		$previous = get_option( self::CONNECTION_ID_OPTION );
		update_option( self::CONNECTION_ID_OPTION, $id );

		if ( (string) $previous !== (string) $id ) {
			SellerLedger_Transaction_Queries::clear();
			SellerLedger_Business::uninstall();
		}
	}

	/**
	 * Remember the admin who connected, so background jobs that read WooCommerce's own
	 * admin-only data (WooPayments payouts) can act as them.
	 *
	 * @param int $user_id WordPress user id.
	 */
	public static function remember_user( $user_id ) {
		update_option( self::CONNECTED_USER_OPTION, (int) $user_id );
	}

	public static function connected_user_id() {
		return (int) get_option( self::CONNECTED_USER_OPTION );
	}

	public function __construct( $token ) {
		$this->token = $token;
	}

	public function get_connection_id() {
		if ( is_null( $this->connection_id ) ) {
			$this->connection_id = get_option( self::CONNECTION_ID_OPTION );
		}

		return $this->connection_id;
	}

	public function set_connection_id( $id ) {
		self::store_connection_id( $id );
		$this->connection_id = $id;
		return $id;
	}

	public function has_connection() {
		$id = $this->get_connection_id();
		return ! empty( $id );
	}

	public function create() {
		if ( $this->token->invalid() ) {
			return false;
		}

		// Creating a connection is a blocking API call; never run it during a
		// front-end page render.
		if ( ! is_admin() && ! wp_doing_cron() && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return false;
		}

		if ( $this->has_connection() ) {
			return false;
		}

		if ( 'yes' === get_transient( 'sellerledger_creating_connection' ) ) {
			return false;
		}

		set_transient( 'sellerledger_creating_connection', 'yes', 600 );

		$host    = self::store_host();
		$details = array(
			/* translators: %s: the store's domain */
			'name'                       => sprintf( __( 'WooCommerce (%s)', 'seller-ledger' ), $host ),
			'balance_sheet_account_type' => 'asset',
			'kind'                       => self::KIND,
			'platform'                   => self::PLATFORM,
			'external_id'                => $host,
		);

		try {
			$client   = SellerLedger_Integration::api_client( $this->token->get() );
			$response = $client->createConnection( $details );
			$this->set_connection_id( $response->id );
			if ( is_admin() ) {
				self::remember_user( get_current_user_id() );
			}
			delete_transient( self::ERROR_TRANSIENT );
		} catch ( SellerLedger\Exception $e ) {
			$this->record_error( $e );
		} catch ( \Throwable $e ) {
			SellerLedger()->log( 'SELLERLEDGER createConnection FAILED: ' . $e->getMessage() );
		}

		delete_transient( 'sellerledger_creating_connection' );

		return $this->has_connection();
	}

	/**
	 * Disconnect the store: revoke its key in Seller Ledger and forget the key, the
	 * connection and the sync history here. The books in Seller Ledger stay.
	 */
	public function disconnect() {
		if ( $this->token->valid() ) {
			try {
				SellerLedger_Integration::api_client( $this->token->get() )->revokeApiKey();
			} catch ( SellerLedger\Exception $e ) {
				SellerLedger()->log( 'SELLERLEDGER revokeApiKey FAILED: ' . $e->getMessage() );
			}
		}

		SellerLedger_Transaction_Sync::unschedule();
		SellerLedger_WooPayments::unschedule();
		SellerLedger_Transaction_Queries::clear();
		SellerLedger_WooPayments::forget();
		SellerLedger_Business::uninstall();
		self::destroy();
		SellerLedger_Settings::destroy();
		delete_option( SellerLedger_Integration::BUSINESS_NAME_OPTION );
		$this->connection_id = null;
	}

	public static function last_error() {
		$error = get_transient( self::ERROR_TRANSIENT );
		return is_array( $error ) ? $error : null;
	}

	public static function clear_error() {
		delete_transient( self::ERROR_TRANSIENT );
	}

	private function record_error( $exception ) {
		set_transient(
			self::ERROR_TRANSIENT,
			array(
				'code'    => $exception->getCode(),
				'message' => $exception->getMessage(),
			),
			60
		);

		SellerLedger()->log( 'SELLERLEDGER CONNECTION ERROR: ' . $exception->getMessage() );
	}
}
