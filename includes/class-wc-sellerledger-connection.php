<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_SellerLedger_Connection {
	private $token;
	private $connection_id;

	const CONNECTION_ID_OPTION = 'sellerledger-connection-id';
	const ERROR_TRANSIENT      = 'sellerledger_connection_error';

	public static function init( $token ) {
		$instance = new self( $token );
		$instance->create();
		return $instance;
	}

	public static function destroy() {
		delete_option( self::CONNECTION_ID_OPTION );
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
		update_option( self::CONNECTION_ID_OPTION, $id );
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

		$details = array(
			'name'                       => html_entity_decode( get_bloginfo( 'name' ) ),
			'balance_sheet_account_type' => 'asset',
		);

		try {
			$client   = WC_SellerLedger_Integration::api_client( $this->token->get() );
			$response = $client->createConnection( $details );
			$this->set_connection_id( $response->id );
			delete_transient( self::ERROR_TRANSIENT );
		} catch ( SellerLedger\Exception $e ) {
			$this->record_error( $e );
		} catch ( \Throwable $e ) {
			SellerLedger()->log( 'SELLERLEDGER createConnection FAILED: ' . $e->getMessage() );
		}

		delete_transient( 'sellerledger_creating_connection' );

		return $this->has_connection();
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
