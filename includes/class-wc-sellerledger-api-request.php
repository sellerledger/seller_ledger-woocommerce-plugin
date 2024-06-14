<?php

if ( ! defined( "ABSPATH" ) ) {
	exit;
}

class WC_SellerLedger_API_Request {

  private $ua;
  private $api_token;
  private $endpoint;
  private $request_type;
  private $request_body;
  private $content_type;

  public static $base_url = 'https://api.sellerledger.com/v1/';

  public function __construct( $token, $content_type = "application/json" ) {
    $this->set_api_token( $token );
    $this->set_user_agent( self::create_ua_header() );
    $this->set_content_type( $content_type );
  }

  public function get($endpoint, $body = null) {
    $this->set_request_type( "get" );
    $this->set_endpoint( $endpoint );
    $this->set_request_body( $body );
    return new WC_SellerLedger_API_Response($this->send_get_request());
  }

  public function post($endpoint, $body) {
    $this->set_request_type( "post" );
    $this->set_endpoint( $endpoint );
    $this->set_request_body( $body );
    return new WC_SellerLedger_API_Response($this->send_post_request());
  }

  public function put($endpoint, $body) {
    $this->set_request_type( "put" );
    $this->set_endpoint( $endpoint );
    $this->set_request_body( $body );
    return new WC_SellerLedger_API_Response($this->send_put_request());
  }

  public function delete($endpoint) {
    $this->set_request_type( "delete" );
    $this->set_endpoint( $endpoint );
    return new WC_SellerLedger_API_Response($this->send_delete_request());
  }

  public function get_request_args() {
    $request_args = array(
      "headers"    => array(
      "Authorization" => "Bearer " . $this->get_api_token(),
      "Content-Type"  => $this->get_content_type()
    ),
    "user-agent" => $this->get_user_agent()
    );

    if ( $this->get_request_type() === "put" ) {
      $request_args[ "method" ] = "PUT";
    }

    if ( $this->get_request_type() === "delete" ) {
      $request_args[ "method" ] = "DELETE";
    }

    if ( !empty( $this->get_request_body() ) ) {
      $request_args[ "body" ] = $this->get_request_body();
    }

    return $request_args;
  }

  public function send_request() {
    switch( $this->get_request_type() ) {
    case "get":
      return $this->send_get_request();
      break;
    case "put":
      return $this->send_put_request();
      break;
    case "delete":
      return $this->send_delete_request();
      break;
    default:
      return $this->send_post_request();
    }
  }

  public function send_post_request() {
    $url = $this->get_full_url();
    return wp_remote_post( $url, $this->get_request_args() );
  }

  public function send_get_request() {
    $url = $this->get_full_url();
    return wp_remote_get( $url, $this->get_request_args() );
  }

  public function send_put_request() {
    $url = $this->get_full_url();
    return wp_remote_request( $url, $this->get_request_args() );
  }

  public function send_delete_request() {
    $url = $this->get_full_url();
    return wp_remote_request( $url, $this->get_request_args() );
  }

  static function create_ua_header() {
  $curl_version = "";
  if ( function_exists( "curl_version" ) ) {
    $curl_version = curl_version();
    $curl_version = $curl_version["version"] . "; " . $curl_version["ssl_version"];
  }

  $php_version       = phpversion();
  $sellerledger_version    = WC_SellerLedger::$version;
  $woo_version       = WC()->version;
  $wordpress_version = get_bloginfo( "version" );
  $site_url          = get_bloginfo( "url" );
  $user_agent        = "SellerLedger/WooCommerce (PHP $php_version; cURL $curl_version; WordPress $wordpress_version; WooCommerce $woo_version) WC_SellerLedger/$sellerledger_version $site_url";
  return $user_agent;
  }

  public function get_full_url() {
    return self::$base_url . $this->endpoint;
  }

  public function get_request_body() {
    return $this->request_body;
  }

  public function set_request_body( $body ) {
    $this->request_body = $body;
  }

  public function get_api_token() {
    return $this->api_token;
  }

  public function set_api_token( $token ) {
  $this->api_token = $token->get();
  }

  public function get_user_agent() {
    return $this->ua;
  }

  public function set_user_agent( $user_agent ) {
    $this->ua = $user_agent;
  }

  public function get_request_type() {
    return $this->request_type;
  }

  public function set_request_type( $type ) {
    $this->request_type = $type;
  }

  public function get_endpoint() {
    return $this->endpoint;
  }

  public function set_endpoint( $endpoint ) {
    $this->endpoint = $endpoint;
  }

  public function get_content_type() {
    return $this->content_type;
  }

  public function set_content_type( $content_type ) {
    $this->content_type = $content_type;
  }

}
