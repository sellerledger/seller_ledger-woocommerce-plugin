<?php

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

class TaxCalculatorTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'get_option' )->justReturn( 7 );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	private function calculator() {
		return new SellerLedger_Tax_Calculator( new stdClass() );
	}

	private function set( $calculator, $prop, $value ) {
		$ref = new ReflectionProperty( SellerLedger_Tax_Calculator::class, $prop );
		$ref->setAccessible( true );
		$ref->setValue( $calculator, $value );
	}

	private function nexus_result( $rate, $freight_taxable = true ) {
		$result                  = new stdClass();
		$result->has_nexus       = true;
		$result->rate            = $rate;
		$result->freight_taxable = $freight_taxable;
		return $result;
	}

	private $native = array( 'native' => array( 'rate' => '6.0000' ) );

	public function test_passthrough_when_not_primed() {
		$calc = $this->calculator();
		$this->assertSame( $this->native, $calc->inject_rate( $this->native ) );
	}

	public function test_no_tax_when_primed_but_not_collecting() {
		$calc = $this->calculator();
		$this->set( $calc, 'primed', true );
		$this->set( $calc, 'collecting', false );

		$this->assertSame( array(), $calc->inject_rate( $this->native ) );
	}

	public function test_passthrough_when_api_failed_in_a_nexus_state() {
		$calc = $this->calculator();
		$this->set( $calc, 'primed', true );
		$this->set( $calc, 'collecting', true );
		$this->set( $calc, 'api_failed', true );
		$this->assertSame( $this->native, $calc->inject_rate( $this->native ) );
	}

	public function test_no_tax_when_result_null() {
		$calc = $this->calculator();
		$this->set( $calc, 'primed', true );
		$this->set( $calc, 'collecting', true );
		$this->set( $calc, 'result', null );
		$this->assertSame( array(), $calc->inject_rate( $this->native ) );
	}

	public function test_no_tax_when_no_nexus() {
		$calc   = $this->calculator();
		$result = new stdClass();
		$result->has_nexus = false;
		$this->set( $calc, 'primed', true );
		$this->set( $calc, 'collecting', true );
		$this->set( $calc, 'result', $result );

		$this->assertSame( array(), $calc->inject_rate( $this->native ) );
	}

	public function test_injects_single_rate_when_nexus() {
		$calc = $this->calculator();
		$this->set( $calc, 'primed', true );
		$this->set( $calc, 'collecting', true );
		$this->set( $calc, 'result', $this->nexus_result( 0.0825, true ) );

		$rates = $calc->inject_rate( $this->native );

		$this->assertArrayHasKey( 7, $rates );
		$this->assertSame( '8.25', $rates[7]['rate'] );
		$this->assertSame( 'yes', $rates[7]['shipping'] );
		$this->assertSame( 'no', $rates[7]['compound'] );
	}

	public function test_shipping_not_taxable_when_freight_not_taxable() {
		$calc = $this->calculator();
		$this->set( $calc, 'primed', true );
		$this->set( $calc, 'collecting', true );
		$this->set( $calc, 'result', $this->nexus_result( 0.05, false ) );

		$rates = $calc->inject_rate( $this->native );

		$this->assertSame( 'no', $rates[7]['shipping'] );
	}
}
