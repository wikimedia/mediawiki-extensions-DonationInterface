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
class DonationInterface_FormChooserTest extends DonationInterfaceTestCase {

	/**
	 * @param $name string The name of the test case
	 * @param $data array Any parameters read from a dataProvider
	 * @param $dataName string|int The name or index of the data set
	 */
	public function __construct( $name = null, array $data = array(), $dataName = '' ) {
		$adapterclass = TESTS_ADAPTER_DEFAULT;
		$this->testAdapterClass = $adapterclass;

		parent::__construct( $name, $data, $dataName );
	}

	public function setUp() {
		parent::setUp();

		$this->setMwGlobals( array(
			'wgDonationInterfaceEnableFormChooser' => true,
			'wgGlobalCollectGatewayEnabled' => true,
			'wgPaypalGatewayEnabled' => true,
		) );

		$this->setupMoreForms();
	}

	public function setupMoreForms() {
		global $wgDonationInterfaceAllowedHtmlForms,
			$wgDonationInterfaceFormDirs;

		$form_dirs = $wgDonationInterfaceFormDirs;

		$moreForms = array ( );

		$moreForms['amazon'] = array(
			'gateway' => 'amazon',
			'payment_methods' => array( 'amazon' => 'ALL' ),
			'redirect',
		);

		$moreForms['cc-vmad'] = array (
			'file' => $form_dirs['gc'] . '/cc/cc-vmad.html',
			'gateway' => 'globalcollect',
			'payment_methods' => array ( 'cc' => array ( 'visa', 'mc', 'amex', 'discover' ) ),
			'countries' => array (
				'+' => array ( 'US', ),
			),
		);

		$moreForms['cc-vmaj'] = array (
			'file' => $form_dirs['gc'] . '/cc/cc-vmaj.html',
			'gateway' => 'globalcollect',
			'payment_methods' => array ( 'cc' => array ( 'visa', 'mc', 'amex', 'jcb' ) ),
			'countries' => array (
				'+' => array ( 'AD', 'AT', 'AU', 'BE', 'BH', 'DE', 'EC', 'ES', 'FI', 'FR', 'GB',
					'GF', 'GR', 'HK', 'IE', 'IT', 'JP', 'KR', 'LU', 'MY', 'NL', 'PR',
					'PT', 'SG', 'SI', 'SK', 'TH', 'TW', ),
			),
		);

		$moreForms['cc-vma'] = array (
			'file' => $form_dirs['gc'] . '/cc/cc-vma.html',
			'gateway' => 'globalcollect',
			'payment_methods' => array ( 'cc' => array ( 'visa', 'mc', 'amex' ) ),
			'countries' => array (
				// Array merge with cc-vmaj as fallback in case 'j' goes down
				// Array merge with cc-vmad as fallback in case 'd' goes down
				'+' => array_merge(
					$moreForms['cc-vmaj']['countries']['+'], $moreForms['cc-vmad']['countries']['+'], array ( 'AE', 'AL', 'AN', 'AR', 'BG', 'CA', 'CH', 'CN', 'CR', 'CY', 'CZ', 'DK',
					'DZ', 'EE', 'EG', 'JO', 'KE', 'HR', 'HU', 'IL', 'KW', 'KZ', 'LB', 'LI',
					'LK', 'LT', 'LV', 'MA', 'MT', 'NO', 'NZ', 'OM', 'PK', 'PL', 'QA', 'RO',
					'RU', 'SA', 'SE', 'TN', 'TR', 'UA', )
				)
			),
		);

		$moreForms['cc'] = array (
			'file' => $form_dirs['gc'] . '/cc/cc.html',
			'gateway' => 'globalcollect',
			'payment_methods' => array ( 'cc' => 'ALL' ),
			'countries' => array ( '-' => 'VN' )
		);

		$moreForms['rtbt-ideal'] = array (
			'file' => $form_dirs['gc'] . '/rtbt/rtbt-ideal.html',
			'gateway' => 'globalcollect',
			'payment_methods' => array ( 'rtbt' => 'rtbt_ideal' ),
			'countries' => array ( '+' => 'NL' ),
			'currencies' => array ( '+' => 'EUR' ),
		);

		$moreForms['rtbt-sofo'] = array(
			'file' => $form_dirs['gc'] . '/rtbt/rtbt-sofo.html',
			'gateway' => 'globalcollect',
			'countries' => array(
				'+' => array( 'AT', 'BE', 'CH', 'DE' ),
				'-' => 'GB'
			),
			'currencies' => array( '+' => 'EUR' ),
			'payment_methods' => array('rtbt' => 'rtbt_sofortuberweisung'),
		);

		$moreForms['paypal'] = array (
			'gateway' => 'paypal',
			'payment_methods' => array ( 'paypal' => 'ALL' ),
		);

		$this->setMwGlobals( array(
			'wgDonationInterfaceAllowedHtmlForms' => array_merge(
				$wgDonationInterfaceAllowedHtmlForms,
				$moreForms
			),
		) );
	}

	function testGetOneValidForm_CC_SpecificCountry() {
		$tests = array (
			0 => array (
				'country' => 'US',
				'payment_method' => 'cc',
				'currency' => 'USD',
				'expected' => 'cc-vmad'
			),
			1 => array (
				'country' => 'DK',
				'payment_method' => 'cc',
				'currency' => 'DKK',
				'expected' => 'cc-vma'
			),
		);

		foreach ( $tests as $testno => $data ) {
			$form = GatewayFormChooser::getOneValidForm( $data['country'], $data['currency'], $data['payment_method'] );
			$this->assertEquals( $data['expected'], $form, "$form is not the preferred option for " . $data['payment_method'] . ' in ' . $data['country'] );
		}
	}

	function testMaintenanceMode_Redirect() {

		$this->setMwGlobals( array(
			'wgContributionTrackingFundraiserMaintenance' => true,
		) );

		$expectedLocation = Title::newFromText('Special:FundraiserMaintenance')->getFullURL();
		$assertNodes = array(
			'headers' => array(
				'Location' => $expectedLocation
			),
		);
		$initial = array(
			'language' => 'en'
		);
		$this->verifyFormOutput( 'GatewayFormChooser', $initial, $assertNodes, false );
	}

	/**
	 * currency_code should take precedence over currency, payment_method
	 * over paymentmethod, etc.
	 */
	function testPreferCanonicalParams() {
		$self = $this; // someday, my upgrade will come
		$assertNodes = array(
			'headers' => array(
				'Location' => function( $val ) use ( $self ) {
					$qs = array();
					parse_str( parse_url( $val, PHP_URL_QUERY ), $qs );
					$self->assertEquals( 'paypal', $qs['ffname'], 'Wrong form' );
				}
			),
		);
		$initial = array(
			'language' => 'en',
			'payment_method' => 'paypal',
			'paymentmethod' => 'amazon',
			'country' => 'US',
		);
		$this->verifyFormOutput( 'GatewayFormChooser', $initial, $assertNodes, false );
	}
}


