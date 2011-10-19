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
	 * testbuildRequestXML
	 *
	 * @todo
	 * - there are many cases to this that need to be developed.
	 * - Do not consider this a complete test!
	 *
	 * @covers GatewayAdapter::__construct
	 * @covers GatewayAdapter::do_transaction
	 * @covers GatewayAdapter::buildRequestXML
	 * @covers GatewayAdapter::getData
	 */
	public function testbuildRequestXML() {

		global $wgGlobalCollectGatewayTest;
		global $wgRequest;
		
		$wgGlobalCollectGatewayTest = true;

		$_SERVER = array();
		
		$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
		$_SERVER['HTTP_HOST'] = TESTS_HOSTNAME;
		$_SERVER['SERVER_NAME'] = TESTS_HOSTNAME;
		$_SERVER['REQUEST_URI'] = '/index.php/Special:GlobalCollectGateway?form_name=TwoStepAmount';


		$options = array();
		
		$options['test'] = true;
		$transactionType = 'BANK_TRANSFER';
		
		$amount = 350;
		
		$options['postDefaults'] = array(
			'returnTitle'	=> true,
			'returnTo'		=> 'http://' . TESTS_HOSTNAME . '/index.php/Special:GlobalCollectGatewayResult',
		);
		
		$options['testData'] = array(
			'amount' => $amount,
			'transaction_type' => $transactionType,
			'email' => TESTS_EMAIL,
			'fname' => 'Testy',
			'lname' => 'Testerton',
			'street' => '123 Happy Street',
			'city' => 'Barcelona',
			'state' => 'XX',
			'zip' => '',
			'country' => 'ES',
			//'size' => 'small',
			'currency' => 'EUR',
			'payment_method' => '',
			//'order_id' => '5038287830',
			//'i_order_id' => '1234567890',
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

		$result = $gateway->do_transaction( $transactionType );
		
		$request = trim( $gateway->buildRequestXML() );
		
		$orderId = $gateway->getData( 'order_id' );
		
		$expected  = '<?xml version="1.0"?>' . "\n";
		$expected .= '<XML><REQUEST><ACTION>INSERT_ORDERWITHPAYMENT</ACTION><META><MERCHANTID>6570</MERCHANTID><VERSION>1.0</VERSION></META><PARAMS><ORDER><ORDERID>' . $orderId . '</ORDERID><AMOUNT>' . $amount * 100 . '</AMOUNT><CURRENCYCODE>EUR</CURRENCYCODE><LANGUAGECODE>en</LANGUAGECODE><COUNTRYCODE>ES</COUNTRYCODE><MERCHANTREFERENCE>' . $orderId . '</MERCHANTREFERENCE></ORDER><PAYMENT><PAYMENTPRODUCTID>11</PAYMENTPRODUCTID><AMOUNT>35000</AMOUNT><CURRENCYCODE>EUR</CURRENCYCODE><LANGUAGECODE>en</LANGUAGECODE><COUNTRYCODE>ES</COUNTRYCODE><HOSTEDINDICATOR>1</HOSTEDINDICATOR><RETURNURL>http://wikimedia-fundraising-1.17.localhost.wikimedia.org/index.php/Special:GlobalCollectGatewayResult?order_id=' . $orderId . '</RETURNURL><FIRSTNAME>Testy</FIRSTNAME><SURNAME>Testerton</SURNAME><STREET>123 Happy Street</STREET><CITY>Barcelona</CITY><STATE>XX</STATE><EMAIL>jpostlethwaite@wikimedia.org</EMAIL></PAYMENT></PARAMS></REQUEST></XML>';
		//$expected .= '<XML><REQUEST><ACTION>Donate</ACTION><ACCOUNT><MERCHANTID>128</MERCHANTID><PASSWORD>k4ftw</PASSWORD><VERSION>3.2</VERSION><RETURNURL>http://' . TESTS_HOSTNAME . '/index.php/Donate-thanks/en</RETURNURL></ACCOUNT><DONATION><DONOR>Tester Testington</DONOR><AMOUNT>35000</AMOUNT><CURRENCYCODE>USD</CURRENCYCODE><LANGUAGECODE>en</LANGUAGECODE><COUNTRYCODE>US</COUNTRYCODE></DONATION></REQUEST></XML>' . "\n";
		$this->assertEquals($request, $expected, 'The constructed XML for transaction type [' . $transactionType . '] does not match our expected.');
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

		global $wgGlobalCollectGatewayTest;
		global $wgRequest;
		
		$wgGlobalCollectGatewayTest = true;

		$_SERVER = array();
		
		$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
		$_SERVER['HTTP_HOST'] = TESTS_HOSTNAME;
		$_SERVER['SERVER_NAME'] = TESTS_HOSTNAME;
		$_SERVER['REQUEST_URI'] = '/index.php/Special:GlobalCollectGateway?form_name=TwoStepAmount';

		$options = array();
		
		$options['test'] = true;
		$transactionType = 'BANK_TRANSFER';
		
		$options['postDefaults'] = array(
			'returnTitle'	=> true,
			'returnTo'		=> 'http://' . TESTS_HOSTNAME . '/index.php/Special:GlobalCollectGatewayResult',
		);

		$amount = 350;
		
		$options['testData'] = array(
			'amount' => $amount,
			'transaction_type' => $transactionType,
			'email' => TESTS_EMAIL,
			'fname' => 'Testy',
			'lname' => 'Testerton',
			'street' => '123 Happy Street',
			'city' => 'Barcelona',
			'state' => 'XX',
			'zip' => '',
			'country' => 'ES',
			//'size' => 'small',
			'currency' => 'EUR',
			'payment_method' => '',
			//'order_id' => '5038287830',
			//'i_order_id' => '1234567890',
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
		$result = $gateway->do_transaction( $transactionType );
	
		$this->assertTrue( $result );
	}
}

