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
 * @group Amazon
 */
class DonationInterface_Adapter_Amazon_Test extends DonationInterfaceTestCase {

	/**
	 * @param $name string The name of the test case
	 * @param $data array Any parameters read from a dataProvider
	 * @param $dataName string|int The name or index of the data set
	 */
	public function __construct( $name = null, array $data = array(), $dataName = '' ) {
		parent::__construct( $name, $data, $dataName );
		$this->testAdapterClass = 'TestingAmazonAdapter';
	}

	public function setUp() {
		parent::setUp();

		$this->setMwGlobals( array(
			'wgAmazonGatewayEnabled' => true,
			'wgDonationInterfaceAllowedHtmlForms' => array(
				'amazon' => array(
					'gateway' => 'amazon',
					'payment_methods' => array('amazon' => 'ALL'),
					'redirect',
				),
				'amazon-recurring' => array(
					'gateway' => 'amazon',
					'payment_methods' => array('amazon' => 'ALL'),
					'redirect',
					'recurring',
				),
			),
			'wgAmazonGatewayAccountInfo' => array( 'test' => array(
				'SellerID' => 'ABCDEFGHIJKL',
				'ClientID' => 'amzn1.application-oa2-client.1a2b3c4d5e',
				'ClientSecret' => '12432g134e3421a41234b1341c324123d',
				'MWSAccessKey' => 'N0NSENSEXYZ',
				'MWSSecretKey' => 'iuasd/2jhaslk2j49lkaALksdJLsJLas+',
				'Region' => 'us',
			) ),
		) );
	}

	public function tearDown() {
		TestingAmazonAdapter::$fakeGlobals = array();
		parent::tearDown();
	}

	/**
	 * Integration test to verify that the Amazon gateway converts Canadian
	 * dollars before redirecting
	 *
	 * @dataProvider canadaLanguageProvider
	 */
	function testCanadianDollarConversion( $language ) {
		$init = $this->getDonorTestData( 'CA' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'amazon';
		$init['ffname'] = 'amazon';
		$init['language'] = $language;
		$rates = CurrencyRates::getCurrencyRates();
		$cadRate = $rates['CAD'];

		$expectedAmount = floor( $init['amount'] / $cadRate );

		TestingAmazonAdapter::$fakeGlobals = array(
			'FallbackCurrency' => 'USD',
			'NotifyOnConvert' => true,
		);

		$expectedNotification = wfMessage(
			'donate_interface-fallback-currency-notice',
			'USD'
		)->inLanguage( $language )->text();

		$that = $this; //needed for PHP pre-5.4
		$convertTest = function( $amountString ) use ( $expectedAmount, $that ) {
			$actual = explode( ' ', trim( $amountString ) );
			$that->assertTrue( is_numeric( $actual[0] ) );
			$difference = abs( floatval( $actual[0] ) - $expectedAmount );
			$that->assertTrue( $difference <= 1 );
			$that->assertEquals( 'USD', $actual[1] );
		};

		$assertNodes = array(
			'selected-amount' => array( 'innerhtml' => $convertTest ),
			'mw-content-text' => array(
				'innerhtmlmatches' => "/.*$expectedNotification.*/"
			)
		);
		$this->verifyFormOutput( 'TestingAmazonGateway', $init, $assertNodes, false );
	}

	/**
	 * Integration test to verify that the Amazon gateway shows an error message when validation fails.
	 */
	function testShowFormOnError() {
		$init = $this->getDonorTestData();
		$init['OTT'] = 'SALT123456789';
		$init['amount'] = '-100.00';
		$init['ffname'] = 'amazon';
		$_SESSION['Donor'] = $init;
		$errorMessage = wfMessage('donate_interface-error-msg-field-correction', wfMessage('donate_interface-error-msg-amount')->text())->text();
		$assertNodes = array(
			'mw-content-text' => array(
				'innerhtmlmatches' => "/.*$errorMessage.*/"
			)
		);

		$this->verifyFormOutput( 'AmazonGateway', $init, $assertNodes, false );
	}

}
