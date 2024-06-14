<?php
if ( ! defined( "ABSPATH" ) ) {
  exit; // Exit if accessed directly.
}

if ( ! class_exists( "WC_SellerLedger_Integration" ) ) :

  class WC_SellerLedger_Integration {
    protected static $_instance = null;
    public $id;
    public $settings;
    public $token;
    public $connection;
    public $business;
    public $transaction_sync;

    public static function instance() {
      if ( is_null( self::$_instance ) ) {
        self::$_instance = new self();
        self::$_instance->init();
      }
      return self::$_instance;
    }

    public static function log( $data ) {
      if ( is_array( $data ) || is_object( $data ) ) {
        error_log( print_r( $data, true ) );
      } else {
        error_log( $data );
      }
    }

    public function __construct() {
      $this->id = "sellerledger-integration";
    }

    public function init() {
      $this->settings = WC_SellerLedger_Settings::init();
      $this->token = WC_SellerLedger_Token::init( $this->settings::api_token() );
      $this->connection = WC_SellerLedger_Connection::init( $this->token );
      $this->business = WC_SellerLedger_Business::init( $this->token );
      $this->transaction_sync = WC_SellerLedger_Transaction_Sync::init( $this );

      if ( is_admin() ) {
        add_action( "admin_enqueue_scripts", array( $this, "load_admin_assets" ) );
      }
    }

    public function load_admin_assets() {
      wp_register_script( "wc-sellerledger-admin", plugin_dir_url( __FILE__ ) . "/js/wc-sellerledger-admin.js" );

      wp_localize_script(
        "wc-sellerledger-admin",
        "woocommerce_sellerledger_admin",
        array(
          "ajax_url"                   => admin_url( "admin-ajax.php" ),
          "transaction_sync_nonce"     => wp_create_nonce( "sellerledger-transaction-sync" ),
          "activate_plugin_nonce"      => wp_create_nonce( "sellerledger-activate-plugin" ),
          "current_user"               => get_current_user_id()
        )
      );

      wp_enqueue_script( "wc-sellerledger-admin", array( "jquery" ) );
    }

    public function active() {
      return $this->token->valid() && $this->connection->valid();
    }
  }

endif;
