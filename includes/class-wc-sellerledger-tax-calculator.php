<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_SellerLedger_Tax_Calculator {

	const RATE_ID_OPTION = 'sellerledger-tax-rate-id';
	const CACHE_TTL      = 900;

	private $integration;
	private $result     = null;
	private $primed     = false;
	private $api_failed = false;
	private $collecting = false;

	public static function init( $integration ) {
		$instance = new self( $integration );
		$instance->add_hooks();
		return $instance;
	}

	public function __construct( $integration ) {
		$this->integration = $integration;
	}

	public function enabled() {
		return $this->integration->token->valid()
			&& $this->integration->connection->has_connection()
			&& WC_SellerLedger_Settings::realtime_tax_enabled()
			&& function_exists( 'wc_tax_enabled' )
			&& wc_tax_enabled();
	}

	public function add_hooks() {
		if ( ! $this->enabled() ) {
			if ( $this->misconfigured() ) {
				add_action( 'admin_notices', array( $this, 'tax_disabled_notice' ) );
			}
			return;
		}

		add_action( 'woocommerce_before_calculate_totals', array( $this, 'prime' ), 5, 1 );
		add_action( 'woocommerce_order_before_calculate_taxes', array( $this, 'prime_order' ), 5, 2 );
		add_filter( 'woocommerce_find_rates', array( $this, 'inject_rate' ), 100, 2 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'stamp_order' ), 10, 2 );
	}

	private function misconfigured() {
		return is_admin()
			&& WC_SellerLedger_Settings::realtime_tax_enabled()
			&& function_exists( 'wc_tax_enabled' )
			&& ! wc_tax_enabled();
	}

	public function tax_disabled_notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'Seller Ledger real-time sales tax is turned on, but WooCommerce tax calculation is disabled. Enable taxes under WooCommerce > Settings > General for it to take effect.', 'seller-ledger' );
		echo '</p></div>';
	}

	public function prime( $cart ) {
		$this->result     = null;
		$this->primed     = false;
		$this->api_failed = false;
		$this->collecting = false;

		if ( ! $cart instanceof WC_Cart ) {
			return;
		}

		$request = WC_SellerLedger_Cart_Tax_Request::from_cart( $cart, WC()->customer );

		if ( ! $request->is_calculable() ) {
			return;
		}

		$nexus = $this->integration->nexus_states();

		if ( ! $this->integration->nexus_reachable() ) {
			return;
		}

		$this->primed = true;

		if ( empty( $nexus ) || ! isset( $nexus[ $request->ship_to_state() ] ) ) {
			return;
		}

		$this->collecting = true;
		$this->result     = $this->lookup( $request );
	}

	public function prime_order( $args, $order ) {
		$this->result     = null;
		$this->primed     = false;
		$this->api_failed = false;
		$this->collecting = false;

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$country = ! empty( $args['country'] ) ? $args['country'] : $order->get_shipping_country();
		$state   = ! empty( $args['state'] ) ? $args['state'] : $order->get_shipping_state();
		$zip     = ! empty( $args['postcode'] ) ? $args['postcode'] : $order->get_shipping_postcode();

		if ( 'US' !== $country || '' === $state || '' === $zip ) {
			return;
		}

		if ( ! $this->integration->nexus_reachable() ) {
			return;
		}

		$this->primed = true;

		$nexus = $this->integration->nexus_states();
		if ( empty( $nexus ) || ! isset( $nexus[ $state ] ) ) {
			return;
		}

		$this->collecting = true;

		$params = WC_SellerLedger_Transaction_Order::build( array( 'record_id' => $order->get_id() ) )->to_params();
		if ( ! is_array( $params ) ) {
			return;
		}

		$params['ship_to_country_code'] = $country;
		$params['ship_to_state']        = $state;
		$params['ship_to_zip']          = $zip;
		$params['tax_amount']           = 0;

		$cache_key    = 'sl_tax_order_' . md5( wp_json_encode( array( $order->get_id(), $country, $state, $zip, $params['total_amount'] ?? '' ) ) );
		$this->result = $this->lookup_params( $params, $cache_key );
	}

	private function lookup( $request ) {
		return $this->lookup_params( $request->to_params(), $request->cache_key() );
	}

	private function lookup_params( $params, $cache_key ) {
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		try {
			$client = WC_SellerLedger_Integration::api_client( $this->integration->token->get() );
			$tax    = $client->calculateSalesTax(
				$params,
				array(
					'timeout'         => 5,
					'connect_timeout' => 3,
				)
			);

			set_transient( $cache_key, $tax, self::CACHE_TTL );
			return $tax;
		} catch ( SellerLedger\Exception $e ) {
			$this->api_failed = true;
			WC_SellerLedger_Logger::warning( 'Sales tax calculation rejected: ' . $e->getMessage(), array( 'code' => $e->getCode() ) );
			return null;
		} catch ( \Throwable $e ) {
			$this->api_failed = true;
			WC_SellerLedger_Logger::error( 'Sales tax calculation failed: ' . $e->getMessage() );
			return null;
		}
	}

	public function inject_rate( $rates, $args = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- $args is part of the woocommerce_find_rates filter signature.
		if ( ! $this->primed ) {
			return $rates;
		}

		if ( ! $this->collecting ) {
			return array();
		}

		if ( $this->api_failed ) {
			return $rates;
		}

		if ( is_null( $this->result ) || empty( $this->result->has_nexus ) ) {
			return array();
		}

		return array(
			$this->rate_id() => array(
				'rate'     => (string) ( (float) $this->result->rate * 100 ),
				'label'    => __( 'Sales Tax', 'seller-ledger' ),
				'shipping' => empty( $this->result->freight_taxable ) ? 'no' : 'yes',
				'compound' => 'no',
			),
		);
	}

	public function stamp_order( $order, $data ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- $data is part of the woocommerce_checkout_create_order action signature.
		if ( ! $this->primed || $this->api_failed || is_null( $this->result ) || empty( $this->result->has_nexus ) ) {
			return;
		}

		$order->update_meta_data( '_sellerledger_tax_source', 'realtime' );
		$order->update_meta_data( '_sellerledger_tax_rate', (string) $this->result->rate );
		$order->update_meta_data( '_sellerledger_amount_to_collect', (string) $this->result->amount_to_collect );
	}

	private function rate_id() {
		$id = get_option( self::RATE_ID_OPTION );

		if ( $id ) {
			return (int) $id;
		}

		$id = WC_Tax::_insert_tax_rate(
			array(
				'tax_rate_country'  => '',
				'tax_rate_state'    => '',
				'tax_rate'          => '0.0000',
				'tax_rate_name'     => 'Sales Tax',
				'tax_rate_priority' => 1,
				'tax_rate_compound' => 0,
				'tax_rate_shipping' => 1,
				'tax_rate_order'    => 0,
				'tax_rate_class'    => '',
			)
		);

		update_option( self::RATE_ID_OPTION, $id );
		return (int) $id;
	}
}
