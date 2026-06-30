<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WC_SellerLedger_Integration' ) ) :

	class WC_SellerLedger_Integration {
		protected static $instance = null;
		public static $app_url     = 'https://app.sellerledger.com';

		const BUSINESS_NAME_OPTION = 'sellerledger-business-name';
		const BUSINESS_CACHE_TTL   = 300;
		const NEXUS_CACHE_TTL      = 900;

		public $id;
		public $settings;
		public $token;
		public $connection;
		public $business;
		public $transaction_sync;
		public $tax_calculator;

		private $business_data;
		private $business_data_loaded = false;
		private $business_error_code;
		private $business_api_reachable = false;
		private $nexus_areas;
		private $nexus_areas_loaded = false;
		private $nexus_states_ok    = false;

		public static function instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
				self::$instance->init();
			}
			return self::$instance;
		}

		/**
		 * Browser-facing base URL of the Seller Ledger app, used for links the user's
		 * browser follows (e.g. the Connect button). Override with the SELLERLEDGER_APP_URL
		 * constant or the sellerledger_app_url filter for local/staging environments.
		 */
		public static function app_url() {
			$url = ( defined( 'SELLERLEDGER_APP_URL' ) && SELLERLEDGER_APP_URL ) ? SELLERLEDGER_APP_URL : self::$app_url;

			/**
			 * Filters the browser-facing Seller Ledger app base URL.
			 *
			 * @since 0.1.0
			 * @param string $url Base URL of the Seller Ledger app.
			 */
			$url = apply_filters( 'sellerledger_app_url', $url );

			return untrailingslashit( $url );
		}

		/**
		 * Base URL the plugin makes API calls to (server-side). Defaults to app_url(),
		 * but can differ in containerized dev where the browser reaches the app at one
		 * host (localhost) and PHP reaches it at another (the Docker host gateway).
		 * Override with SELLERLEDGER_API_URL or the sellerledger_api_url filter.
		 */
		public static function api_url() {
			$url = ( defined( 'SELLERLEDGER_API_URL' ) && SELLERLEDGER_API_URL ) ? SELLERLEDGER_API_URL : self::app_url();

			/**
			 * Filters the server-side Seller Ledger API base URL.
			 *
			 * @since 0.1.0
			 * @param string $url Base URL the plugin sends API requests to.
			 */
			$url = apply_filters( 'sellerledger_api_url', $url );

			return untrailingslashit( $url );
		}

		/**
		 * Build an API client, honoring the api_url() override so the plugin can be
		 * pointed at a local Seller Ledger instance. All API calls go through this.
		 *
		 * @param string $token API key.
		 * @return SellerLedger\Client
		 */
		public static function api_client( $token ) {
			$client = SellerLedger\Client::withApiKey( $token );

			// Bound every request so a slow or unreachable Seller Ledger can never
			// hang a request (e.g. the nexus lookup during checkout).
			$client->setApiConfig( 'connect_timeout', 5 );
			$client->setApiConfig( 'timeout', 15 );

			if ( self::api_url() !== untrailingslashit( self::$app_url ) ) {
				$client->setApiConfig( 'base_uri', self::api_url() . '/v1/' );
			}

			return $client;
		}

		public static function log( $data ) {
			if ( is_array( $data ) || is_object( $data ) ) {
				$data = wp_json_encode( $data );
			}

			WC_SellerLedger_Logger::error( $data );
		}

		public function __construct() {
			$this->id = 'sellerledger-integration';
		}

		public function init() {
			$this->settings         = WC_SellerLedger_Settings::init();
			$this->token            = WC_SellerLedger_Token::init( $this->settings::api_token() );
			$this->connection       = WC_SellerLedger_Connection::init( $this->token );
			$this->business         = WC_SellerLedger_Business::init( $this );
			$this->transaction_sync = WC_SellerLedger_Transaction_Sync::init( $this );
			$this->tax_calculator   = WC_SellerLedger_Tax_Calculator::init( $this );
			WC_SellerLedger_Order_Status::init( $this );

			if ( is_admin() ) {
				add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_assets' ) );
			}
		}

		public function load_admin_assets( $hook_suffix ) {
			if ( 'woocommerce_page_wc-settings' !== $hook_suffix ) {
				return;
			}

			wp_enqueue_style(
				'wc-sellerledger-admin',
				plugin_dir_url( __FILE__ ) . 'css/wc-sellerledger-admin.css',
				array(),
				WC_SellerLedger::$version
			);

			wp_register_script(
				'wc-sellerledger-admin',
				plugin_dir_url( __FILE__ ) . 'js/wc-sellerledger-admin.js',
				array( 'jquery' ),
				WC_SellerLedger::$version,
				true
			);

			wp_localize_script(
				'wc-sellerledger-admin',
				'woocommerce_sellerledger_admin',
				array(
					'ajax_url'               => admin_url( 'admin-ajax.php' ),
					'transaction_sync_nonce' => wp_create_nonce( 'sellerledger-transaction-sync' ),
				)
			);

			wp_enqueue_script( 'wc-sellerledger-admin' );
		}

		/**
		 * Fetch the connected business once per request (cached in a transient between
		 * requests) so the settings screen and runtime checks don't each hit the API.
		 *
		 * @return object|null The business object, or null if it could not be loaded.
		 */
		public function business_data() {
			if ( $this->business_data_loaded ) {
				return $this->business_data;
			}

			$this->business_data_loaded   = true;
			$this->business_data          = null;
			$this->business_error_code    = null;
			$this->business_api_reachable = false;

			if ( $this->token->invalid() ) {
				return null;
			}

			$cache_key = 'sellerledger_business_' . md5( (string) $this->token->get() );
			$cached    = get_transient( $cache_key );

			if ( is_object( $cached ) ) {
				$this->business_data          = $cached;
				$this->business_api_reachable = true;
				return $cached;
			}

			// Never block a front-end page render on a third-party API call. The live
			// fetch only happens in admin, cron, or WP-CLI contexts; front-end requests
			// rely on the cached value (or the stored business-name option).
			if ( ! is_admin() && ! wp_doing_cron() && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
				return null;
			}

			try {
				$client   = self::api_client( $this->token->get() );
				$business = $client->getBusiness();

				$this->business_api_reachable = true;
				$this->business_data          = $business;

				if ( is_object( $business ) ) {
					set_transient( $cache_key, $business, self::BUSINESS_CACHE_TTL );

					if ( isset( $business->name ) ) {
						update_option( self::BUSINESS_NAME_OPTION, $business->name );
					}
				}
			} catch ( SellerLedger\Exception $e ) {
				$this->business_error_code = $e->getCode();
			} catch ( \Throwable $e ) {
				self::log( 'SELLERLEDGER getBusiness FAILED: ' . $e->getMessage() );
			}

			return $this->business_data;
		}

		public function refresh_business_data() {
			if ( $this->token->valid() ) {
				delete_transient( 'sellerledger_business_' . md5( (string) $this->token->get() ) );
			}

			$this->business_data_loaded = false;
			$this->business_data        = null;

			return $this->business_data();
		}

		public function nexus_areas() {
			if ( $this->nexus_areas_loaded ) {
				return $this->nexus_areas;
			}

			$this->nexus_areas_loaded = true;
			$this->nexus_areas        = array();
			$this->nexus_states_ok    = false;

			if ( $this->token->invalid() || ! $this->connection->has_connection() ) {
				return array();
			}

			$cache_key = 'sellerledger_nexus_' . md5( (string) $this->token->get() );
			$cached    = get_transient( $cache_key );

			if ( is_array( $cached ) ) {
				$this->nexus_areas     = $cached;
				$this->nexus_states_ok = true;
				return $cached;
			}

			try {
				$client = self::api_client( $this->token->get() );
				$nexus  = $client->getSalesTaxNexus();

				$this->nexus_areas     = is_array( $nexus ) ? array_values( $nexus ) : array();
				$this->nexus_states_ok = true;
				set_transient( $cache_key, $this->nexus_areas, self::NEXUS_CACHE_TTL );
			} catch ( SellerLedger\Exception $e ) {
				self::log( 'SELLERLEDGER getSalesTaxNexus FAILED: ' . $e->getMessage() );
			} catch ( \Throwable $e ) {
				self::log( 'SELLERLEDGER getSalesTaxNexus FAILED: ' . $e->getMessage() );
			}

			return $this->nexus_areas;
		}

		public function nexus_states() {
			$states = array();

			foreach ( $this->nexus_areas() as $area ) {
				if ( empty( $area->collecting ) || ! isset( $area->state ) || '' === $area->state ) {
					continue;
				}

				$states[ $area->state ] = isset( $area->name ) ? $area->name : $area->state;
			}

			return $states;
		}

		public function nexus_reachable() {
			$this->nexus_areas();
			return $this->nexus_states_ok;
		}

		public function refresh_nexus_states() {
			if ( $this->token->valid() ) {
				delete_transient( 'sellerledger_nexus_' . md5( (string) $this->token->get() ) );
			}

			$this->nexus_areas_loaded = false;
			$this->nexus_areas        = null;

			return $this->nexus_areas();
		}

		public function business_error_code() {
			$this->business_data();
			return $this->business_error_code;
		}

		public function business_name() {
			return get_option( self::BUSINESS_NAME_OPTION );
		}

		/**
		 * Resolve the current connection state.
		 *
		 * @return string One of disconnected, invalid_token, connection_failed, connected.
		 */
		public function connection_state() {
			if ( $this->token->invalid() ) {
				return 'disconnected';
			}

			$this->business_data();

			if ( in_array( $this->business_error_code, array( 401, 403 ), true ) ) {
				return 'invalid_token';
			}

			if ( 402 === (int) $this->business_error_code ) {
				return 'billing_locked';
			}

			if ( ! $this->business_api_reachable ) {
				return 'connection_failed';
			}

			return $this->connection->has_connection() ? 'connected' : 'connection_failed';
		}

		/**
		 * Lightweight "are we connected" check for the runtime hot path (hook
		 * registration, front-end). Token + stored connection id only, no API call.
		 * Use connection_state() in admin when the live status matters.
		 */
		public function active() {
			return $this->token->valid() && $this->connection->has_connection();
		}
	}

endif;
