<?php
if ( ! defined( "ABSPATH" ) ) {
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

  public static function destroy() {
    delete_option( self::CONNECTION_ID_OPTION );
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
    if ( $this->token->invalid() ) {
      return false;
    }

    if ( !is_null( $this->getConnectionID() ) && $this->getConnectionID() != "" ) {
      return false;
    }

    if ( get_transient("sellerledger_creating_connection") == "yes" ) {
      return false;
    }

    set_transient("sellerledger_creating_connection", "yes", 600);

    $details = array(
      "name" => html_entity_decode(get_bloginfo("name")),
      "type" => "asset"
    );

    try {
      $client = SellerLedger\Client::withApiKey( $this->token->get() );
      $response = $client->createConnection($details);
      $this->setConnectionID($response->id);
    } catch ( SellerLedger\Exception $e ) {
      SellerLedger()->log( "ERROR CREATING SELLERLEDGER CONNECTION" );
    }

    delete_transient("sellerledger_creating_connection");
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

    try {
      $client = SellerLedger\Client::withApiKey( $this->token->get() );
      $response = $client->getBusiness();
      return true;
    } catch ( SellerLedger\Exception $e ) {
      return false;
    }
  }
}
