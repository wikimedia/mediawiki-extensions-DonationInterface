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
use Psr\Log\LogLevel;

/**
 *
 * @group Fundraising
 * @group DonationInterface
 */
class DonationInterface_LoggingTest extends DonationInterfaceTestCase {
	public function setUp(): void {
		parent::setUp();

		$this->setMwGlobals( [
			'wgDonationInterfaceLogCompleted' => true,
		] );
	}

	/**
	 * @param string $name The name of the test case
	 * @param array $data Any parameters read from a dataProvider
	 * @param string|int $dataName The name or index of the data set
	 */
	public function __construct( $name = null, array $data = [], $dataName = '' ) {
		parent::__construct( $name, $data, $dataName );
		$this->testAdapterClass = TestingGlobalCollectAdapter::class;
	}

	/**
	 * Check that we can log completed transactions
	 */
	public function testLogCompleted() {
		$init = $this->getDonorTestData();
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['email'] = 'innocent@manichean.com';
		$init['ffname'] = 'cc-vmad';
		unset( $init['order_id'] );

		$expectedObject = [
			'gross' => 23.45,
			'city' => 'San Francisco',
			// 'contribution_tracking_id' => '1',
			'fee' => 0,
			'country' => 'US',
			'currency' => 'EUR',
			'email' => 'innocent@manichean.com',
			'first_name' => 'Firstname',
			'gateway' => 'globalcollect',
			'language' => 'en',
			'last_name' => 'Surname',
			'payment_method' => 'cc',
			'payment_submethod' => 'visa',
			'recurring' => '',
			'state_province' => 'CA',
			'street_address' => '123 Fake Street',
			'user_ip' => '127.0.0.1',
			'utm_source' => '..cc',
			'postal_code' => '94105',
			'response' => 'Original Response Status (pre-SET_PAYMENT): 200',
			'gateway_account' => 'test',
		];

		$gateway = $this->getFreshGatewayObject( $init );
		$gateway::setDummyGatewayResponseCode( '200' );
		$gateway->do_transaction( 'Confirm_CreditCard' );
		$preface_pattern = '/' . preg_quote( GatewayAdapter::COMPLETED_PREFACE ) . '/';
		$matches = self::getLogMatches( LogLevel::INFO, $preface_pattern );
		$this->assertTrue( $matches !== false,
			'Should log a completion message' );

		$json = str_replace( GatewayAdapter::COMPLETED_PREFACE, '', $matches[0] );
		$actualObject = $this->stripRandomFields( json_decode( $json, true ) );
		$this->assertEquals( $expectedObject, $actualObject,
			'Completion message is as expected' );
	}

	/**
	 * Test robustness when passed a bad Unicode string.
	 */
	public function testBadUnicode() {
		$init = $this->getDonorTestData();
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['amount'] = '23';
		// Fake name with a bad character encoding.
		$init['first_name'] = 'Алексан' . chr( 239 );
		$init['last_name'] = 'Гончар';
		$init['email'] = 'innocent@manichean.com';
		$init['ffname'] = 'cc-vmad';
		$init['unusual_key'] = mt_rand();
		unset( $init['order_id'] );

		$expectedObject = [
			'gross' => 23.45,
			'fee' => 0,
			'city' => 'San Francisco',
			'country' => 'US',
			'currency' => 'EUR',
			'email' => 'innocent@manichean.com',
			'first_name' => 'Алексанï',
			'gateway' => 'globalcollect',
			'language' => 'en',
			'last_name' => 'Гончар',
			'payment_method' => 'cc',
			'payment_submethod' => 'visa',
			'recurring' => '',
			'state_province' => 'CA',
			'street_address' => '123 Fake Street',
			'user_ip' => '127.0.0.1',
			'utm_source' => '..cc',
			'postal_code' => '94105',
			'response' => 'Original Response Status (pre-SET_PAYMENT): 200',
			'gateway_account' => 'test',
		];

		$gateway = $this->getFreshGatewayObject( $init );
		$gateway::setDummyGatewayResponseCode( '200' );
		$gateway->do_transaction( 'Confirm_CreditCard' );
		$preface_pattern = '/' . preg_quote( GatewayAdapter::COMPLETED_PREFACE ) . '/';
		$matches = self::getLogMatches( LogLevel::INFO, $preface_pattern );
		$this->assertTrue( $matches !== false,
			'Should log a completion message' );

		$json = str_replace( GatewayAdapter::COMPLETED_PREFACE, '', $matches[0] );
		$actualObject = $this->stripRandomFields( json_decode( $json, true ) );

		$this->assertEquals( $expectedObject, $actualObject,
			'Completion message is as expected' );
	}

	protected function stripRandomFields( $data ) {
		$toUnset = [
			'contribution_tracking_id',
			'date',
			'gateway_txn_id',
			'order_id',
		];
		array_map( function ( $key ) use ( &$data ) {
			unset( $data[$key] );
		}, $toUnset );
		return $data;
	}
}
