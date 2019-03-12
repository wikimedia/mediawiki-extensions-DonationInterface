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
 * @group DIIntegration
 */
class DonationInterface_IntegrationTest extends DonationInterfaceTestCase {

	/**
	 * @param string $name The name of the test case
	 * @param array $data Any parameters read from a dataProvider
	 * @param string|int $dataName The name or index of the data set
	 */
	public function __construct( $name = null, array $data = array(), $dataName = '' ) {
		$adapterclass = TESTS_ADAPTER_DEFAULT;
		$this->testAdapterClass = $adapterclass;

		parent::__construct( $name, $data, $dataName );
	}

	public function setUp() {
		parent::setUp();

		$this->setMwGlobals( array(
			'wgGlobalCollectGatewayEnabled' => true,
			'wgPaypalGatewayEnabled' => true,
			'wgDonationInterfaceAllowedHtmlForms' => array(
				'cc-vmad' => array(
					'gateway' => 'globalcollect',
					'payment_methods' => array( 'cc' => array( 'visa', 'mc', 'amex', 'discover' ) ),
					'countries' => array(
						'+' => array( 'US', ),
					),
				),
				'paypal' => array(
					'gateway' => 'paypal',
					'payment_methods' => array( 'paypal' => 'ALL' ),
				),
			),
		) );
	}

	// this is meant to simulate a user choosing paypal, then going back and choosing GC.
	public function testBackClickPayPalToGC() {
		$options = $this->getDonorTestData( 'US' );
		$options['payment_method'] = 'paypal';
		$options['ffname'] = 'paypal';
		$paypalRequest = $this->setUpRequest( $options );

		$gateway = new TestingPaypalLegacyAdapter();
		$gateway->do_transaction( 'Donate' );

		$paymentForms = $paypalRequest->getSessionData( 'PaymentForms' );
		// check to see that we have a numAttempt and form set in the session
		$this->assertEquals( 'paypal', $paymentForms[0], "Paypal didn't load its form." );
		$this->assertEquals( '1', $paypalRequest->getSessionData( 'numAttempt' ), "We failed to record the initial paypal attempt in the session" );
		// now, get GC.
		$options['payment_method'] = 'cc';
		unset( $options['ffname'] );
		$this->setUpRequest( $options, $paypalRequest->getSessionArray() );
		$gateway = new TestingGlobalCollectAdapter();
		$response = $gateway->do_transaction( 'INSERT_ORDERWITHPAYMENT' );

		$this->assertEmpty( $response->getErrors() );

		$errors = '';
		$messages = DonationLoggerFactory::$overrideLogger->messages;
		if ( array_key_exists( LogLevel::ERROR, $messages ) ) {
			foreach ( $messages[LogLevel::ERROR] as $msg ) {
				$errors .= "$msg\n";
			}
		}
		$this->assertEmpty( $errors, "The gateway error log had the following message(s):\n" . $errors );
	}

}
