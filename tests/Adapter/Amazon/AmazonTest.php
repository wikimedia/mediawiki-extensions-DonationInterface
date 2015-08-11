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
		global $wgAmazonGatewayHtmlFormDir;

		parent::setUp();

		$this->setMwGlobals( array(
			'wgAmazonGatewayEnabled' => true,
			'wgDonationInterfaceAllowedHtmlForms' => array(
				'amazon' => array(
					'file' => $wgAmazonGatewayHtmlFormDir . '/amazon.html',
					'gateway' => 'amazon',
					'payment_methods' => array('amazon' => 'ALL'),
					'redirect',
				),
				'amazon-recurring' => array(
					'file' => $wgAmazonGatewayHtmlFormDir . '/amazon-recurring.html',
					'gateway' => 'amazon',
					'payment_methods' => array('amazon' => 'ALL'),
					'redirect',
					'recurring',
				),
			),
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
		$this->markTestSkipped( 'Logic temporarily missing' );
		$init = $this->getDonorTestData( 'CA' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'amazon';
		$init['ffname'] = 'amazon';
		$init['language'] = $language;
		$init['redirect'] = 1;
		$donateText = wfMessage( 'donate_interface-donation-description' )->inLanguage( $language )->text();

		$rates = CurrencyRates::getCurrencyRates();
		$cadRate = $rates['CAD'];

		$expectedAmount = floor( $init['amount'] / $cadRate );

		TestingAmazonAdapter::$fakeGlobals = array(
			'FallbackCurrency' => 'USD',
			'NotifyOnConvert' => false,
		);
		$that = $this; //needed for PHP pre-5.4
		$redirectTest = function( $location ) use ( $expectedAmount, $donateText, $that ) {
			$actual = array();
			parse_str( $location, $actual );
			$that->assertTrue( is_numeric( $actual['amount'] ) );
			$difference = abs( floatval( $actual['amount'] ) - $expectedAmount );
			$that->assertTrue( $difference <= 1 );
			$that->assertEquals( $donateText, $actual['description'] );
		};

		$assertNodes = array(
			'headers' => array(
				'Location' => $redirectTest,
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
