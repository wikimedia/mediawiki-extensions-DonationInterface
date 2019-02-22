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
	 * @param string $name The name of the test case
	 * @param array $data Any parameters read from a dataProvider
	 * @param string|int $dataName The name or index of the data set
	 */
	public function __construct( $name = null, array $data = [], $dataName = '' ) {
		$adapterclass = TESTS_ADAPTER_DEFAULT;
		$this->testAdapterClass = $adapterclass;

		parent::__construct( $name, $data, $dataName );
	}

	public function setUp() {
		parent::setUp();

		$this->setMwGlobals( [
			'wgDonationInterfaceEnableFormChooser' => true,
			'wgIngenicoGatewayEnabled' => true,
			'wgPaypalGatewayEnabled' => true,
		] );
	}

	function testGetOneValidForm_CC_SpecificCountry() {
		$tests = [
			0 => [
				'country' => 'US',
				'payment_method' => 'cc',
				'currency' => 'USD',
				'expected' => 'cc-vmad'
			],
			1 => [
				'country' => 'DK',
				'payment_method' => 'cc',
				'currency' => 'DKK',
				'expected' => 'cc-vma'
			],
		];

		foreach ( $tests as $testno => $data ) {
			$form = GatewayFormChooser::getOneValidForm( $data['country'], $data['currency'], $data['payment_method'] );
			$this->assertEquals( $data['expected'], $form, "$form is not the preferred option for " . $data['payment_method'] . ' in ' . $data['country'] );
		}
	}

	function testMaintenanceMode_Redirect() {
		$this->setMwGlobals( [
			'wgContributionTrackingFundraiserMaintenance' => true,
		] );

		$expectedLocation = Title::newFromText( 'Special:FundraiserMaintenance' )->getFullURL( '', false, PROTO_CURRENT );
		$assertNodes = [
			'headers' => [
				'Location' => $expectedLocation
			],
		];
		$initial = [
			'language' => 'en'
		];
		$this->verifyFormOutput( 'GatewayFormChooser', $initial, $assertNodes, false );
	}

	/**
	 * currency should take precedence over currency_code, payment_method
	 * over paymentmethod, etc.
	 */
	function testPreferCanonicalParams() {
		$assertNodes = [
			'headers' => [
				'Location' => function ( $val ) {
					$qs = [];
					parse_str( parse_url( $val, PHP_URL_QUERY ), $qs );
					$this->assertEquals( 'paypal', $qs['ffname'], 'Wrong form' );
				}
			],
		];
		$initial = [
			'language' => 'en',
			'payment_method' => 'paypal',
			'paymentmethod' => 'amazon',
			'country' => 'US',
		];
		$this->verifyFormOutput( 'GatewayFormChooser', $initial, $assertNodes, false );
	}

	/**
	 * Make sure none of the payment form settings are horribly broken.
	 */
	function testBuildAllFormUrls() {
		global $wgDonationInterfaceAllowedHtmlForms;
		foreach ( $wgDonationInterfaceAllowedHtmlForms as $ffname => $config ) {
			if ( empty( $config['special_type'] ) || $config['special_type'] != 'error' ) {
				$url = GatewayFormChooser::buildPaymentsFormURL( $ffname );
				$this->assertNotNull( $url );
			}
		}
	}
}
