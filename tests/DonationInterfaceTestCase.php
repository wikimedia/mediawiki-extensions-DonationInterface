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
 * @see TestHelper.php
 */
require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'TestHelper.php';

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
	
	/**
	 * This fetches test data to be used for gateway adapters.
	 *
	 * The returned result is populated with a test user from Spain, attempting
	 * a bank transfer for 350 EUR.
	 *
	 * Options that may need to be set:
	 * - adapter: (string) Defaults to TESTS_ADAPTER_DEFAULT
	 * - gateway: (string) Defaults to TESTS_GATEWAY_DEFAULT
	 * - test: (boolean) $test may be legacy code, use with caution.
	 *
	 *
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
	 * - zip:  
	 * - country: ES: 
	 * - //size: small
	 * - currency: EUR: 
	 * - payment_method:  
	 * - //order_id: 5038287830
	 * - //i_order_id: 1234567890
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
	 */
	public function getGatewayAdapterTestDataFromSpain( $options = array() ) {
		
		extract( $options );

		
		$adapter = isset( $adapter ) ? (string) $adapter : TESTS_ADAPTER_DEFAULT ;
		$gateway = isset( $gateway ) ? (string) $gateway : TESTS_GATEWAY_DEFAULT ;

		$form_name = isset( $form_name ) ? (string) $form_name : 'TwoStepAmount' ;

		// This is used to make sure the gateway and adapter match for unit testing.
		if ( $adapter == 'PayflowProAdapter' && $gateway == 'PayflowProGateway' ) {

			$gatewayAdapterMatch = true;
		}
		elseif ( $adapter == 'GlobalCollectAdapter' && $gateway == 'GlobalCollectGateway' ) {

			$gatewayAdapterMatch = true;
		}
		else {

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
		$amount				= isset( $amount )				? $amount : 350 ;
		$currency			= isset( $currency )			? (string) $currency : 'EUR' ;
		$language			= isset( $language )			? (string) $language	: 'en' ;

		$email		= isset( $email )		? (string) $email	: TESTS_EMAIL ;
		$fname		= isset( $fname )		? (string) $fname	: 'Testy' ;
		$lname		= isset( $lname )		? (string) $lname	: 'Testerton' ;
		$street		= isset( $street )		? (string) $street	: '123 Happy Street' ;
		$city		= isset( $city )		? (string) $city	: 'Barcelona' ;
		$state		= isset( $state )		? (string) $state	: 'XX' ;
		$zip		= isset( $zip )			? (string) $zip		: '' ;
		$country	= isset( $country )		? (string) $country	: 'ES' ;

		
		$return = array();
		
		$return['postDefaults'] = array(
			'returnTitle'	=> true,
			'returnTo'	=> 'http://' . TESTS_HOSTNAME . '/index.php/Special:' . $gateway . 'Result',
		);

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
			'currency' => $currency,
			//'order_id' => '5038287830',
			//'i_order_id' => '1234567890',
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
		
		if ( $gateway == 'GlobalCollectGateway' ) {
			$return['testData']['gateway'] = 'globalcollect';	
		}
		elseif ( $gateway == 'PayflowProGateway' ) {
			$return['testData']['gateway'] = 'payflowpro';	
		}
		
		return $return;
		
	}
}
