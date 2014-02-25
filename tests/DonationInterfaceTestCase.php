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

require_once __DIR__ . '/TestConfiguration.php';

/**
 * @group		Fundraising
 * @group		QueueHandling
 * @group		ClassMethod
 * @group		ListenerAdapter
 *
 * @category	UnitTesting
 * @package		Fundraising_QueueHandling
 */
abstract class DonationInterfaceTestCase extends PHPUnit_Framework_TestCase
{
	protected $backupGlobalsBlacklist = array(
		'wgHooks',
	);

	/**
	 * This will be set by a test method with the adapter object.
	 *
	 * @var GatewayAdapter	$gatewayAdapter
	 */
	protected $gatewayAdapter;

	/**
	 * buildRequestXmlForGlobalCollect
	 *
	 * @todo
	 * - there are many cases to this that need to be developed.
	 * - Do not consider this a complete test!
	 *
	 * @covers GatewayAdapter::__construct
	 * @covers GatewayAdapter::setCurrentTransaction
	 * @covers GatewayAdapter::buildRequestXML
	 */
	public function buildRequestXmlForGlobalCollect( $optionsForTestData, $options ) {

		global $wgGlobalCollectGatewayTest;
		
		$wgGlobalCollectGatewayTest = true;

		$this->gatewayAdapter = new GlobalCollectTestAdapter( $options );

		$this->gatewayAdapter->setCurrentTransaction('INSERT_ORDERWITHPAYMENT');

		$request = trim( $this->gatewayAdapter->executeBuildRequestXML() );

		$expected = $this->getExpectedXmlRequestForGlobalCollect( $optionsForTestData, $options );
		
		$this->assertEquals($expected, $request, 'The constructed XML for payment_method [' . $optionsForTestData['payment_method'] . '] and payment_submethod [' . $optionsForTestData['payment_submethod'] . '] does not match our expected request.');
	}

	/**
	 * This fetches test data to be used for gateway adapters.
	 *
	 * This method also sets up $_SERVER
	 *
	 * The returned result is populated with a test user from Spain, attempting
	 * a bank transfer for 350 EUR.
	 *
	 * @param array    $options
	 *
	 * Options that may need to be set:
	 * - adapter: (string) Defaults to TESTS_ADAPTER_DEFAULT
	 * - gateway: (string) Defaults to TESTS_GATEWAY_DEFAULT
	 * - test: (boolean) $test may be legacy code, use with caution.
	 *
	 * This test data has these defaults:
	 * - amount: Amount is set to an integer, by default, for the amount of 350
	 * - payment_method: bt
	 * - payment_submethod: bt
	 * - email: TESTS_EMAIL
	 * - fname: Testy:
	 * - lname: Testerton:
	 * - street: 123 Happy Street:
	 * - city: Barcelona:
	 * - state: XX:
	 * - zip: 0
	 * - country: ES:
	 * - //size: small
	 * - currency: EUR:
	 * - payment_method:
	 * - //order_id: 5038287830
	 * - numAttempt: 0
	 * - referrer: http:// . TESTS_HOSTNAME . /index.php/Special:GlobalCollectGateway?form_name=TwoStepAmount:
	 * - utm_source: ..gc_bt:
	 * - utm_medium: null
	 * - utm_campaign: null
	 * - language: en:
	 * - comment-option:
	 * - comment:
	 * - email-opt: 1
	 * - test_string:
	 * - token:
	 * - contribution_tracking_id:
	 * - data_hash:
	 * - action:
	 * - gateway: globalcollect:
	 * - owa_session:
	 * - owa_ref: http://localhost/defaultTestData
	 *
	 * @throws Exception
	 * @return array    Contains: testData
	 */
	public function getGatewayAdapterTestData( $options = array() ) {
		
		extract( $options );

		
		$adapter = isset( $adapter ) ? (string) $adapter : TESTS_ADAPTER_DEFAULT ;
		$gateway = isset( $gateway ) ? (string) $gateway : TESTS_GATEWAY_DEFAULT ;

		if ( !class_exists( $adapter ) ) {
			$message = 'Adapter "' . $adapter . '" does not seem to exist...';
			throw new Exception( $message );
		}

		if ( !class_exists( $gateway ) ) {
			$message = 'Gateway "' . $gateway . '" does not seem to exist...';
			throw new Exception( $message );
		}

		$testAdapter = new $adapter();
		$testGateway = new $gateway();

		$form_name = isset( $form_name ) ? (string) $form_name : 'TwoStepAmount' ;

		// This is used to make sure the gateway and adapter match for unit testing.
		if ( is_a( $testAdapter, 'PayflowProAdapter' ) && is_a( $testGateway, 'PayflowProGateway' ) ) {
			$gatewayAdapterMatch = true;
		} elseif ( is_a( $testAdapter, 'GlobalCollectAdapter' ) && is_a( $testGateway, 'GlobalCollectGateway' ) ) {
			$gatewayAdapterMatch = true;
		} else {
			$gatewayAdapterMatch = false;
		}
		
		if ( !$gatewayAdapterMatch ) {
			$message = 'Gateway (' . $gateway . ') does not match the adapter (' . $adapter . ').';
			throw new Exception( $message );
		}

		// $test may be legacy code, use with caution.
		$test = isset( $test ) ? (boolean) $test : true ;
		$return['test'] = $test;
		
		$payment_method		= isset( $payment_method )		? (string) $payment_method : 'bt' ;
		$payment_submethod	= isset( $payment_submethod )	? (string) $payment_submethod : 'bt' ;
		$issuer_id			= isset( $issuer_id )			? (string) $issuer_id : '' ;
		$amount				= isset( $amount )				? $amount : 350 ;
		$currency			= isset( $currency )			? (string) $currency : 'EUR' ;
		$language			= isset( $language )			? (string) $language	: 'en' ;

		$email		= isset( $email )		? (string) $email	: TESTS_EMAIL ;
		$fname		= isset( $fname )		? (string) $fname	: 'Testy' ;
		$lname		= isset( $lname )		? (string) $lname	: 'Testerton' ;
		$street		= isset( $street )		? (string) $street	: '123 Happy Street' ;
		$city		= isset( $city )		? (string) $city	: 'Barcelona' ;
		$state		= isset( $state )		? (string) $state	: 'XX' ;
		$zip		= isset( $zip )			? (string) $zip		: '0' ;
		$country	= isset( $country )		? (string) $country	: 'ES' ;

		
		$return = array();

		$return['testData'] = array(
			'amount' => $amount,
			'payment_method' => $payment_method,
			'payment_submethod' => $payment_submethod,
			'email' => $email,
			'fname' => $fname,
			'lname' => $lname,
			'street' => $street,
			'city' => $city,
			'state' => $state,
			'zip' => $zip,
			'country' => $country,
			//'size' => 'small',
			'currency_code' => $currency,
			//'order_id' => '5038287830',
			'numAttempt' => 0,
			'referrer' => 'http://' . TESTS_HOSTNAME . '/index.php/Special:' . $gateway . '?form_name=' . $form_name,
			'utm_source' => '..gc_bt',
			'utm_medium' => null,
			'utm_campaign' => null,
			'language' => $language,
			'comment-option' => '',
			'comment' => '',
			'email-opt' => 1,
			'test_string' => '',
			'token' => '',
			'contribution_tracking_id' => '',
			'data_hash' => '',
			'action' => '',
			'gateway' => '',
			'owa_session' => '',
			'owa_ref' => 'http://localhost/defaultTestData',
		);
		
		// Set the issuer id if available
		if ( !empty( $issuer_id ) ) {
			
			$return['testData']['issuer_id'] = $issuer_id;
		}
		
		// Set the gateway
		if ( $gateway == 'GlobalCollectGateway' ) {
			$return['testData']['gateway'] = 'globalcollect';	
		}
		elseif ( $gateway == 'PayflowProGateway' ) {
			$return['testData']['gateway'] = 'payflowpro';	
		}

		return $return;
	}

	/**
	 * This fetches test data to be used for gateway adapters.
	 *
	 * The returned result is populated with a test user from Spain, attempting
	 * a bank transfer for 350 EUR.
	 *
	 * If you need more locations to test, implement another method like this
	 * one, overriding options as needed.
	 *
	 * Use the naming conventions with:
	 * - From<Location>
	 * - Using<BankTransfer>
	 *
	 * The above parameters would map to: getGatewayAdapterTestDataFromSpainUsingBankTransfer()
	 *
 	 * @see DonationInterfaceTestCase::getGatewayAdapterTestData()
	 */
	public function getGatewayAdapterTestDataFromSpain( $options = array() ) {
		
		$options['city']		= 'Barcelona';
		$options['state']		= 'XX';
		$options['zip']			= '0';
		$options['country']		= 'ES';
		$options['currency_code']	= 'EUR';
		
		return $this->getGatewayAdapterTestData( $options );
	}

	/**
	 * Get the expected XML request from GlobalCollect
	 *
	 * @param $optionsForTestData
	 * @param array $options
	 * @return string    The expected XML request
	 */
	public function getExpectedXmlRequestForGlobalCollect( $optionsForTestData, $options = array() ) {
		global $wgRequest, $wgServer, $wgArticlePath, $wgDonationInterfaceThankYouPage;

		$orderId = $this->gatewayAdapter->getData_Unstaged_Escaped( 'order_id' );

		$expected  = '<?xml version="1.0"?>' . "\n";
		$expected .= '<XML>';
		$expected .= 	'<REQUEST>';
		$expected .= 		'<ACTION>INSERT_ORDERWITHPAYMENT</ACTION>';
		$expected .= 		'<META><MERCHANTID>' . $this->gatewayAdapter->getGlobal( 'MerchantID' ) . '</MERCHANTID>';

		if ( isset( $wgRequest ) ) {
			$expected .=		'<IPADDRESS>' . $wgRequest->getIP() . '</IPADDRESS>';
		}
		
		$expected .=			'<VERSION>1.0</VERSION>';
		$expected .=		'</META>';
		$expected .= 		'<PARAMS>';
		$expected .= 			'<ORDER>';
		$expected .= 				'<ORDERID>' . $orderId . '</ORDERID>';
		$expected .= 				'<AMOUNT>' . $options['testData']['amount'] * 100 . '</AMOUNT>';
		$expected .= 				'<CURRENCYCODE>' . $options['testData']['currency_code'] . '</CURRENCYCODE>';
		$expected .= 				'<LANGUAGECODE>' . $options['testData']['language'] . '</LANGUAGECODE>';
		$expected .= 				'<COUNTRYCODE>' . $options['testData']['country'] . '</COUNTRYCODE>';
		$expected .= 				'<MERCHANTREFERENCE>' . $orderId . '</MERCHANTREFERENCE>';

		if ( isset( $wgRequest ) ) {
			$expected .=			'<IPADDRESSCUSTOMER>' . $wgRequest->getIP() . '</IPADDRESSCUSTOMER>';
		}

		$expected .=				'<EMAIL>' . TESTS_EMAIL . '</EMAIL>';
		$expected .= 			'</ORDER>';
		$expected .= 			'<PAYMENT>';
		$expected .= 				'<PAYMENTPRODUCTID>' . $optionsForTestData['payment_product_id'] . '</PAYMENTPRODUCTID>';
		$expected .= 				'<AMOUNT>' . $options['testData']['amount'] * 100 . '</AMOUNT>';
		$expected .= 				'<CURRENCYCODE>' . $options['testData']['currency_code'] . '</CURRENCYCODE>';
		$expected .= 				'<LANGUAGECODE>' . $options['testData']['language'] . '</LANGUAGECODE>';
		$expected .= 				'<COUNTRYCODE>' . $options['testData']['country'] . '</COUNTRYCODE>';
		$expected .= 				'<HOSTEDINDICATOR>1</HOSTEDINDICATOR>';
		$expected .= 				'<RETURNURL>' . $wgServer . preg_replace( '#\\$1#', $wgDonationInterfaceThankYouPage . '/' . $options['testData']['language'], $wgArticlePath ) . '</RETURNURL>';
		$expected .=				'<AUTHENTICATIONINDICATOR>0</AUTHENTICATIONINDICATOR>';
		$expected .= 				'<FIRSTNAME>' . $options['testData']['fname'] . '</FIRSTNAME>';
		$expected .= 				'<SURNAME>' . $options['testData']['lname'] . '</SURNAME>';
		$expected .= 				'<STREET>' . $options['testData']['street'] . '</STREET>';
		$expected .= 				'<CITY>' . $options['testData']['city'] . '</CITY>';
		$expected .= 				'<STATE>' . $options['testData']['state'] . '</STATE>';
		$expected .= 				'<ZIP>' . $options['testData']['zip'] . '</ZIP>';
		$expected .= 				'<EMAIL>' . TESTS_EMAIL . '</EMAIL>';

		// Set the issuer id if it is passed.
		if ( isset( $optionsForTestData['issuer_id'] ) ) {
			$expected .= 				'<ISSUERID>' . $optionsForTestData['issuer_id'] . '</ISSUERID>';
		}
		
		$expected .= 			'</PAYMENT>';
		$expected .= 		'</PARAMS>';
		$expected .= 	'</REQUEST>';
		$expected .= '</XML>';
		
		return $expected;
		
	}
}
