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

	/**
	 * testBuildRequestXml
	 *
	 * @todo
	 * - there are many cases to this that need to be developed.
	 * - Do not consider this a complete test!
	 *
	 * @covers GatewayAdapter::__construct
	 * @covers GatewayAdapter::currentTransaction
	 * @covers GatewayAdapter::buildRequestXML
	 * @covers GatewayAdapter::getData
	 */
	public function testBuildRequestXml() {

		global $wgGlobalCollectGatewayTest;

		$wgGlobalCollectGatewayTest = true;

		$_SERVER = array();

		$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
		$_SERVER['HTTP_HOST'] = TESTS_HOSTNAME;
		$_SERVER['SERVER_NAME'] = TESTS_HOSTNAME;
		$_SERVER['REQUEST_URI'] = '/index.php/Special:GlobalCollectGateway?form_name=TwoStepAmount';

		$payment_product_id = 11;
		
		$optionsForTestData = array(
			'form_name' => 'TwoStepAmount',
			'payment_method' => 'bt',
			'payment_submethod' => 'bt',
		);

		$options = $this->getGatewayAdapterTestDataFromSpain( $optionsForTestData );
		
		$gateway = new GlobalCollectAdapter( $options );

		$gateway->currentTransaction('INSERT_ORDERWITHPAYMENT');

		$request = trim( $gateway->buildRequestXML() );

		$orderId = $gateway->getData( 'order_id' );

		$expected  = '<?xml version="1.0"?>' . "\n";
		$expected .= '<XML>';
		$expected .= 	'<REQUEST>';
		$expected .= 		'<ACTION>INSERT_ORDERWITHPAYMENT</ACTION>';
		$expected .= 		'<META><MERCHANTID>' . $gateway->getGatewayMerchantId() . '</MERCHANTID><VERSION>1.0</VERSION></META>';
		$expected .= 		'<PARAMS>';
		$expected .= 			'<ORDER>';
		$expected .= 				'<ORDERID>' . $orderId . '</ORDERID>';
		$expected .= 				'<AMOUNT>' . $options['testData']['amount'] * 100 . '</AMOUNT>';
		$expected .= 				'<CURRENCYCODE>' . $options['testData']['currency'] . '</CURRENCYCODE>';
		$expected .= 				'<LANGUAGECODE>' . $options['testData']['language'] . '</LANGUAGECODE>';
		$expected .= 				'<COUNTRYCODE>' . $options['testData']['country'] . '</COUNTRYCODE>';
		$expected .= 				'<MERCHANTREFERENCE>' . $orderId . '</MERCHANTREFERENCE>';
		$expected .= 			'</ORDER>';
		$expected .= 			'<PAYMENT>';
		$expected .= 				'<PAYMENTPRODUCTID>' . $payment_product_id . '</PAYMENTPRODUCTID>';
		$expected .= 				'<AMOUNT>' . $options['testData']['amount'] * 100 . '</AMOUNT>';
		$expected .= 				'<CURRENCYCODE>' . $options['testData']['currency'] . '</CURRENCYCODE>';
		$expected .= 				'<LANGUAGECODE>' . $options['testData']['language'] . '</LANGUAGECODE>';
		$expected .= 				'<COUNTRYCODE>' . $options['testData']['country'] . '</COUNTRYCODE>';
		$expected .= 				'<HOSTEDINDICATOR>1</HOSTEDINDICATOR>';
		$expected .= 				'<RETURNURL>http://' . TESTS_HOSTNAME . '/index.php/Special:GlobalCollectGatewayResult?order_id=' . $orderId . '</RETURNURL>';
		$expected .= 				'<FIRSTNAME>' . $options['testData']['fname'] . '</FIRSTNAME>';
		$expected .= 				'<SURNAME>' . $options['testData']['lname'] . '</SURNAME>';
		$expected .= 				'<STREET>' . $options['testData']['street'] . '</STREET>';
		$expected .= 				'<CITY>' . $options['testData']['city'] . '</CITY>';
		$expected .= 				'<STATE>' . $options['testData']['state'] . '</STATE>';
		$expected .= 				'<EMAIL>' . TESTS_EMAIL . '</EMAIL>';
		$expected .= 			'</PAYMENT>';
		$expected .= 		'</PARAMS>';
		$expected .= 	'</REQUEST>';
		$expected .= '</XML>';
		
		$this->assertEquals($expected, $request, 'The constructed XML for paymentmethod [' . $optionsForTestData['payment_method'] . '] does not match our expected.');
	}

	/**
	 * testSendToGlobalCollect
	 *
	 * Adding
	 * @covers GatewayAdapter::__construct
	 * @covers GatewayAdapter::currentTransaction
	 * @covers GatewayAdapter::do_transaction
	 * @covers GatewayAdapter::buildRequestXML
	 * @covers GatewayAdapter::getData
	 */
	public function testSendToGlobalCollect() {
		$this->markTestIncomplete( TESTS_MESSAGE_NOT_IMPLEMENTED );

		global $wgGlobalCollectGatewayTest;

		$wgGlobalCollectGatewayTest = true;

		$_SERVER = array();

		$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
		$_SERVER['HTTP_HOST'] = TESTS_HOSTNAME;
		$_SERVER['SERVER_NAME'] = TESTS_HOSTNAME;
		$_SERVER['REQUEST_URI'] = '/index.php/Special:GlobalCollectGateway?form_name=TwoStepAmount';

		$payment_product_id = 11;
		
		$optionsForTestData = array(
			'form_name' => 'TwoStepAmount',
			'payment_method' => 'bt',
			'payment_submethod' => 'bt',
		);

		$options = $this->getGatewayAdapterTestDataFromSpain( $optionsForTestData );

		$gateway = new GlobalCollectAdapter( $options );
		$result = $gateway->do_transaction( $transactionType );

		// This will never assert to true. Another type of assertion will be necessary.
		$this->assertTrue( $result );
	}
}

