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
	public function setUp() {
		parent::setUp();

		$this->setMwGlobals( array(
			'wgDonationInterfaceLogCompleted' => true,
		) );
	}

	/**
	 * @param $name string The name of the test case
	 * @param $data array Any parameters read from a dataProvider
	 * @param $dataName string|int The name or index of the data set
	 */
	function __construct( $name = null, array $data = array(), $dataName = '' ) {
		parent::__construct( $name, $data, $dataName );
		$this->testAdapterClass = 'TestingGlobalCollectAdapter';
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
		$init['unusual_key'] = mt_rand();
		unset( $init['order_id'] );

		$expectedObject = array(
			'amount' => 23.45,
			'appeal' => 'JimmyQuote',
			'attempt_id' => '2',
			'avs_result' => 'M',
			'city' => 'San Francisco',
			//'contribution_tracking_id' => '1',
			'country' => 'US',
			'currency_code' => 'EUR',
			'cvv_result' => 'P',
			'effort_id' => '1',
			'email' => 'innocent@manichean.com',
			'ffname' => 'cc-vmad',
			'fname' => 'Firstname',
			'full_name' => 'Firstname Surname',
			'gateway' => 'globalcollect',
			'language' => 'en',
			'lname' => 'Surname',
			//'order_id' => 'ORDER_ID',
			'payment_method' => 'cc',
			'payment_product' => 1,
			'payment_submethod' => 'visa',
			'recurring' => '',
			'referrer' => 'www.yourmom.com',
			//'returnto' => 'http =>//payments.dev/index.php/Special =>GlobalCollectGatewayResult?order_id=ORDER_ID',
			'server_ip' => '127.0.0.1',
			'state' => 'CA',
			'street' => '123 Fake Street',
			'unusual_key' => ( string ) $init['unusual_key'],
			'user_ip' => '127.0.0.1',
			'utm_source' => '..cc',
			'zip' => '94105',
		);

		$gateway = $this->getFreshGatewayObject( $init );
		$gateway->setDummyGatewayResponseCode( '200' );
		$gateway->do_transaction( 'Confirm_CreditCard' );
		$preface_pattern = '/' . preg_quote( GatewayAdapter::COMPLETED_PREFACE ) . '/';
		$matches = $this->getLogMatches( LogLevel::INFO, $preface_pattern );
		$this->assertTrue( $matches !== false,
			'Should log a completion message' );

		$json = str_replace( GatewayAdapter::COMPLETED_PREFACE, '', $matches[0] );
		$actualObject = json_decode( $json, true );
		unset( $actualObject['order_id'] );
		unset( $actualObject['returnto'] );
		unset( $actualObject['contribution_tracking_id'] );
		$this->assertEquals( $expectedObject, $actualObject,
			'Completion message is as expected' );
	}
}
