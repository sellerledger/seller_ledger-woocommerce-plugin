<?php

if ( ! class_exists( 'WC_SellerLedger_Settings' ) ) :

  class WC_SellerLedger_Settings {

    public static $id = 'sellerledger-integration';

    public static function init() {
      add_filter( 'woocommerce_settings_tabs_array', array( __CLASS__, 'add_settings_tab' ), 50 );
      add_action( 'woocommerce_sections_' . self::$id, array( __CLASS__, 'output_sections' ) );
      add_action( 'woocommerce_settings_' . self::$id, array( __CLASS__, 'output_settings_page' ) );
      add_action( 'woocommerce_settings_save_' . self::$id, array( __CLASS__, 'save' ) );
    }

    public static function get_stored_settings_identifier() {
      return 'woocommerce_' . self::$id . '_settings';
    }

    public static function add_settings_tab( $settings_tabs ) {
      $settings_tabs[ self::$id ] = __( 'Seller Ledger', 'sellerledger' );
      return $settings_tabs;
    }

    public static function save() {
      $settings = self::get_settings();
      WC_Admin_Settings::save_fields( $settings );
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
        $section_relative_url = 'admin.php?page=wc-settings&tab=' . self::$id . '&section=' . sanitize_title( $id );
        $section_html_string  = '<li><a href="';
        $section_html_string .= admin_url( $section_relative_url );
        $section_html_string .= '" class="' . ( $current_section === $id ? 'current' : '' ) . '">';
        $section_html_string .= $label . '</a> ' . ( end( $array_keys ) === $id ? '' : '|' ) . ' </li>';
        echo wp_kses_post( $section_html_string );
      }

      echo '</ul><br class="clear" />';
    }

    public static function get_sections() {
      $sections = array(
        ''                     => __( 'Settings', 'woocommerce' ),
        'transaction_backfill' => __( 'Transaction Backfill', 'wc-sellerledger' ),
      );
      return apply_filters( 'woocommerce_get_sections_' . self::$id, $sections );
    }

    public static function get_connect_to_sellerledger_setting() {
      return array(
      'title'   => 'Seller Ledger API Token',
      'type'    => 'text',
      'desc'    => '<p class="hidden sl-api-token-title"><a href="' . WC_SellerLedger_Integration::$app_uri . 'account#api-access" target="_blank">' . __( 'Get API token', 'wc-sellerledger' ) . '<a></p>',
      'default' => '',
      'class'   => '',
      'id'      => 'woocommerce_sellerledger-integration_settings[api_token]',
      );
    }

    public static function get_sync_button_setting() {
      return array(
        'title' => '',
        'type'  => 'title',
        'desc'  => '<button id="sellerledger-sync" name="sellerledger-sync" class="button-primary" type="submit" value="Sync Transactions">' . __( 'Sync Transactions To Seller Ledger', 'wc-sellerledger' ) . '</button>'
      );
    }

    public static function get_title_settings() {
      return array(
        array(
          'title' => __( 'Seller Ledger', 'wc-sellerledger' ),
          'type'  => 'title',
          'desc'  => __( 'Automated bookkeeping software for eCommerce sellers', 'wc-sellerledger' ),
        ),
        self::get_section_split(),
        array(
          'title' => __( 'Step 1: Activate your Seller Ledger WooCommerce Plugin', 'wc-sellerledger' ),
          'type'  => 'title',
          'desc'  => __( 'Enter your API token to activate the plugin', 'wc-sellerledger' ),
        ),
        self::get_section_split()
      );
    }

    public static function get_section_split() {
      return array(
        'type' => 'sectionend',
      );
    }

    public static function get_stored_settings() {
      return WC_Admin_Settings::get_option( self::get_stored_settings_identifier() );
    }

    public static function get_settings() {
      $settings = array();
      $connection = new WC_SellerLedger_Connection();
      $settings = self::get_title_settings();
      global $hide_save_button;

      array_push( $settings, self::get_connect_to_sellerledger_setting() );

      if ( $connection->validToken() ) {
        $hide_save_button = true;
        array_push( $settings, self::get_section_split() );
        array_push( $settings, self::get_sync_button_setting() );
      }

      return $settings;
    }

    public static function get_setting( $key ) {
      $settings = self::get_stored_settings();
      return $settings[ $key ];
    }

    public static function output_settings_page() {
      global $current_section;
      global $hide_save_button;

      if ( '' === $current_section ) {
        $settings = self::get_settings();
        WC_Admin_Settings::output_fields( $settings );
        wp_nonce_field( 'sellerledger_settings' );
      } elseif ( 'transaction_backfill' === $current_section ) {
        $hide_save_button = true;
        ?>
        <p>Please enable the Seller Ledger plugin to initiate transaction backfill.</p>
        <?php
      }
    }

  }

endif;
