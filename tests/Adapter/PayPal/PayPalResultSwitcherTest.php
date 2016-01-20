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
 * @group Fundraising
 * @group DonationInterface
 * @group PayPal
 */
class PayPalResultSwitcherTest extends DonationInterfaceTestCase {
	public function setUp() {
		parent::setUp();

		$this->setMwGlobals( array(
			'wgPaypalGatewayEnabled' => true,
		) );
	}

	function testSuccessfulRedirect() {
		$init = $this->getDonorTestData( 'FR' );
		$init['OTT'] = 'SALT123456789';
		$session = array( 'Donor' => $init );

		$assertNodes = array(
			'headers' => array(
				'Location' => 'https://wikimediafoundation.org/wiki/Thank_You/fr',
			),
		);

		$this->verifyFormOutput( 'PaypalGatewayResult', $init, $assertNodes, false, $session );
	}

}
