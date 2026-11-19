<?php

use PHPUnit\Framework\TestCase;
use Brain\Monkey;

class SL_Test_Order {
	public $props;
	public $meta;

	public function __construct( $props, $meta = array() ) {
		$this->props = $props;
		$this->meta  = $meta;
	}

	public function __call( $name, $args ) {
		$key = substr( $name, 4 );
		return $this->props[ $key ] ?? null;
	}

	public function get_meta( $key ) {
		return $this->meta[ $key ] ?? '';
	}
}

class PaymentsTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_maps_gateway_ids_to_processor_keys() {
		$this->assertSame( 'stripe', SellerLedger_Payments::processor_for( 'stripe' ) );
		$this->assertSame( 'stripe', SellerLedger_Payments::processor_for( 'stripe_klarna' ) );
		$this->assertSame( 'woopayments', SellerLedger_Payments::processor_for( 'woocommerce_payments' ) );
		$this->assertSame( 'woopayments', SellerLedger_Payments::processor_for( 'woocommerce_payments_affirm' ) );
		$this->assertSame( 'paypal', SellerLedger_Payments::processor_for( 'ppcp-gateway' ) );
		$this->assertSame( 'square', SellerLedger_Payments::processor_for( 'square_credit_card' ) );
		$this->assertSame( 'cash', SellerLedger_Payments::processor_for( 'cod' ) );
		$this->assertSame( 'check', SellerLedger_Payments::processor_for( 'cheque' ) );
		$this->assertSame( 'bank_deposit', SellerLedger_Payments::processor_for( 'bacs' ) );
		$this->assertSame( 'manual', SellerLedger_Payments::processor_for( '' ) );
		$this->assertSame( 'some_gateway', SellerLedger_Payments::processor_for( 'some_gateway' ) );
	}

	public function test_does_not_treat_a_lookalike_id_as_a_prefix_match() {
		$this->assertSame( 'stripey', SellerLedger_Payments::processor_for( 'stripey' ) );
	}

	public function test_stripe_order_sends_its_charge_and_intent() {
		$order = $this->paid_order( 'stripe', 'ch_3Q1', array( '_stripe_intent_id' => 'pi_3Q1' ) );

		$payment = SellerLedger_Payments::for_order( $order )[0];

		$this->assertSame( '24-payment', $payment['transaction_id'] );
		$this->assertSame( 'stripe', $payment['processor'] );
		$this->assertSame( 54.0, $payment['amount'] );
		$this->assertSame( 'ch_3Q1', $payment['processor_charge_id'] );
		$this->assertSame( 'pi_3Q1', $payment['processor_payment_id'] );
		$this->assertSame( '2026-11-18T22:06:39+00:00', $payment['paid_at'] );
	}

	public function test_woopayments_order_sends_its_intent_as_the_payment_and_its_charge() {
		$order = $this->paid_order( 'woocommerce_payments', 'pi_3Q2', array( '_charge_id' => 'ch_3Q2' ) );

		$payment = SellerLedger_Payments::for_order( $order )[0];

		$this->assertSame( 'woopayments', $payment['processor'] );
		$this->assertSame( 'pi_3Q2', $payment['processor_payment_id'] );
		$this->assertSame( 'ch_3Q2', $payment['processor_charge_id'] );
	}

	public function test_cash_order_sends_no_processor_ids() {
		$order = $this->paid_order( 'cod', '', array() );

		$payment = SellerLedger_Payments::for_order( $order )[0];

		$this->assertSame( 'cash', $payment['processor'] );
		$this->assertArrayNotHasKey( 'processor_charge_id', $payment );
		$this->assertArrayNotHasKey( 'processor_payment_id', $payment );
	}

	public function test_unpaid_order_sends_no_payments() {
		$order = new SL_Test_Order( array( 'date_paid' => null ) );

		$this->assertSame( array(), SellerLedger_Payments::for_order( $order ) );
	}

	public function test_refund_goes_back_through_the_order_gateway() {
		$parent = $this->paid_order( 'stripe', 'ch_3Q1', array( '_stripe_intent_id' => 'pi_3Q1' ) );
		$refund = new SL_Test_Order(
			array(
				'id'           => 28,
				'amount'       => '20.00',
				'currency'     => 'USD',
				'date_created' => new DateTime( '2026-11-19T10:00:00+00:00' ),
			)
		);

		$payment = SellerLedger_Payments::for_refund( $refund, $parent )[0];

		$this->assertSame( '28-refund', $payment['transaction_id'] );
		$this->assertSame( 'stripe', $payment['processor'] );
		$this->assertSame( 20.0, $payment['amount'] );
		$this->assertSame( 'ch_3Q1', $payment['processor_charge_id'] );
	}

	private function paid_order( $gateway, $transaction_id, $meta ) {
		return new SL_Test_Order(
			array(
				'id'                   => 24,
				'date_paid'            => new DateTime( '2026-11-18T22:06:39+00:00' ),
				'payment_method'       => $gateway,
				'payment_method_title' => 'Card',
				'transaction_id'       => $transaction_id,
				'total'                => '54.00',
				'currency'             => 'USD',
			),
			$meta
		);
	}
}
