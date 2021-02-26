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
 */

/**
 *
 * @group Fundraising
 * @group DonationInterface
 * @group PayPal
 */
class DonationInterface_Adapter_PayPal_Legacy_Test extends DonationInterfaceTestCase {

	/**
	 * @param string $name The name of the test case
	 * @param array $data Any parameters read from a dataProvider
	 * @param string|int $dataName The name or index of the data set
	 */
	public function __construct( $name = null, array $data = [], $dataName = '' ) {
		parent::__construct( $name, $data, $dataName );
		$this->testAdapterClass = TestingPaypalLegacyAdapter::class;
	}

	public function setUp(): void {
		parent::setUp();

		$this->setMwGlobals( [
			'wgDonationInterfaceCancelPage' => 'https://example.com/tryAgain.php',
			'wgPaypalGatewayEnabled' => true,
			'wgDonationInterfaceThankYouPage' => 'https://example.org/wiki/Thank_You',
			'wgDonationInterfaceAllowedHtmlForms' => [
				'paypal' => [
					'gateway' => 'paypal',
					'payment_methods' => [ 'paypal' => 'ALL' ],
				],
				'paypal-recurring' => [
					'gateway' => 'paypal',
					'payment_methods' => [ 'paypal' => 'ALL' ],
					'recurring',
				],
			],
		] );
	}

	public function tearDown(): void {
		TestingPaypalLegacyAdapter::$fakeGlobals = [];

		parent::tearDown();
	}

	/**
	 * Integration test to verify that the Donate transaction works as expected when all necessary data is present.
	 */
	public function testDoTransactionDonate() {
		$init = $this->getDonorTestData();
		$gateway = $this->getFreshGatewayObject( $init );

		$ret = $gateway->doPayment();
		parse_str( parse_url( $ret->getRedirect(), PHP_URL_QUERY ), $res );

		$expected = [
			'amount' => $init['amount'],
			'currency_code' => $init['currency'],
			'country' => $init['country'],
			'business' => 'phpunittesting@wikimedia.org',
			'cmd' => '_donations',
			'item_name' => 'Wikimedia 877 600 9454',
			'item_number' => 'DONATE',
			'no_note' => '0',
			'custom' => $gateway->getData_Unstaged_Escaped( 'contribution_tracking_id' ),
			'lc' => $init['country'], // this works because it's a US donor...
			'cancel_return' => 'https://example.com/tryAgain.php/en',
			'return' => 'https://example.org/wiki/Thank_You/en?country=US',
		];

		$this->assertEquals( $expected, $res, 'Paypal "Donate" transaction not constructing the expected redirect URL' );
		$this->assertEquals(
			$gateway->getData_Unstaged_Escaped( 'contribution_tracking_id' ),
			$gateway->getData_Unstaged_Escaped( 'order_id' ),
			"Paypal order_id should equal ct_id"
		);
	}

	/**
	 * Integration test to verify that the DonateRecurring transaction works as expected when all necessary data is present.
	 */
	public function testDoTransactionDonateRecurring() {
		$init = $this->getDonorTestData();
		$init['recurring'] = '1';
		$gateway = $this->getFreshGatewayObject( $init );

		$ret = $gateway->doPayment();
		parse_str( parse_url( $ret->getRedirect(), PHP_URL_QUERY ), $res );

		$expected = [
			'a3' => $init['amount'], // obviously.
			'currency_code' => $init['currency'],
			'country' => $init['country'],
			'business' => 'phpunittesting@wikimedia.org',
			'cmd' => '_xclick-subscriptions',
			'item_name' => 'Wikimedia 877 600 9454',
			'item_number' => 'DONATE',
			'no_note' => '0',
			'custom' => $gateway->getData_Unstaged_Escaped( 'contribution_tracking_id' ),
			'lc' => $init['country'], // this works because it's a US donor...
			't3' => 'M', // hard-coded in transaction definition
			'p3' => '1', // hard-coded in transaction definition
			'src' => '1', // hard-coded in transaction definition
			'srt' => $gateway->getGlobal( 'RecurringLength' ),
			'cancel_return' => 'https://example.com/tryAgain.php/en',
			'return' => 'https://example.org/wiki/Thank_You/en?country=US',
		];

		$this->assertEquals( $expected, $res, 'Paypal "DonateRecurring" transaction not constructing the expected redirect URL' );
	}

	/**
	 * Integration test to verify that the Donate transaction works as expected when all necessary data is present.
	 */
	public function testDoTransactionDonateXclick() {
		$init = $this->getDonorTestData();

		TestingPaypalLegacyAdapter::$fakeGlobals = [
			'XclickCountries' => [ $init['country'] ],
		];

		$gateway = $this->getFreshGatewayObject( $init );

		$ret = $gateway->doPayment();
		parse_str( parse_url( $ret->getRedirect(), PHP_URL_QUERY ), $res );

		$expected = [
			'amount' => $init['amount'],
			'currency_code' => $init['currency'],
			'country' => $init['country'],
			'business' => 'phpunittesting@wikimedia.org',
			'cmd' => '_xclick',
			'item_name' => 'Wikimedia 877 600 9454',
			'item_number' => 'DONATE',
			'no_note' => '1', // hard-coded in transaction definition
			'custom' => $gateway->getData_Unstaged_Escaped( 'contribution_tracking_id' ),
// 'lc' => $init['country'], //Apparently, this was removed from our implementation, because 'CN' is weird.
			'cancel_return' => 'https://example.com/tryAgain.php/en',
			'return' => 'https://example.org/wiki/Thank_You/en?country=US',
			'no_shipping' => '1', // hard-coded in transaction definition
		];

		$this->assertEquals( $expected, $res, 'Paypal "DonateXclick" transaction not constructing the expected redirect URL' );
	}

	/**
	 * Integration test to verify that the Paypal gateway redirects when validation is successful.
	 */
	public function testRedirectFormOnValid() {
		$init = $this->getDonorTestData();
		$session = [ 'Donor' => $init ];

		$redirectTest = function ( $location ) use ( $init ) {
			parse_str( parse_url( $location, PHP_URL_QUERY ), $actual );
			$this->assertEquals( $init['amount'], $actual['amount'] );
		};
		$assertNodes = [
			'headers' => [
				'Location' => $redirectTest,
			]
		];

		$this->verifyFormOutput( 'PaypalLegacyGateway', $init, $assertNodes, false, $session );
	}

	/**
	 * Integration test to verify that the Paypal gateway shows an error message when validation fails.
	 */
	public function testShowFormOnError() {
		$init = $this->getDonorTestData();
		$init['amount'] = '-100.00';
		$session = [ 'Donor' => $init ];
		$errorMessage = wfMessage( 'donate_interface-error-msg-invalid-amount' )->text();
		$assertNodes = [
			'mw-content-text' => [
				'innerhtmlmatches' => "/.*$errorMessage.*/"
			]
		];

		$this->verifyFormOutput( 'PaypalLegacyGateway', $init, $assertNodes, false, $session );
	}

	/**
	 * Stay on the payments form if there's a currency conversion notification.
	 */
	public function testShowFormOnCurrencyFallback() {
		$init = $this->getDonorTestData();
		$init['currency'] = 'BBD';
		$init['amount'] = 15.00;
		$session = [ 'Donor' => $init ];
		$this->setMwGlobals( [
			'wgDonationInterfaceFallbackCurrency' => 'USD',
			'wgDonationInterfaceNotifyOnConvert' => true,
		] );
		$errorMessage = wfMessage( 'donate_interface-fallback-currency-notice', 'USD' )->text();
		$assertNodes = [
			'headers' => [
				'location' => null,
			],
			'currencyMsg' => [
				'innerhtmlmatches' => "/.*$errorMessage.*/"
			]
		];

		$this->verifyFormOutput( 'PaypalLegacyGateway', $init, $assertNodes, false, $session );
	}

	/**
	 * Integration test to verify that the Donate transaction works as expected in Belgium for fr, de, and nl.
	 *
	 * @dataProvider belgiumLanguageProvider
	 */
	public function testDoTransactionDonate_BE( $language ) {
		$init = $this->getDonorTestData( 'BE' );
		$init['language'] = $language;
		$this->setLanguage( $language );
		$gateway = $this->getFreshGatewayObject( $init );
		$donateText = wfMessage( 'donate_interface-donation-description' )->inLanguage( $language )->text();
		$ret = $gateway->doPayment();
		parse_str( parse_url( $ret->getRedirect(), PHP_URL_QUERY ), $res );

		$expected = [
			'amount' => $init['amount'],
			'currency_code' => $init['currency'],
			'country' => 'BE',
			'business' => 'phpunittesting@wikimedia.org',
			'cmd' => '_donations',
			'item_name' => $donateText,
			'item_number' => 'DONATE',
			'no_note' => '0',
			'custom' => $gateway->getData_Unstaged_Escaped( 'contribution_tracking_id' ),
			'lc' => 'BE',
			'cancel_return' => "https://example.com/tryAgain.php/$language",
			'return' => "https://example.org/wiki/Thank_You/$language?country=BE",
		];

		$this->assertEquals( $expected, $res, 'Paypal "Donate" transaction not constructing the expected redirect URL' );
		$this->assertEquals(
			$gateway->getData_Unstaged_Escaped( 'contribution_tracking_id' ),
			$gateway->getData_Unstaged_Escaped( 'order_id' ),
			"Paypal order_id should equal ct_id"
		);
	}

	/**
	 * Integration test to verify that the Donate transaction works as expected
	 * in Canada for English and French
	 *
	 * @dataProvider canadaLanguageProvider
	 */
	public function testDoTransactionDonate_CA( $language ) {
		$init = $this->getDonorTestData( 'CA' );
		$init['language'] = $language;
		$this->setLanguage( $language );
		$gateway = $this->getFreshGatewayObject( $init );
		$donateText = wfMessage( 'donate_interface-donation-description' )->inLanguage( $language )->text();
		$ret = $gateway->doPayment();
		parse_str( parse_url( $ret->getRedirect(), PHP_URL_QUERY ), $res );

		$expected = [
			'amount' => $init['amount'],
			'currency_code' => 'CAD',
			'country' => 'CA',
			'business' => 'phpunittesting@wikimedia.org',
			'cmd' => '_donations',
			'item_name' => $donateText,
			'item_number' => 'DONATE',
			'no_note' => '0',
			'custom' => $gateway->getData_Unstaged_Escaped( 'contribution_tracking_id' ),
			'lc' => 'CA',
			'cancel_return' => "https://example.com/tryAgain.php/$language",
			'return' => "https://example.org/wiki/Thank_You/$language?country=CA",
		];

		$this->assertEquals( $expected, $res, 'Paypal "Donate" transaction not constructing the expected redirect URL' );
		$this->assertEquals(
			$gateway->getData_Unstaged_Escaped( 'contribution_tracking_id' ),
			$gateway->getData_Unstaged_Escaped( 'order_id' ),
			"Paypal order_id should equal ct_id"
		);
	}

	/**
	 * Integration test to verify that the Donate transaction works as expected in Italy
	 */
	public function testDoTransactionDonate_IT() {
		$init = $this->getDonorTestData( 'IT' );
		$this->setLanguage( 'it' );
		$gateway = $this->getFreshGatewayObject( $init );
		$donateText = wfMessage( 'donate_interface-donation-description' )->inLanguage( 'it' )->text();
		$ret = $gateway->doPayment();
		parse_str( parse_url( $ret->getRedirect(), PHP_URL_QUERY ), $res );

		$expected = [
			'amount' => $init['amount'],
			'currency_code' => $init['currency'],
			'country' => 'IT',
			'business' => 'phpunittesting@wikimedia.org',
			'cmd' => '_donations',
			'item_name' => $donateText,
			'item_number' => 'DONATE',
			'no_note' => '0',
			'custom' => $gateway->getData_Unstaged_Escaped( 'contribution_tracking_id' ),
			'lc' => 'IT',
			'cancel_return' => 'https://example.com/tryAgain.php/it',
			'return' => 'https://example.org/wiki/Thank_You/it?country=IT',
		];

		$this->assertEquals( $expected, $res, 'Paypal "Donate" transaction not constructing the expected redirect URL' );
		$this->assertEquals(
			$gateway->getData_Unstaged_Escaped( 'contribution_tracking_id' ),
			$gateway->getData_Unstaged_Escaped( 'order_id' ),
			"Paypal order_id should equal ct_id"
		);
	}
}
