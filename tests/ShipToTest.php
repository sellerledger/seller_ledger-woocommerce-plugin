<?php

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

class SL_Ship_To_Order {
	private $props;

	public function __construct( $props ) {
		$this->props = $props;
	}

	public function get_shipping_methods() {
		return array();
	}

	public function __call( $name, $args ) {
		return $this->props[ substr( $name, 4 ) ] ?? '';
	}
}

class ShipToTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$countries = new class() {
			public function get_base_country() {
				return 'US';
			}

			public function get_base_state() {
				return 'CT';
			}

			public function get_base_postcode() {
				return '06103';
			}
		};
		Functions\when( 'WC' )->justReturn( (object) array( 'countries' => $countries ) );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_uses_the_shipping_address() {
		$order = new SL_Ship_To_Order( array( 'shipping_country' => 'US', 'shipping_state' => 'CA', 'shipping_postcode' => '94110', 'billing_country' => 'US', 'billing_state' => 'NY' ) );

		$this->assertSame( array( 'country' => 'US', 'state' => 'CA', 'zip' => '94110' ), SellerLedger_Transaction::ship_to( $order ) );
	}

	public function test_falls_back_to_a_billing_address_without_a_state() {
		$order = new SL_Ship_To_Order( array( 'billing_country' => 'GB', 'billing_postcode' => 'SW1A 1AA' ) );

		$this->assertSame( array( 'country' => 'GB', 'state' => '', 'zip' => 'SW1A 1AA' ), SellerLedger_Transaction::ship_to( $order ) );
	}

	public function test_uses_the_store_address_when_the_order_has_none() {
		$order = new SL_Ship_To_Order( array() );

		$this->assertSame( array( 'country' => 'US', 'state' => 'CT', 'zip' => '06103' ), SellerLedger_Transaction::ship_to( $order ) );
	}
}
