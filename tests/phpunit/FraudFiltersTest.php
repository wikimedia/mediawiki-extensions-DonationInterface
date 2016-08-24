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
 * @group Fundraising
 * @group DonationInterface
 * @group FormChooser
 */
class DonationInterface_FraudFiltersTest extends DonationInterfaceTestCase {

	/**
	 * @param $name string The name of the test case
	 * @param $data array Any parameters read from a dataProvider
	 * @param $dataName string|int The name or index of the data set
	 */
	public function __construct( $name = null, array $data = array(), $dataName = '' ) {
		$adapterclass = TESTS_ADAPTER_DEFAULT;
		$this->testAdapterClass = $adapterclass;

		parent::__construct( $name, $data, $dataName );
		self::setupFraudMaps( $this );
	}

	public static function setupFraudMaps( $testContext ) {
		// Declare so setMwGlobals can override.
		global $wgGlobalCollectGatewayCustomFiltersFunctions;
		$wgGlobalCollectGatewayCustomFiltersFunctions = null;


		// Declare here cos reused.
		$customFilters = array(
			'getScoreCountryMap' => 50,
			'getScoreUtmCampaignMap' => 50,
			'getScoreUtmSourceMap' => 15,
			'getScoreUtmMediumMap' => 15,
			'getScoreEmailDomainMap' => 75,
		);

		$testContext->setMwGlobals( array(
			'wgDonationInterfaceCustomFiltersActionRanges' => array (
				'process' => array ( 0, 25 ),
				'review' => array ( 25, 50 ),
				'challenge' => array ( 50, 75 ),
				'reject' => array ( 75, 100 ),
			),

			'wgDonationInterfaceCustomFiltersRefRules' => array (
				'/donate-error/i' => 5,
			),

			'wgDonationInterfaceCustomFiltersSrcRules' => array ( '/wikimedia\.org/i' => 80 ),

			'wgDonationInterfaceCustomFiltersFunctions' => $customFilters,

			'wgGlobalCollectGatewayCustomFiltersFunctions' => array(
				'getCVVResult' => 20,
				'getAVSResult' => 25,
			) + $customFilters,

			'wgDonationInterfaceCountryMap' => array (
				'US' => 40,
				'CA' => 15,
				'RU' => -4,
			),

			'wgDonationInterfaceUtmCampaignMap' => array (
				'/^(C14_)/' => 14,
				'/^(spontaneous)/' => 5
			),
			'wgDonationInterfaceUtmSourceMap' => array (
				'/somethingmedia/' => 70
			),
			'wgDonationInterfaceUtmMediumMap' => array (
				'/somethingmedia/' => 80
			),
			'wgDonationInterfaceEmailDomainMap' => array (
				'wikimedia.org' => 42,
				'wikipedia.org' => 50,
			),
		) );
	}

	function testGCFraudFilters() {
		$this->setMwGlobals( array(
			'wgGlobalCollectGatewayEnableMinfraud' => true,
		) );

		$options = $this->getDonorTestData();
		$options['email'] = 'somebody@wikipedia.org';
		$class = $this->testAdapterClass;

		$gateway = $this->getFreshGatewayObject( $options );

		$gateway->runAntifraudFilters();

		$this->assertEquals( 'reject', $gateway->getValidationAction(), 'Validation action is not as expected' );
		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$this->assertEquals( 157.5, $exposed->risk_score, 'RiskScore is not as expected' );
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

