<?php
/**
 * Wikimedia Foundation
 *
 * LICENSE
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 */
use SmashPig\PaymentData\FinalStatus;

/**
 *
 * @group Fundraising
 * @group DonationInterface
 * @group GlobalCollect
 * @group Recurring
 */
class DonationInterface_Adapter_GlobalCollect_RecurringTest extends DonationInterfaceTestCase {

	/**
	 * @param string|null $name The name of the test case
	 * @param array $data Any parameters read from a dataProvider
	 * @param string|int $dataName The name or index of the data set
	 */
	public function __construct( $name = null, array $data = [], $dataName = '' ) {
		parent::__construct( $name, $data, $dataName );
		$this->testAdapterClass = TestingGlobalCollectAdapter::class;
	}

	public function setUp(): void {
		parent::setUp();

		$this->setMwGlobals( [
			'wgGlobalCollectGatewayEnabled' => true,
		] );
	}

	/**
	 * Can make a recurring payment
	 *
	 * @covers GlobalCollectAdapter::transactionRecurring_Charge
	 */
	public function testRecurringCharge() {
		$init = [
			'amount' => '2345',
			'effort_id' => 2,
			'order_id' => '9998890004',
			'currency' => 'EUR',
			'payment_product' => '',
		];
		$gateway = $this->getFreshGatewayObject( $init );

		$gateway::setDummyGatewayResponseCode( 'recurring-OK' );

		$result = $gateway->do_transaction( 'Recurring_Charge' );

		$this->assertTrue( $result->getCommunicationStatus() );
		$this->assertRegExp( '/SET_PAYMENT/', $result->getRawResponse() );
	}

	/**
	 * Can make a recurring payment
	 *
	 * @covers GlobalCollectAdapter::transactionRecurring_Charge
	 */
	public function testDeclinedRecurringCharge() {
		$init = [
			'amount' => '2345',
			'effort_id' => 2,
			'order_id' => '9998890004',
			'currency' => 'EUR',
			'payment_product' => '',
		];
		$gateway = $this->getFreshGatewayObject( $init );

		$gateway::setDummyGatewayResponseCode( 'recurring-declined' );

		$result = $gateway->do_transaction( 'Recurring_Charge' );

		$this->assertRegExp( '/GET_ORDERSTATUS/', $result->getRawResponse(),
			'Stopped after GET_ORDERSTATUS.' );
		$this->assertCount( 2, $gateway->curled,
			'Expected 2 API calls' );
		$this->assertEquals( FinalStatus::FAILED, $gateway->getFinalStatus() );
	}

	/**
	 * Throw errors if the payment is incomplete
	 *
	 * @covers GlobalCollectAdapter::transactionRecurring_Charge
	 */
	public function testRecurringTimeout() {
		$init = [
			'amount' => '2345',
			'effort_id' => 2,
			'order_id' => '9998890004',
			'currency' => 'EUR',
			'payment_product' => '',
		];
		$gateway = $this->getFreshGatewayObject( $init );

		$gateway::setDummyGatewayResponseCode( 'recurring-timeout' );

		$result = $gateway->do_transaction( 'Recurring_Charge' );

		$this->assertFalse( $result->getCommunicationStatus() );
		$this->assertRegExp( '/GET_ORDERSTATUS/', $result->getRawResponse() );
		// FIXME: This is a little funky--the transaction is actually pending-poke.
		$this->assertEquals( FinalStatus::FAILED, $gateway->getFinalStatus() );
	}

	/**
	 * Can resume a recurring payment
	 *
	 * @covers GlobalCollectAdapter::transactionRecurring_Charge
	 */
	public function testRecurringResume() {
		$init = [
			'amount' => '2345',
			'effort_id' => 2,
			'order_id' => '9998890004',
			'currency' => 'EUR',
			'payment_product' => '',
		];
		$gateway = $this->getFreshGatewayObject( $init );

		$gateway::setDummyGatewayResponseCode( 'recurring-resume' );

		$result = $gateway->do_transaction( 'Recurring_Charge' );

		$this->assertTrue( $result->getCommunicationStatus() );
		$this->assertRegExp( '/SET_PAYMENT/', $result->getRawResponse() );
	}

	public function testConvertPaymentToProfile() {
		$init = [
			'amount' => '1234',
			'currency' => 'USD',
			'order_id' => '879656905',
			'payment_product' => 'cc',
			'country' => 'US',
		];
		$gateway = $this->getFreshGatewayObject( $init );

		$gateway->do_transaction( 'CONVERT_PAYMENTTOPROFILE' );
		$token = $gateway->getData_Unstaged_Escaped( 'recurring_payment_token' );
		$this->assertEquals( 'af16af70-2bf5-44e9-a087-68c87f67e3a1', $token );
	}
}
