<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WC_SellerLedger_Settings' ) ) :

	class WC_SellerLedger_Settings {

		public static $tab_id = 'sellerledger-integration';

		public static function init() {
			self::add_hooks();
			return __CLASS__;
		}

		public static function add_hooks() {
			add_filter( 'woocommerce_settings_tabs_array', array( __CLASS__, 'add_settings_tab' ), 50 );
			add_action( 'woocommerce_sections_' . self::$tab_id, array( __CLASS__, 'output_sections' ) );
			add_action( 'woocommerce_settings_' . self::$tab_id, array( __CLASS__, 'output_settings_page' ) );
			add_action( 'woocommerce_settings_save_' . self::$tab_id, array( __CLASS__, 'save' ) );
			add_filter( 'woocommerce_admin_settings_sanitize_option_' . self::get_stored_settings_identifier(), array( __CLASS__, 'sanitize' ), 10, 2 );
			add_action( 'admin_init', array( __CLASS__, 'maybe_consume_handoff' ) );
		}

		public static function get_stored_settings_identifier() {
			return 'woocommerce_' . self::$tab_id . '_settings';
		}

		public static function api_token() {
			return ( self::all()['api_token'] ?? null );
		}

		public static function realtime_tax_enabled() {
			return 'yes' === ( self::all()['realtime_tax'] ?? 'no' );
		}

		public static function all() {
			return WC_Admin_Settings::get_option( self::get_stored_settings_identifier() );
		}

		public static function destroy() {
			delete_option( self::get_stored_settings_identifier() );
		}

		public static function add_settings_tab( $settings_tabs ) {
			$settings_tabs[ self::$tab_id ] = __( 'Seller Ledger', 'seller-ledger' );
			return $settings_tabs;
		}

		public static function save() {
			$settings = self::get_settings_attributes();
			WC_Admin_Settings::save_fields( $settings );
		}

		public static function sanitize( $value, $option ) {
			parse_str( $option['id'], $option_details );
			$name         = current( array_keys( $option_details ) );
			$setting_name = key( $option_details[ $name ] );

			if ( 'api_token' === $setting_name ) {
				return wc_clean( $value );
			}

			if ( 'realtime_tax' === $setting_name ) {
				return 'yes' === $value ? 'yes' : 'no';
			}

			return $value;
		}

		public static function output_sections() {
			global $current_section;

			$sections = self::get_sections();

			if ( empty( $sections ) || 1 === count( $sections ) ) {
				return;
			}

			echo '<ul class="subsubsub">';

			$array_keys = array_keys( $sections );

			foreach ( $sections as $id => $label ) {
				$section_relative_url = 'admin.php?page=wc-settings&tab=' . self::$tab_id . '&section=' . sanitize_title( $id );
				$section_html         = '<li><a href="';
				$section_html        .= esc_url( admin_url( $section_relative_url ) );
				$section_html        .= '" class="' . ( $current_section === $id ? 'current' : '' ) . '">';
				$section_html        .= $label . '</a> ' . ( end( $array_keys ) === $id ? '' : '|' ) . ' </li>';
				echo wp_kses_post( $section_html );
			}

			echo '</ul><br class="clear" />';
		}

		public static function get_sections() {
			$sections = array(
				'' => __( 'Settings', 'seller-ledger' ),
			);

			if ( SellerLedger()->active() ) {
				$sections['transactions'] = __( 'Transactions', 'seller-ledger' );
			}

			return $sections;
		}

		public static function get_api_token_field( $hidden = false ) {
			return array(
				'title'   => ( $hidden ? '' : __( 'API key', 'seller-ledger' ) ),
				'type'    => 'text',
				'default' => '',
				'class'   => ( $hidden ? 'hidden' : '' ),
				'id'      => 'woocommerce_sellerledger-integration_settings[api_token]',
			);
		}

		public static function get_realtime_tax_field() {
			return array(
				'title'   => __( 'Real-time sales tax', 'seller-ledger' ),
				'type'    => 'checkbox',
				'desc'    => __( 'Calculate sales tax at checkout using Seller Ledger. This overrides WooCommerce\'s native tax rates and requires WooCommerce tax calculation to be enabled.', 'seller-ledger' ),
				'default' => 'no',
				'id'      => 'woocommerce_sellerledger-integration_settings[realtime_tax]',
			);
		}

		public static function get_section_split() {
			return array(
				'type' => 'sectionend',
			);
		}

		public static function get_settings_attributes() {
			$settings = array();
			$state    = SellerLedger()->connection_state();

			$settings[] = array(
				'title' => __( 'Seller Ledger', 'seller-ledger' ),
				'type'  => 'title',
				'desc'  => self::panel_description( $state ),
			);

			if ( 'connected' === $state ) {
				$settings[] = self::get_api_token_field( true );
				$settings[] = self::get_realtime_tax_field();
			} else {
				$settings[] = self::get_api_token_field();
			}

			$settings[] = self::get_section_split();

			return $settings;
		}

		private static function panel_description( $state ) {
			$key_url = esc_url( WC_SellerLedger_Integration::app_url() . '/settings/api' );

			switch ( $state ) {
				case 'connected':
					return self::connected_panel();

				case 'invalid_token':
					return '<span class="sl-status-error">' . esc_html__( 'That API key was not accepted. Check that you copied the full key and try again.', 'seller-ledger' ) . '</span>'
						. '<br>' . self::connect_steps( $key_url );

				case 'connection_failed':
					$desc = '<span class="sl-status-warning">' . esc_html__( 'We reached Seller Ledger but could not finish connecting. Save again in a moment to retry.', 'seller-ledger' ) . '</span>';

					$error = WC_SellerLedger_Connection::last_error();
					if ( $error && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						$desc .= '<br><code>' . esc_html( $error['message'] ) . '</code>';
					}
					return $desc;

				default:
					return self::connect_steps( $key_url );
			}
		}

		private static function connected_panel() {
			$business      = SellerLedger()->business_name();
			$connection_id = SellerLedger()->connection->get_connection_id();
			$counts        = WC_SellerLedger_Transaction_Queries::status_counts();
			$last          = WC_SellerLedger_Transaction_Queries::last_synced_at();

			$desc = '<span class="sl-status-connected">&#10004; ' . esc_html__( 'Connected to Seller Ledger.', 'seller-ledger' ) . '</span>';

			if ( $business ) {
				/* translators: %s: business name (wrapped in a strong tag) */
				$desc .= ' &mdash; ' . sprintf( esc_html__( 'Business: %s', 'seller-ledger' ), '<strong>' . esc_html( $business ) . '</strong>' );
			}

			$desc .= self::action_buttons( $connection_id );

			/* translators: %s: number of synced transactions */
			$stats = sprintf( esc_html__( 'Synced transactions: %s', 'seller-ledger' ), '<strong>' . esc_html( number_format_i18n( $counts['synced'] ) ) . '</strong>' );

			if ( $counts['pending'] > 0 ) {
				/* translators: %s: number of transactions waiting to sync */
				$stats .= ' &middot; ' . sprintf( esc_html__( '%s pending', 'seller-ledger' ), esc_html( number_format_i18n( $counts['pending'] ) ) );
			}

			if ( $counts['failed'] > 0 ) {
				/* translators: %s: number of transactions that failed to sync */
				$stats .= ' &middot; <span class="sl-status-error">' . sprintf( esc_html__( '%s failed', 'seller-ledger' ), esc_html( number_format_i18n( $counts['failed'] ) ) ) . '</span>';
			}

			if ( $last ) {
				/* translators: %s: human-readable time since the last successful sync */
				$stats .= '<br>' . sprintf( esc_html__( 'Last sync: %s ago', 'seller-ledger' ), esc_html( human_time_diff( strtotime( $last . ' UTC' ), time() ) ) );
			}

			$desc .= '<p>' . $stats . '</p>';
			$desc .= self::nexus_summary();

			return $desc;
		}

		private static function action_buttons( $connection_id ) {
			$html = '<p class="sl-actions" style="margin:10px 0">'
				. '<a href="' . esc_url( WC_SellerLedger_Integration::app_url() . '/dashboard' ) . '" class="button button-primary" target="_blank" rel="noopener">'
				. esc_html__( 'Open Seller Ledger dashboard', 'seller-ledger' ) . '</a>';

			if ( $connection_id ) {
				$html .= ' <a href="' . esc_url( WC_SellerLedger_Integration::app_url() . '/connections/' . rawurlencode( $connection_id ) ) . '" class="button" target="_blank" rel="noopener">'
					. esc_html__( 'View this connection', 'seller-ledger' ) . '</a>';
			}

			return $html . '</p>';
		}

		private static function nexus_summary() {
			if ( ! self::realtime_tax_enabled() || ! SellerLedger()->nexus_reachable() ) {
				return '';
			}

			$manage_url = esc_url( WC_SellerLedger_Integration::app_url() . '/taxes/sales' );

			$collecting  = array();
			$approaching = array();
			foreach ( (array) SellerLedger()->nexus_areas() as $area ) {
				if ( ! empty( $area->collecting ) ) {
					$collecting[] = $area;
				} elseif ( isset( $area->status ) && 0 === strpos( (string) $area->status, 'qualifying_' ) ) {
					$approaching[] = $area;
				}
			}

			if ( empty( $collecting ) ) {
				return '<br><span class="sl-status-warning">'
					. esc_html__( 'Real-time sales tax is on, but you have no established nexus in Seller Ledger, so no sales tax will be collected.', 'seller-ledger' )
					. ' <a href="' . $manage_url . '" target="_blank" rel="noopener">' . esc_html__( 'Set up your nexus', 'seller-ledger' ) . '</a></span>'
					. self::nexus_approaching_note( $approaching );
			}

			return self::nexus_table( $collecting, $manage_url ) . self::nexus_approaching_note( $approaching );
		}

		private static function nexus_table( $areas, $manage_url ) {
			/* translators: %d: number of states where the merchant collects sales tax */
			$heading = sprintf( _n( 'Collecting sales tax in %d state', 'Collecting sales tax in %d states', count( $areas ), 'seller-ledger' ), count( $areas ) );

			$html = '<p class="sl-nexus-heading"><strong>' . esc_html( $heading ) . '</strong> '
				. '(<a href="' . $manage_url . '" target="_blank" rel="noopener">' . esc_html__( 'manage', 'seller-ledger' ) . '</a>)</p>';

			$html .= '<table class="widefat striped" style="max-width:540px">'
				. '<thead><tr>'
				. '<th>' . esc_html__( 'State', 'seller-ledger' ) . '</th>'
				. '<th>' . esc_html__( 'Nexus', 'seller-ledger' ) . '</th>'
				. '<th>' . esc_html__( 'Since', 'seller-ledger' ) . '</th>'
				. '<th>' . esc_html__( 'Filing', 'seller-ledger' ) . '</th>'
				. '</tr></thead><tbody>';

			foreach ( $areas as $area ) {
				$name  = isset( $area->name ) ? $area->name : $area->state;
				$html .= '<tr>'
					. '<td>' . esc_html( $name ) . ' (' . esc_html( $area->state ) . ')</td>'
					. '<td>' . esc_html( self::nexus_type_label( $area ) ) . '</td>'
					. '<td>' . esc_html( self::nexus_since_label( $area ) ) . '</td>'
					. '<td>' . esc_html( self::nexus_filing_label( $area ) ) . '</td>'
					. '</tr>';
			}

			return $html . '</tbody></table>';
		}

		private static function nexus_approaching_note( $areas ) {
			if ( empty( $areas ) ) {
				return '';
			}

			$parts = array();
			foreach ( $areas as $area ) {
				$name = isset( $area->name ) ? $area->name : $area->state;
				$pct  = isset( $area->economic_nexus->amount_percentage ) ? (float) $area->economic_nexus->amount_percentage : null;

				$parts[] = null === $pct
					? esc_html( $name )
					/* translators: 1: state name, 2: percent toward the economic nexus threshold */
					: esc_html( sprintf( __( '%1$s (%2$d%% of threshold)', 'seller-ledger' ), $name, round( $pct ) ) );
			}

			return '<p class="sl-nexus-approaching"><em>'
				. esc_html__( 'Approaching economic nexus:', 'seller-ledger' ) . ' ' . implode( ', ', $parts )
				. '</em></p>';
		}

		private static function nexus_type_label( $area ) {
			switch ( isset( $area->nexus_basis ) ? $area->nexus_basis : '' ) {
				case 'economic':
					return __( 'Economic', 'seller-ledger' );
				case 'physical':
					return __( 'Physical', 'seller-ledger' );
				default:
					return '—';
			}
		}

		private static function nexus_since_label( $area ) {
			if ( empty( $area->nexus_start_date ) ) {
				return '—';
			}

			$timestamp = strtotime( $area->nexus_start_date );
			return $timestamp ? date_i18n( 'M Y', $timestamp ) : '—';
		}

		private static function nexus_filing_label( $area ) {
			if ( empty( $area->filing_frequency ) ) {
				return '—';
			}

			return ucfirst( $area->filing_frequency );
		}

		private static function connect_steps( $key_url ) {
			$button = '<p><a href="' . esc_url( self::connect_url() ) . '" class="button button-primary">'
				. esc_html__( 'Connect to Seller Ledger', 'seller-ledger' ) . '</a></p>';

			$manual = wp_kses_post(
				sprintf(
					/* translators: %s: URL of the Seller Ledger API keys page */
					__( 'Prefer to do it by hand? <a href="%s" target="_blank" rel="noopener">Find your API key here</a>, then paste it below and save.', 'seller-ledger' ),
					$key_url
				)
			);

			return $button . $manual;
		}

		private static function connect_url() {
			$state = wp_create_nonce( 'sellerledger_connect' );

			$return_url = admin_url( 'admin.php?page=wc-settings&tab=' . self::$tab_id . '&sl_connected=1' );

			// http_build_query (not add_query_arg) so the return_url's own query string
			// is URL-encoded into a single param instead of leaking to the top level.
			$query = http_build_query(
				array(
					'site'       => home_url(),
					'return_url' => $return_url,
					'state'      => $state,
				)
			);

			return WC_SellerLedger_Integration::app_url() . '/connect/woocommerce?' . $query;
		}

		public static function maybe_consume_handoff() {
			if ( ! isset( $_GET['sl_connected'] ) ) {
				return;
			}

			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				return;
			}

			$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';

			if ( ! wp_verify_nonce( $state, 'sellerledger_connect' ) ) {
				return;
			}

			$api_key       = isset( $_GET['api_key'] ) ? sanitize_text_field( wp_unslash( $_GET['api_key'] ) ) : '';
			$connection_id = isset( $_GET['connection_id'] ) ? sanitize_text_field( wp_unslash( $_GET['connection_id'] ) ) : '';

			if ( '' !== $api_key ) {
				$stored = get_option( self::get_stored_settings_identifier() );
				$stored = is_array( $stored ) ? $stored : array();

				$stored['api_token'] = $api_key;
				update_option( self::get_stored_settings_identifier(), $stored );
			}

			if ( '' !== $connection_id ) {
				update_option( WC_SellerLedger_Connection::CONNECTION_ID_OPTION, $connection_id );
			}

			wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=' . self::$tab_id ) );
			exit;
		}

		public static function output_settings_page() {
			global $current_section;
			global $hide_save_button; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce core global.

			if ( '' === $current_section ) {
				SellerLedger()->refresh_nexus_states();
				$settings = self::get_settings_attributes();
				WC_Admin_Settings::output_fields( $settings );
				wp_nonce_field( 'sellerledger_settings' );
			} elseif ( 'transactions' === $current_section ) {
				$hide_save_button = true; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce core global.

				echo '<h3>' . esc_html__( 'Import historical orders', 'seller-ledger' ) . '</h3>';
				$backfill = new WC_SellerLedger_Settings_Backfill( SellerLedger()->business );
				$backfill->render();

				echo '<h3>' . esc_html__( 'Transaction history', 'seller-ledger' ) . '</h3>';
				$queue = new WC_SellerLedger_Settings_Queue();
				$queue->render();
			}
		}
	}

endif;
