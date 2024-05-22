<?php

if ( ! class_exists( 'WC_SellerLedger_Integration' ) ) :

  class WC_SellerLedger_Integration {
    protected static $_instance = null;
    public static $app_uri = 'https://app.sellerledger.com/';
    public $id;

    public static function instance() {
      if ( is_null( self::$_instance ) ) {
        self::$_instance = new self();
      }
      return self::$_instance;
    }

    public function __construct() {
      $this->id = 'sellerledger-integration';
      WC_SellerLedger_Settings::init();

      if ( is_admin() ) {
        add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_assets' ) );
      }
    }

    public function settings() {
      return WC_SellerLedger_Settings::all();
    }

    public function activate() {
      # $settings = new WC_SellerLedger_Settings();
      # $settings.save();

      # $connection = new WC_SellerLedger_Connection();
      # $connection_id = $connection.create();

      return true;
    }

    public function activated() {
      return true;
    }

    public function load_admin_assets() {
      wp_register_script( 'wc-sellerledger-admin', plugin_dir_url( __FILE__ ) . '/js/wc-sellerledger-admin.js' );

      wp_localize_script(
        'wc-sellerledger-admin',
        'woocommerce_sellerledger_admin',
        array(
          'ajax_url'                   => admin_url( 'admin-ajax.php' ),
          'transaction_sync_nonce'     => wp_create_nonce( 'sellerledger-transaction-sync' ),
          'activate_plugin_nonce'     => wp_create_nonce( 'sellerledger-activate-plugin' ),
          'current_user'               => get_current_user_id(),
          'app_url'                    => untrailingslashit( self::$app_uri ),
        )
      );

      wp_enqueue_script( 'wc-sellerledger-admin', array( 'jquery' ) );
    }
  }

endif;
