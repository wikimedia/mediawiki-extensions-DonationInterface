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
 * @since		r98249
 * @author		Jeremy Postlethwaite <jpostlethwaite@wikimedia.org>
 */

/**
 * @see DonationInterfaceTestCase
 */
require_once dirname( dirname( dirname( __FILE__ ) ) ) . DIRECTORY_SEPARATOR . 'DonationInterfaceTestCase.php';

/**
 * 
 * @group Fundraising
 * @group Gateways
 * @group DonationInterface
 * @group GlobalCollect
 * @group BankTransfer
 */
class DonationInterface_Adapter_GlobalCollect_BankTransferTestCase extends DonationInterfaceTestCase {

	/*
	Acceptance Criteria
		308.1 *Given that a donor wants to donate via an offline bank transfer
			When they submit the following information:
				Amount
				Country (Required – use ISO 3166 codes)
				First Name
				Surname
				Street Address
				Zip
				City
				State (optional)
			Then an XML submission is created and sent to Global Collect with the above information AND:
				MERCHANTREFERENCE (contribution tracking id)
				curencycode
		308.2
			GIven that a donation order was submitted with the above information
			When the submit button is pressed and a response recieved from Global Collect
			The response is parased and the following information is displayed to the donor:
				PAYMENTREFERENCE
				Account Holder
				Bank Name
				City
				Swift Code
				SpecialID (if provided)
				Bank Account Number
				IBAN
				CountryDescription
				Notes
			We do not need to have donor information stored on our side yet as long as it is sent to Global Collect	
	*/

	/**
	 * Copied from Katie's test code
	 */
	public function testbuildRequestXML() {

		$this->markTestIncomplete( TESTS_MESSAGE_NOT_IMPLEMENTED );

		return;
		
		$gateway = new TestAdapter();
		$gateway->publicCurrentTransaction( 'Test1' );
		$built = $gateway->buildRequestXML();
		$expected = '<?xml version="1.0"?>' . "\n";
		$expected .= '<XML><REQUEST><ACTION>Donate</ACTION><ACCOUNT><MERCHANTID>128</MERCHANTID><PASSWORD>k4ftw</PASSWORD><VERSION>3.2</VERSION><RETURNURL>http://' . TESTS_HOSTNAME . '/index.php/Donate-thanks/en</RETURNURL></ACCOUNT><DONATION><DONOR>Tester Testington</DONOR><AMOUNT>35000</AMOUNT><CURRENCYCODE>USD</CURRENCYCODE><LANGUAGECODE>en</LANGUAGECODE><COUNTRYCODE>US</COUNTRYCODE></DONATION></REQUEST></XML>' . "\n";
		$this->assertEquals($built, $expected, "The constructed XML for transaction type Test1 does not match our expected.");
		
	}
	

	/**
	 * testRequestHasRequiredFields
	 */
	public function testRequestHasRequiredFields() {

		$this->markTestIncomplete( TESTS_MESSAGE_NOT_IMPLEMENTED );

	}

	/**
	 * testReturnDonorResponse
	 */
	public function testReturnDonorResponse() {

		$this->markTestIncomplete( TESTS_MESSAGE_NOT_IMPLEMENTED );

	}

	/**
	 * testSendToGlobalCollect
	 *
	 * Adding
	 */
	public function testSendToGlobalCollect() {
		$this->markTestIncomplete( TESTS_MESSAGE_NOT_IMPLEMENTED );

		//global $wgContLang, $wgAuth, $wgMemc, $wgRequest, $wgUser, $wgServer;
		global $wgGlobalCollectGatewayTest;
		
		$wgGlobalCollectGatewayTest = true;

		$_SERVER = array();
		
		$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
		$_SERVER['HTTP_HOST'] = TESTS_HOSTNAME;
		$_SERVER['SERVER_NAME'] = TESTS_HOSTNAME;
		$_SERVER['REQUEST_URI'] = '/index.php/Special:GlobalCollectGateway?form_name=TwoStepAmount';
		
		/**
		 * @see WebStart.php
		 */
		require_once TESTS_WEB_ROOT . '/includes/WebStart.php';

		$options = array();
		
		$options['test'] = true;
		$options['transactionType'] = 'BANK_TRANSFER';
		
		$options['postDefaults'] = array(
			'returnTitle'	=> true,
			'returnTo'		=> 'http://' . TESTS_HOSTNAME . '/index.php/Special:GlobalCollectGatewayResult',
		);
		
		$options['testData'] = array(
			'amount' => "35",
			'amountOther' => '',
			'email' => 'test@example.com',
			'fname' => 'Tester',
			'mname' => 'T.',
			'lname' => 'Testington',
			'street' => '548 Market St.',
			'city' => 'San Francisco',
			'state' => 'CA',
			'zip' => '94104',
			'country' => 'US',
			'fname2' => 'Testy',
			'lname2' => 'Testerson',
			'street2' => '123 Telegraph Ave.',
			'city2' => 'Berkeley',
			'state2' => 'CA',
			'zip2' => '94703',
			'country2' => 'US',
			'size' => 'small',
			'premium_language' => 'es',
			//'card_num' => TESTS_CREDIT_CARDS_AMEREICAN_EXPRESS_VALID_CARD,
			//'card_type' => 'american',
			//'expiration' => date( 'my', strtotime( '+1 year 1 month' ) ),
			//'cvv' => '001',
			'currency' => 'USD',
			'payment_method' => '',
			'order_id' => '1234567890',
			'i_order_id' => '1234567890',
			'numAttempt' => 0,
			'referrer' => 'http://' . TESTS_HOSTNAME . '/index.php/Special:GlobalCollectGateway?form_name=TwoStepAmount',
			'utm_source' => '..gc_bt',
			'utm_medium' => null,
			'utm_campaign' => null,
			'language' => 'en',
			'comment-option' => '',
			'comment' => '',
			'email-opt' => 1,
			'test_string' => '',
			'token' => '',
			'contribution_tracking_id' => '',
			'data_hash' => '',
			'action' => '',
			'gateway' => 'globalcollect',
			'owa_session' => '',
			'owa_ref' => 'http://localhost/defaultTestData',
			'transaction_type' => '', // Used by GlobalCollect for payment types
		);
		
		$gateway = new GlobalCollectAdapter( $options );
		return $gateway->do_transaction( $options['transactionType'] );
	}
}

