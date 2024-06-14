<?php

if ( ! class_exists( "WC_SellerLedger_Settings" ) ) :

  class WC_SellerLedger_Settings {

    public static $tab_id = "sellerledger-integration";

    public static function init() {
      self::add_hooks();
      return __CLASS__;
    }

    public static function add_hooks() {
      add_filter( "woocommerce_settings_tabs_array", array( __CLASS__, "add_settings_tab" ), 50 );
      add_action( "woocommerce_sections_" . self::$tab_id, array( __CLASS__, "output_sections" ) );
      add_action( "woocommerce_settings_" . self::$tab_id, array( __CLASS__, "output_settings_page" ) );
      add_action( "woocommerce_settings_save_" . self::$tab_id, array( __CLASS__, "save" ) );
      add_filter( "woocommerce_admin_settings_sanitize_option_" . self::get_stored_settings_identifier(), array( __CLASS__, "sanitize" ), 10, 2 );
    }

    public static function get_stored_settings_identifier() {
      return "woocommerce_" . self::$tab_id . "_settings";
    }

    public static function api_token() {
      return (self::all()[ "api_token" ] ?? null);
    }

    public static function all() {
      return WC_Admin_Settings::get_option( self::get_stored_settings_identifier() );
    }

    public static function add_settings_tab( $settings_tabs ) {
      $settings_tabs[ self::$tab_id ] = __( "Seller Ledger", "sellerledger" );
      return $settings_tabs;
    }

    public static function save() {
      $settings = self::get_settings_attributes();
      WC_Admin_Settings::save_fields( $settings );
    }

    public static function sanitize( $value, $option ) {
      parse_str( $option["id"], $option_details );
      $name = current( array_keys( $option_details ) );
      $setting_name = key( $option_details[ $name ] );

      if ( $setting_name == "api_token" ) {
        return wc_clean( $value );
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
        $section_relative_url = "admin.php?page=wc-settings&tab=" . self::$tab_id . "&section=" . sanitize_title( $id );
        $section_html  = '<li><a href="';
        $section_html .= admin_url( $section_relative_url );
        $section_html .= '" class="' . ( $current_section === $id ? "current" : "" ) . '">';
        $section_html .= $label . "</a> " . ( end( $array_keys ) === $id ? "" : "|" ) . " </li>";
        echo wp_kses_post( $section_html );
      }

      echo '</ul><br class="clear" />';
    }

    public static function get_sections() {
      $sections = array(
        ""                     => __( "Settings", "woocommerce" ),
        "transaction_backfill" => __( "Transaction Sync", "wc-sellerledger" ),
        "queue" => __( "Transaction Queue", "wc-sellerledger" ),
      );
      return $sections;
    }

    public static function get_title( $title, $description = "" ) {
      return array(
        "title" => __( $title, "wc-sellerledger" ),
        "type"  => "title",
        "desc"  => __( $description, "wc-sellerledger" ),
      );
    }

    public static function get_api_token_field( $hidden = false ) {
      $cid = SellerLedger()->connection->getConnectionID();
      return array(
        "title"   => ( $hidden ? "" : "API Token" ),
        "type"    => "text",
        "desc"    => $cid ? "Your connection ID is " . $cid . "." : "",
        "default" => "",
        "class"   => ( $hidden ? "hidden" : ""),
        "id"      => "woocommerce_sellerledger-integration_settings[api_token]",
      );
    }

    public static function get_section_split() {
      return array(
        "type" => "sectionend",
      );
    }

    public static function get_settings_attributes() {
      $settings = array();
      $token = new WC_SellerLedger_Token( self::all()[ "api_token" ] ?? null );
      $connection = new WC_SellerLedger_Connection( $token );

      if ( $token->invalid() ) {
        array_push( $settings, self::get_title( "Seller Ledger", "Please enter your API token to active the Seller Ledger plugin." ) );
        array_push( $settings, self::get_section_split() );
        array_push( $settings, self::get_api_token_field() );
      } elseif ( $connection->invalid() ) {
        array_push( $settings, self::get_title( "Seller Ledger", "You have entered a token that appears to be invalid. Please verify it is correct." ) );
        array_push( $settings, self::get_section_split() );
        array_push( $settings, self::get_api_token_field() );
      } else {
        array_push( $settings, self::get_title( "Seller Ledger", "Congrats! Your are connected to Seller Ledger." ) );
        array_push( $settings, self::get_section_split() );
        array_push( $settings, self::get_api_token_field() );
      }

      return $settings;
    }

    public static function output_settings_page() {
      global $current_section;
      global $hide_save_button;

      if ( $current_section == "" ) {
        $settings = self::get_settings_attributes();
        WC_Admin_Settings::output_fields( $settings );
        wp_nonce_field( "sellerledger_settings" );
      } elseif ( $current_section == "transaction_backfill" ) {
        $hide_save_button = true;
        $backfill = new WC_SellerLedger_Settings_Backfill( SellerLedger()->business );
        $backfill->print();
      } elseif ( $current_section == "queue" ) {
        $hide_save_button = true;
        $queue = new WC_SellerLedger_Settings_Queue();
        $queue->print();
      }
    }
  }

endif;
