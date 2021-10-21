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
 * @group Fundraising
 * @group DonationInterface
 * @group GatewayPage
 */
class GatewayPageTest extends DonationInterfaceTestCase {

	/**
	 * @var GatewayPage
	 */
	protected $page;
	/**
	 * @var GatewayAdapter
	 */
	protected $adapter;

	public function setUp(): void {
		$this->page = new TestingGatewayPage();
		// put these here so tests can override them
		TestingGenericAdapter::$fakeGlobals = [ 'FallbackCurrency' => 'USD' ];
		TestingGenericAdapter::$acceptedCurrencies[] = 'USD';
		TestingGenericAdapter::$fakeIdentifier = 'globalcollect';
		$this->setMwGlobals( [
			'wgPaypalGatewayEnabled' => true,
			'wgDonationInterfaceAllowedHtmlForms' => [
				'paypal' => [
					'gateway' => 'paypal',
					'payment_methods' => [ 'paypal' => 'ALL' ],
				],
			],
		] );
		parent::setUp();
	}

	protected function setUpAdapter( $extra = [] ) {
		$externalData = array_merge(
			[
				'amount' => '200',
				'currency' => 'BBD',
				'contribution_tracking_id' => mt_rand( 10000, 10000000 ),
			],
			$extra
		);
		$this->adapter = new TestingGenericAdapter( [
			'external_data' => $externalData,
		] );
		$this->page->adapter = $this->adapter;
	}

	public function tearDown(): void {
		TestingGenericAdapter::$acceptedCurrencies = [];
		TestingGenericAdapter::$fakeGlobals = [];
		TestingGenericAdapter::$fakeIdentifier = false;
		parent::tearDown();
	}

	public function testCurrencyFallbackWithNotification() {
		TestingGenericAdapter::$fakeGlobals['NotifyOnConvert'] = true;
		$this->setUpAdapter();

		$this->assertFalse( $this->adapter->validatedOK() );

		$errors = $this->adapter->getErrorState()->getErrors();
		$msgKey = 'donate_interface-fallback-currency-notice';
		$this->assertEquals( $msgKey, $errors[0]->getMessageKey() );
		$this->assertEquals( 100, $this->adapter->getData_Unstaged_Escaped( 'amount' ) );
		$this->assertEquals( 'USD', $this->adapter->getData_Unstaged_Escaped( 'currency' ) );
	}

	public function testCurrencyFallbackIntermediateConversion() {
		TestingGenericAdapter::$fakeGlobals['FallbackCurrency'] = 'OMR';
		TestingGenericAdapter::$fakeGlobals['NotifyOnConvert'] = true;
		TestingGenericAdapter::$acceptedCurrencies[] = 'OMR';
		// FIXME: Relies on app default exchange rate.  Set explicitly instead.
		$this->setUpAdapter();

		$errors = $this->adapter->getErrorState()->getErrors();
		$msgKey = 'donate_interface-fallback-currency-notice';
		$this->assertEquals( $msgKey, $errors[0]->getMessageKey() );
		$this->assertEquals( 38, $this->adapter->getData_Unstaged_Escaped( 'amount' ) );
		$this->assertEquals( 'OMR', $this->adapter->getData_Unstaged_Escaped( 'currency' ) );
	}

	public function testCurrencyFallbackWithoutNotification() {
		TestingGenericAdapter::$fakeGlobals['NotifyOnConvert'] = false;
		$this->setUpAdapter();

		$this->assertTrue( $this->adapter->validatedOK() );

		$errorState = $this->adapter->getErrorState();
		$this->assertFalse( $errorState->hasErrors() );
		$this->assertEquals( 100, $this->adapter->getData_Unstaged_Escaped( 'amount' ) );
		$this->assertEquals( 'USD', $this->adapter->getData_Unstaged_Escaped( 'currency' ) );
	}

	public function testCurrencyFallbackAlwaysNotifiesIfOtherErrors() {
		TestingGenericAdapter::$fakeGlobals['NotifyOnConvert'] = false;
		$this->setUpAdapter( [ 'email' => 'notanemail' ] );

		$errors = $this->adapter->getErrorState()->getErrors();
		$msgKey = 'donate_interface-fallback-currency-notice';
		$foundError = false;
		foreach ( $errors as $error ) {
			if ( $error->getField() === 'currency' ) {
				$this->assertEquals( $msgKey, $error->getMessageKey() );
				$foundError = true;
			}
		}
		$this->assertTrue( $foundError );
		$this->assertEquals( 100, $this->adapter->getData_Unstaged_Escaped( 'amount' ) );
		$this->assertEquals( 'USD', $this->adapter->getData_Unstaged_Escaped( 'currency' ) );
	}

	public function testNoFallbackForSupportedCurrency() {
		TestingGenericAdapter::$acceptedCurrencies[] = 'BBD';
		$this->setUpAdapter();

		$errorState = $this->adapter->getErrorState();
		$this->assertFalse( $errorState->hasErrors() );
		$this->assertEquals( 200, $this->adapter->getData_Unstaged_Escaped( 'amount' ) );
		$this->assertEquals( 'BBD', $this->adapter->getData_Unstaged_Escaped( 'currency' ) );
	}

	public function testCurrencyFallbackByCountry() {
		// With 'FallbackCurrencyByCountry', we need to return a single supported currency
		TestingGenericAdapter::$acceptedCurrencies = [ 'USD' ];
		TestingGenericAdapter::$fakeGlobals = [
			'FallbackCurrency' => false,
			'FallbackCurrencyByCountry' => true,
		];
		$extra = [
			'country' => 'US',
		];
		$this->setUpAdapter( $extra );

		$this->assertEquals( 100, $this->adapter->getData_Unstaged_Escaped( 'amount' ) );
		$this->assertEquals( 'USD', $this->adapter->getData_Unstaged_Escaped( 'currency' ) );
	}

	/**
	 * Before redirecting a user to the processor, we should log all of their
	 * details at info level
	 */
	public function testLogDetailsOnRedirect() {
		$init = $this->getDonorTestData();
		$session = [ 'Donor' => $init ];

		$this->verifyFormOutput( 'PaypalLegacyGateway', $init, [], false, $session );

		$logged = self::getLogMatches( LogLevel::INFO, '/^Redirecting for transaction: /' );
		$this->assertCount( 1, $logged, 'Should have logged details once' );
		preg_match( '/Redirecting for transaction: (.*)$/', $logged[0], $matches );
		$detailString = $matches[1];
		$expected = [
			'currency' => 'USD',
			'payment_submethod' => '',
			'first_name' => 'Firstname',
			'last_name' => 'Surname',
			'gross' => '1.55',
			'fee' => 0,
			'language' => 'en',
			'email' => 'nobody@wikimedia.org',
			'country' => 'US',
			'payment_method' => 'paypal',
			'user_ip' => '127.0.0.1',
			'recurring' => '',
			'utm_source' => '..paypal',
			'gateway' => 'paypal',
			'gateway_account' => 'testing',
			'gateway_txn_id' => false,
			'response' => false,
			'street_address' => '123 Fake Street',
			'city' => 'San Francisco',
			'state_province' => 'CA',
			'postal_code' => '94105',
		];
		$actual = json_decode( $detailString, true );
		// TODO: when tests use PHPUnit 4.4
		// $this->assertArraySubset( $expected, $actual, false, 'Logged the wrong stuff' );
		$expected['order_id'] = $actual['contribution_tracking_id'];
		unset( $actual['contribution_tracking_id'] );
		unset( $actual['date'] );
		$this->assertEquals( $expected, $actual, 'Logged the wrong stuff!' );
	}
}
