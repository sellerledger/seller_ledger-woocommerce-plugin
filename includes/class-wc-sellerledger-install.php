<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_SellerLedger_Install
{
  public static function init() {
    add_action( 'init' , array( __CLASS__, 'install' ) );
  }

  public static function install() {
    if ( defined( 'IFRAME_REQUEST' ) ) {
      return;
    }

    $version = get_option( 'sellerledger_version' );

    if ( version_compare( $version, WC_SellerLedger::$version, '<' ) ) {
      if ( get_transient( 'sellerledger_installing' ) == 'yes' ) {
        return;
      }

      set_transient( 'sellerledger_installing', 'yes', 600 );

      global $wpdb;
      $wpdb->hide_errors();
      require_once ABSPATH . 'wp-admin/includes/upgrade.php';
      $result = dbDelta( self::tableSQL() );

      delete_option( 'sellerledger_version' );
      add_option( 'sellerledger_version', WC_SellerLedger::$version );

      delete_transient( 'sellerledger_installing' );
    }
  }

  private static function tableSQL() {
    global $wpdb;
    $collate = '';

    if ( $wpdb->has_cap( 'collation' ) ) {
      $collate = $wpdb->get_charset_collate();
    }

    $sql = "CREATE TABLE {$wpdb->prefix}sellerledger_queue (
  id bigint UNSIGNED NOT NULL auto_increment,
  record_id BIGINT UNSIGNED NOT NULL,
  record_type VARCHAR(100) NOT NULL,
  status VARCHAR(100) NOT NULL DEFAULT 'new',
  created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
  updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
  retry_count SMALLINT(4) NOT NULL DEFAULT 0,
  last_error TEXT NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  KEY record_id (record_id)
) $collate;";

    return $sql;
  }
}

WC_SellerLedger_Install::init();
