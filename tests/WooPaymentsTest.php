<?php

use PHPUnit\Framework\TestCase;
use Brain\Monkey;

class WooPaymentsTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_maps_a_payout_from_minor_units_and_milliseconds() {
		$payout = SellerLedger_WooPayments::map_payout(
			array(
				'id'       => 'po_1',
				'amount'   => 5013,
				'currency' => 'usd',
				'date'     => 1763078400000,
				'status'   => 'paid',
			)
		);

		$this->assertSame(
			array(
				'payout_id'     => 'po_1',
				'amount'        => 50.13,
				'currency_code' => 'USD',
				'paid_out_at'   => '2025-11-14T00:00:00+00:00',
				'status'        => 'paid',
			),
			$payout
		);
	}

	public function test_maps_a_charge_with_its_fee_and_intent() {
		$transaction = SellerLedger_WooPayments::map_transaction(
			array(
				'transaction_id'   => 'txn_1',
				'payment_id'       => 'pi_1',
				'type'             => 'charge',
				'amount'           => 5400,
				'fees'             => 187,
				'net_amount'       => 5213,
				'deposit_currency' => 'usd',
				'date'             => '2026-11-10 12:00:00',
				'order_id'         => 24,
			),
			'USD'
		);

		$this->assertSame( 'charge', $transaction['type'] );
		$this->assertSame( 54.0, $transaction['amount'] );
		$this->assertSame( 1.87, $transaction['fee'] );
		$this->assertSame( 52.13, $transaction['net'] );
		$this->assertSame( 'pi_1', $transaction['processor_payment_id'] );
		$this->assertSame( '24', $transaction['order_reference'] );
		$this->assertSame( '2026-11-10T12:00:00+00:00', $transaction['created_at'] );
		$this->assertArrayNotHasKey( 'processor_charge_id', $transaction );
		$this->assertArrayNotHasKey( 'description', $transaction );
	}

	public function test_maps_an_unknown_type_to_an_adjustment_that_names_it() {
		$transaction = SellerLedger_WooPayments::map_transaction(
			array(
				'transaction_id' => 'txn_2',
				'type'           => 'financing_payout',
				'amount'         => 10000,
				'fees'           => 0,
				'net_amount'     => 10000,
				'date'           => '2026-11-10 12:00:00',
			),
			'USD'
		);

		$this->assertSame( 'adjustment', $transaction['type'] );
		$this->assertSame( 'financing_payout', $transaction['description'] );
		$this->assertSame( 'USD', $transaction['currency_code'] );
	}

	public function test_keeps_zero_decimal_currencies_whole() {
		$this->assertSame( 5400.0, SellerLedger_WooPayments::to_major( 5400, 'jpy' ) );
		$this->assertSame( 54.0, SellerLedger_WooPayments::to_major( 5400, 'USD' ) );
	}
}
