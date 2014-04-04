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

/**
 * @see DonationInterfaceTestCase
 */
require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'DonationInterfaceTestCase.php';

/**
 * @group Fundraising
 * @group DonationInterface
 * @group DIIntegration
 */
class DonationInterface_IntegrationTestCase extends DonationInterfaceTestCase {

	/**
	 *
	 */
	public function __construct(){
		$adapterclass = TESTS_ADAPTER_DEFAULT;
		$this->testAdapterClass = $adapterclass;

		parent::__construct();
//		self::setupMoreForms();
	}

	//this is meant to simulate a user choosing paypal, then going back and choosing GC.
	public function testBackClickPayPalToGC() {
		$this->testAdapterClass = 'TestingPayPalAdapter';
		$options = $this->getDonorTestData( 'US' );
//		unset( $options['ffname'] );

		$gateway = $this->getFreshGatewayObject( $options );
		$gateway->do_transaction( 'Donate' );

		//check to see that we have a numAttempt and form set in the session
		$this->assertEquals( 'paypal', $_SESSION['PaymentForms'][0], "Paypal didn't load its form." );
		$this->assertEquals( '1', $_SESSION['numAttempt'], "We failed to record the initial paypal attempt in the session" );
		//now, get GC.
		$this->testAdapterClass = 'TestingGlobalCollectAdapter';
		$options['payment_method'] = 'cc';
		$gateway = $this->getFreshGatewayObject( $options );
		$gateway->do_transaction( 'INSERT_ORDERWITHPAYMENT' );

		$ffname = $gateway->getData_Unstaged_Escaped( 'ffname' );
		$this->assertEquals( 'cc-vmad', $ffname, "GC did not load the expected form." );

		$errors = '';
		if ( array_key_exists( LOG_ERR, $gateway->testlog ) ) {
			foreach ( $gateway->testlog[LOG_ERR] as $msg ) {
				$errors += "$msg\n";
			}
		}
		$this->assertEmpty( $errors, "The gateway error log had the following message(s):\n" . $errors );
	}

}


