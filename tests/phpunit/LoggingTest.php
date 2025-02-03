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
class LoggingTest extends DonationInterfaceTestCase {
	protected function setUp(): void {
		parent::setUp();

		$this->setMwGlobals( self::getAllGlobalVariants( [
			'LogCompleted' => true,
			'MonthlyConvertCountries' => []
		] ) );
	}

	/**
	 * @param string|null $name The name of the test case
	 * @param array $data Any parameters read from a dataProvider
	 * @param string|int $dataName The name or index of the data set
	 */
	public function __construct( $name = null, array $data = [], $dataName = '' ) {
		parent::__construct( $name, $data, $dataName );
		$this->testAdapterClass = IngenicoAdapter::class;
	}

	/**
	 * Check that we can log completed transactions
	 */
	public function testLogCompleted() {
		$init = $this->getDonorTestData();
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['email'] = 'innocent@manichean.com';
		unset( $init['order_id'] );

		$expectedObject = [
			'gross' => '23.45',
			'city' => 'San Francisco',
			'fee' => 0,
			'country' => 'US',
			'currency' => 'USD',
			'email' => 'innocent@manichean.com',
			'first_name' => 'Firstname',
			'gateway' => 'ingenico',
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
			'response' => false,
			'gateway_account' => null,
			'initial_scheme_transaction_id' => '112233445566'
		];

		$gateway = $this->getFreshGatewayObject( $init );
		$this->mockIngenicoDonorReturn();
		$gateway->processDonorReturn( [] );
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
		array_map( static function ( $key ) use ( &$data ) {
			unset( $data[$key] );
		}, $toUnset );
		return $data;
	}
}
