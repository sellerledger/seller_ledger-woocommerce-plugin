<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_SellerLedger_Connection {
  private $token;
  private $connection_id;

  const CONNECTION_ID_OPTION = "sellerledger-connection-id";

  public static function init( $token ) {
    $instance = new self( $token );
    $instance->create();
    return $instance;
  }

  public function __construct( $token ) {
    $this->token = $token;
  }

  public function getConnectionID() {
    if ( is_null( $this->connection_id ) ) {
      $this->connection_id = get_option( self::CONNECTION_ID_OPTION );
    }

    return $this->connection_id;
  }

  public function setConnectionID( $id ) {
    add_option( self::CONNECTION_ID_OPTION, $id );
    $this->connection_id = $id;
    return $id;
  }

  public function create() {
    if ( !is_null( $this->getConnectionID() ) && $this->getConnectionID() != "" ) {
      return false;
    }

    $details = array(
      'name' => urldecode(get_bloginfo('name')),
      'type' => "asset"
    );

    $request = $this->newRequest();
    $response = $request->post("connections", json_encode($details));

    if ( $response->success() ) {
      $this->setConnectionID($response->body()->connection->id);
    } else {
      error_log(print_r($request, true));
    }
  }

  public function valid() {
    return !$this->invalid();
  }

  public function invalid() {
    if ( is_null( $this->getConnectionID() ) ) {
      return true;
    }

    $result = $this->verify();
    return !$result;
  }

  private function verify() {
    if ( is_null( $this->getConnectionID() ) || $this->getConnectionID() == "" ) {
      return false;
    }

    $request = $this->newRequest();
    $response = $request->get("business" . $this->connection_id);
    return $response->success();
  }

  private function newRequest() {
    return new WC_SellerLedger_API_Request( $this->token );
  }
}
