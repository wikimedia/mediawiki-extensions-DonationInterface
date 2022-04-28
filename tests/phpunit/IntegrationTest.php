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
	 * @param string|null $name The name of the test case
	 * @param array $data Any parameters read from a dataProvider
	 * @param string|int $dataName The name or index of the data set
	 */
	public function __construct( $name = null, array $data = [], $dataName = '' ) {
		$adapterclass = TESTS_ADAPTER_DEFAULT;
		$this->testAdapterClass = $adapterclass;

		parent::__construct( $name, $data, $dataName );
	}

	public function setUp(): void {
		parent::setUp();

		$this->setMwGlobals( [
			'wgAstroPayGatewayEnabled' => true,
			'wgPaypalExpressGatewayEnabled' => true
		] );
	}

	/**
	 * This is meant to simulate a user choosing PayPal, then going back and choosing DLocal.
	 */
	public function testBackClickPayPalToAstroPay() {
		$options = $this->getDonorTestData( 'MX' );
		$options['payment_method'] = 'paypal';
		$paypalRequest = $this->setUpRequest( $options );

		$gateway = new TestingPaypalExpressAdapter();
		$gateway::setDummyGatewayResponseCode( 'OK' );
		$gateway->do_transaction( 'SetExpressCheckout' );
		$paypalCtId = $gateway->getData_Unstaged_Escaped( 'contribution_tracking_id' );

		// now, get AstroPay.
		$options['payment_method'] = 'cc';
		$options['payment_submethod'] = 'visa';
		$this->setUpRequest( $options, $paypalRequest->getSessionArray() );

		$gateway = new TestingAstroPayAdapter();
		$response = $gateway->do_transaction( 'NewInvoice' );
		$astroPayCtId = $gateway->getData_Unstaged_Escaped( 'contribution_tracking_id' );
		$this->assertNotEquals( $astroPayCtId, $paypalCtId, 'Did not regenerate contribution tracking ID on gateway switch' );
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
