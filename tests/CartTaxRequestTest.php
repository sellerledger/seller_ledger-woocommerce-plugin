<?php

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

class SL_Test_Product {
	private $name;
	private $sku;

	public function __construct( $name, $sku ) {
		$this->name = $name;
		$this->sku  = $sku;
	}

	public function get_name() {
		return $this->name;
	}

	public function get_sku() {
		return $this->sku;
	}
}

class SL_Test_Cart {
	public $items;
	public $fees;
	public $subtotal;
	public $shipping;
	public $discount;

	public function __construct( $items, $fees = array(), $subtotal = 0, $shipping = 0, $discount = 0 ) {
		$this->items    = $items;
		$this->fees     = $fees;
		$this->subtotal = $subtotal;
		$this->shipping = $shipping;
		$this->discount = $discount;
	}

	public function get_cart() {
		return $this->items;
	}

	public function get_fees() {
		return $this->fees;
	}

	public function get_subtotal() {
		return $this->subtotal;
	}

	public function get_shipping_total() {
		return $this->shipping;
	}

	public function get_discount_total() {
		return $this->discount;
	}
}

class SL_Test_Customer {
	public $fields;

	public function __construct( $fields ) {
		$this->fields = $fields;
	}

	public function get_shipping_country() {
		return $this->fields['ship_country'] ?? '';
	}

	public function get_shipping_state() {
		return $this->fields['ship_state'] ?? '';
	}

	public function get_shipping_postcode() {
		return $this->fields['ship_zip'] ?? '';
	}

	public function get_shipping_city() {
		return $this->fields['ship_city'] ?? '';
	}

	public function get_billing_country() {
		return $this->fields['bill_country'] ?? '';
	}

	public function get_billing_state() {
		return $this->fields['bill_state'] ?? '';
	}

	public function get_billing_postcode() {
		return $this->fields['bill_zip'] ?? '';
	}

	public function get_billing_city() {
		return $this->fields['bill_city'] ?? '';
	}
}

class CartTaxRequestTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'get_woocommerce_currency' )->justReturn( 'USD' );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	private function line( $name, $sku, $qty, $subtotal, $total ) {
		return array(
			'data'          => new SL_Test_Product( $name, $sku ),
			'quantity'      => $qty,
			'line_subtotal' => $subtotal,
			'line_total'    => $total,
		);
	}

	private function customer() {
		return new SL_Test_Customer(
			array(
				'ship_country' => 'US',
				'ship_state'   => 'TX',
				'ship_zip'     => '78701',
				'ship_city'    => 'Austin',
			)
		);
	}

	public function test_build_params_emits_the_documented_key_set() {
		$params = WC_SellerLedger_Cart_Tax_Request::build_params(
			array(
				'ship_to_country_code' => 'US',
				'ship_to_state'        => 'TX',
				'ship_to_zip'          => '78701',
				'ship_to_city'         => 'Austin',
			),
			array(),
			array(
				'transaction_date' => '2026-01-01',
				'currency_code'    => 'USD',
				'items_subtotal'   => 100.0,
				'shipping_amount'  => 10.0,
				'discount_amount'  => 0.0,
				'total_amount'     => 110.0,
			)
		);

		$this->assertSame(
			WC_SellerLedger_Cart_Tax_Request::PARAM_KEYS,
			array_keys( $params )
		);
		$this->assertSame( 0, $params['tax_amount'] );
		$this->assertFalse( $params['marketplace_remitted_tax'] );
	}

	public function test_to_params_maps_cart_and_customer() {
		$cart = new SL_Test_Cart(
			array( $this->line( 'Widget', 'W-1', 2, 100.0, 90.0 ) ),
			array(),
			100.0,
			10.0,
			10.0
		);

		$request = WC_SellerLedger_Cart_Tax_Request::from_cart( $cart, $this->customer() );
		$params  = $request->to_params();

		$this->assertSame( 'US', $params['ship_to_country_code'] );
		$this->assertSame( 'TX', $params['ship_to_state'] );
		$this->assertSame( '78701', $params['ship_to_zip'] );
		$this->assertSame( 100.0, $params['items_subtotal'] );
		$this->assertSame( 10.0, $params['shipping_amount'] );
		$this->assertSame( 100.0, $params['total_amount'] );

		$this->assertCount( 1, $params['items'] );
		$this->assertSame( 'Widget', $params['items'][0]['product_name'] );
		$this->assertSame( 2, $params['items'][0]['quantity'] );
		$this->assertSame( 100.0, $params['items'][0]['item_amount'] );
		$this->assertSame( 90.0, $params['items'][0]['total_amount'] );
	}

	public function test_fees_are_included_in_subtotal_and_items() {
		$fee         = new stdClass();
		$fee->name   = 'Handling';
		$fee->amount = 5.0;
		$fee->total  = 5.0;

		$cart = new SL_Test_Cart(
			array( $this->line( 'Widget', 'W-1', 1, 100.0, 100.0 ) ),
			array( $fee ),
			100.0,
			0.0,
			0.0
		);

		$params = WC_SellerLedger_Cart_Tax_Request::from_cart( $cart, $this->customer() )->to_params();

		$this->assertSame( 105.0, $params['items_subtotal'] );
		$this->assertCount( 2, $params['items'] );
		$this->assertSame( 'Handling', $params['items'][1]['product_name'] );
	}

	public function test_is_calculable_requires_us_state_zip_and_items() {
		$cart = new SL_Test_Cart( array( $this->line( 'Widget', 'W-1', 1, 100.0, 100.0 ) ), array(), 100.0 );
		$this->assertTrue( WC_SellerLedger_Cart_Tax_Request::from_cart( $cart, $this->customer() )->is_calculable() );

		$empty_cart = new SL_Test_Cart( array(), array(), 0.0 );
		$this->assertFalse( WC_SellerLedger_Cart_Tax_Request::from_cart( $empty_cart, $this->customer() )->is_calculable() );

		$no_zip = new SL_Test_Customer( array( 'ship_country' => 'US', 'ship_state' => 'TX', 'ship_zip' => '' ) );
		$this->assertFalse( WC_SellerLedger_Cart_Tax_Request::from_cart( $cart, $no_zip )->is_calculable() );

		$ca = new SL_Test_Customer( array( 'ship_country' => 'CA', 'ship_state' => 'ON', 'ship_zip' => 'M5V' ) );
		$this->assertFalse( WC_SellerLedger_Cart_Tax_Request::from_cart( $cart, $ca )->is_calculable() );
	}

	public function test_is_calculable_falls_back_to_billing_address() {
		$cart     = new SL_Test_Cart( array( $this->line( 'Widget', 'W-1', 1, 100.0, 100.0 ) ), array(), 100.0 );
		$customer = new SL_Test_Customer(
			array(
				'bill_country' => 'US',
				'bill_state'   => 'TX',
				'bill_zip'     => '78701',
			)
		);

		$this->assertTrue( WC_SellerLedger_Cart_Tax_Request::from_cart( $cart, $customer )->is_calculable() );
	}

	public function test_cache_key_is_stable_regardless_of_item_order() {
		$customer = $this->customer();

		$cart_a = new SL_Test_Cart(
			array(
				$this->line( 'Widget', 'W-1', 1, 100.0, 100.0 ),
				$this->line( 'Gadget', 'G-1', 2, 50.0, 50.0 ),
			),
			array(),
			150.0
		);

		$cart_b = new SL_Test_Cart(
			array(
				$this->line( 'Gadget', 'G-1', 2, 50.0, 50.0 ),
				$this->line( 'Widget', 'W-1', 1, 100.0, 100.0 ),
			),
			array(),
			150.0
		);

		$key_a = WC_SellerLedger_Cart_Tax_Request::from_cart( $cart_a, $customer )->cache_key();
		$key_b = WC_SellerLedger_Cart_Tax_Request::from_cart( $cart_b, $customer )->cache_key();

		$this->assertSame( $key_a, $key_b );
	}

	public function test_cache_key_changes_with_address() {
		$cart = new SL_Test_Cart( array( $this->line( 'Widget', 'W-1', 1, 100.0, 100.0 ) ), array(), 100.0 );

		$tx = WC_SellerLedger_Cart_Tax_Request::from_cart( $cart, $this->customer() )->cache_key();

		$other_customer = new SL_Test_Customer(
			array(
				'ship_country' => 'US',
				'ship_state'   => 'CA',
				'ship_zip'     => '90001',
			)
		);
		$ca = WC_SellerLedger_Cart_Tax_Request::from_cart( $cart, $other_customer )->cache_key();

		$this->assertNotSame( $tx, $ca );
	}
}
