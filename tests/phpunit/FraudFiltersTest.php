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

use Wikimedia\TestingAccessWrapper;

/**
 * @group Fundraising
 * @group DonationInterface
 * @group FormChooser
 */
class DonationInterface_FraudFiltersTest extends DonationInterfaceTestCase {


	function testGCFraudFilters() {
		$this->setMwGlobals( array(
			'wgGlobalCollectGatewayEnableMinfraud' => true,
			'wgDonationInterfaceMinFraudServers' => array('0.0.0.0'),
		) );

		$options = $this->getDonorTestData();
		$options['email'] = 'somebody@wikipedia.org';
		$class = $this->testAdapterClass;

		$gateway = $this->getFreshGatewayObject( $options );

		$gateway->runAntifraudFilters();

		$this->assertEquals( 'reject', $gateway->getValidationAction(), 'Validation action is not as expected' );
		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$this->assertEquals( 107.5, $exposed->risk_score, 'RiskScore is not as expected for failure mode' );
	}
}
// Stub out Minfraud class for CI tests
if ( !class_exists( 'CreditCardFraudDetection' ) ) {
	class CreditCardFraudDetection{
		public $server;
		public function filter_field( $a, $b ) {
			return 'blah';
		}
		public function query() {}
		public function input( $a ) {}
		public function output() {
			return array();
		}
	}
}

