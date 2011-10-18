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
 * @author Katie Horn <khorn@wikimedia.org>
 */

/**
 * @see DonationInterfaceTestCase
 */
require_once dirname( dirname( __FILE__ ) ) . DIRECTORY_SEPARATOR . 'DonationInterfaceTestCase.php';

/**
 * TODO: Test everything. 
 * Make sure all the basic functions in the gateway_adapter are tested here. 
 * Also, the extras and their hooks firing properly and... that the fail score 
 * they give back is acted upon in the way we think it does. 
 * Hint: For that mess, use GatewayAdapter's $debugarray
 * 
 * Also, note that it barely makes sense to test the functions that need to be 
 * defined in each gateway as per the abstract class. If we did that here, we'd 
 * basically be just testing the test code. So, don't do it. 
 * Those should definitely be tested in the various gateway-specific test 
 * classes. 
 * 
 * @group Fundraising
 * @group Splunge
 * @group Gateways
 * @group DonationInterface
 */
class DonationInterface_Adapter_GatewayAdapterTestCase extends DonationInterfaceTestCase {

	/**
	 *
	 * @covers GatewayAdapter::__construct
	 * @covers GatewayAdapter::defineVarMap
	 * @covers GatewayAdapter::defineReturnValueMap
	 * @covers GatewayAdapter::defineTransactions
	 */
	public function testbuildRequestXML() {
		$gateway = new TestAdapter();
		$gateway->publicCurrentTransaction( 'Test1' );
		$built = $gateway->buildRequestXML();
		$expected = '<?xml version="1.0"?>' . "\n";
		$expected .= '<XML><REQUEST><ACTION>Donate</ACTION><ACCOUNT><MERCHANTID>128</MERCHANTID><PASSWORD>k4ftw</PASSWORD><VERSION>3.2</VERSION><RETURNURL>http://' . TESTS_HOSTNAME . '/index.php/Special:GlobalCollectGatewayResult</RETURNURL></ACCOUNT><DONATION><DONOR>Tester Testington</DONOR><AMOUNT>35000</AMOUNT><CURRENCYCODE>USD</CURRENCYCODE><LANGUAGECODE>en</LANGUAGECODE><COUNTRYCODE>US</COUNTRYCODE></DONATION></REQUEST></XML>' . "\n";
		$this->assertEquals($built, $expected, "The constructed XML for transaction type Test1 does not match our expected.");
		
	}
	
	/**
	 *
	 */
	public function testParseResponseStatusXML() {
		
		$returned = $this->getTestGatewayTransactionTest2Results();
		$this->assertEquals($returned['status'], true, "Status should be true at this point.");
	}
	
	/**
	 *
	 */
	public function testParseResponseErrorsXML() {
		
		$returned = $this->getTestGatewayTransactionTest2Results();
		$expected_errors = array(
			128 => "Your shoe's untied...",
			45 => "Low clearance!"
		);
		$this->assertEquals($returned['errors'], $expected_errors, "Expected errors were not found.");
				
	}
	
	/**
	 *
	 */
	public function testParseResponseDataXML() {
		
		$returned = $this->getTestGatewayTransactionTest2Results();
		$expected_data = array(
			'thing' => 'stuff',
			'otherthing' => 12,
		);
		$this->assertEquals($returned['data'], $expected_data, "Expected data was not found.");
				
	}
	
	/**
	 *
	 */
	public function testResponseMessage() {
		
		$returned = $this->getTestGatewayTransactionTest2Results();
		$this->assertEquals($returned['message'], "Test2 Transaction Successful!", "Expected message was not returned.");
				
	}
	
	/**
	 *
	 */
	public function testGetGlobal(){
		$gateway = new TestAdapter();
		$found = $gateway::getGlobal("TestVar");
		$expected = "Hi there!";
		$this->assertEquals($found, $expected, "getGlobal is not functioning properly.");
	}
	
	
	/**
	 *
	 */
	public function getTestGatewayTransactionTest2Results(){
		$gateway = new TestAdapter();
		return $gateway->do_transaction( 'Test2' );
	}

}

/**
 * Test Adapter
 */
class TestAdapter extends GatewayAdapter {

	const GATEWAY_NAME = 'Test Gateway';
	const IDENTIFIER = 'testgateway';
	const COMMUNICATION_TYPE = 'xml';
	const GLOBAL_PREFIX = 'wgTestAdapterGateway';

	/**
	 *
	 */
	public function stageData( $type = 'request' ){
		$this->postdata['amount'] = $this->postdata['amount'] * 1000;
		$this->postdata['name'] = $this->postdata['fname'] . " " . $this->postdata['lname'];
	}
	
	/**
	 *
	 */
	public function __construct( ) {
		global $wgTestAdapterGatewayTestVar, $wgTestAdapterGatewayUseSyslog, $wgTestAdapterGatewayTest;
		$wgTestAdapterGatewayTest = true;
		$wgTestAdapterGatewayTestVar = "Hi there!";
		$wgTestAdapterGatewayUseSyslog = true;
		parent::__construct();
		
	}
	
	/**
	 *
	 */
	public function defineAccountInfo(){
		$this->accountInfo = array(
			'MERCHANTID' => '128',
			'PASSWORD' => 'k4ftw',
			//'IPADDRESS' => '', //TODO: Not sure if this should be OUR ip, or the user's ip. Hurm. 
			'VERSION' => "3.2",
		);
	}
	
	/**
	 *
	 */
	public function defineStagedVars(){
	}
	
	/**
	 *
	 */
	public function defineVarMap(){
		$this->var_map = array(
			'DONOR' => 'name',
			'AMOUNT' => 'amount',
			'CURRENCYCODE' => 'currency',
			'LANGUAGECODE' => 'language',
			'COUNTRYCODE' => 'country',
			'OID' => 'order_id',
			'RETURNURL' => 'returnto', //TODO: Fund out where the returnto URL is supposed to be coming from. 
		);
	}
	
	/**
	 *
	 */
	public function defineReturnValueMap(){
		$this->return_value_map = array(
			'AOK' => true,
			'WRONG' => false,
		);
	}
	
	/**
	 *
	 */
	public function defineTransactions(){
		$this->transactions = array();
		
		$this->transactions['Test1'] = array(
			'request' => array(
				'REQUEST' => array(
					'ACTION',
					'ACCOUNT' => array(
						'MERCHANTID',
						'PASSWORD',
						'VERSION',
						'RETURNURL',
					),
					'DONATION' => array(
						'DONOR',
						'AMOUNT',
						'CURRENCYCODE',
						'LANGUAGECODE',
						'COUNTRYCODE',
						//'OID', //move this to another test. It's different every time, dorkus.
					)
				)
			),
			'values' => array(
				'ACTION' => 'Donate',
			),
		);
		
		$this->transactions['Test2'] = array(
			'request' => array(
				'REQUEST' => array(
					'ACTION',
				)
			),
			'values' => array(
				'ACTION' => 'Donate2',
			),
		);
	}

	/**
	 * Take the entire response string, and strip everything we don't care about.
	 * For instance: If it's XML, we only want correctly-formatted XML. Headers must be killed off. 
	 * return a string.
	 */
	public function getFormattedResponse( $rawResponse ){
		$xmlString = $this->stripXMLResponseHeaders($rawResponse);
		$displayXML = $this->formatXmlString( $xmlString );
		$realXML = new DomDocument( '1.0' );
		self::log( "Here is the Raw XML: " . $displayXML ); //I am apparently a huge fibber.
		$realXML->loadXML( trim( $xmlString ) );
		return $realXML;
	}
	
	/**
	 * Parse the response to get the status. Not sure if this should return a bool, or something more... telling.
	 */
	public function getResponseStatus( $response ){

		$aok = true;

		foreach ( $response->getElementsByTagName( 'RESULT' ) as $node ) {
			if ( array_key_exists( $node->nodeValue, $this->return_value_map ) && $this->return_value_map[$node->nodeValue] !== true ) {
				$aok = false;
			}
		}
		
		return $aok;		
	}
	
	/**
	 * Parse the response to get the errors in a format we can log and otherwise deal with.
	 * return a key/value array of codes (if they exist) and messages. 
	 */
	public function getResponseErrors( $response ){
		$errors = array();
		foreach ( $response->getElementsByTagName( 'warning' ) as $node ) {
			$code = '';
			$message = '';
			foreach ( $node->childNodes as $childnode ) {
				if ($childnode->nodeName === "code"){
					$code = $childnode->nodeValue;
				}
				if ($childnode->nodeName === "message"){
					$message = $childnode->nodeValue;
				}
			}
			$errors[$code] = $message;
		}
		return $errors;
	}
	
	/**
	 * Harvest the data we need back from the gateway. 
	 * return a key/value array
	 */
	public function getResponseData( $response ){
		$data = array();
		foreach ( $response->getElementsByTagName( 'ImportantData' ) as $node ) {
			foreach ( $node->childNodes as $childnode ) {
				if (trim($childnode->nodeValue) != ''){
					$data[$childnode->nodeName] = $childnode->nodeValue;
				}
			}
		}
		self::log( "Returned Data: " . print_r($data, true));
		return $data;
	}
	
	public function processResponse( $response ) {
		//TODO: Stuff. 
	}
	
	public function publicCurrentTransaction( $transaction = '' ){
		$this->currentTransaction( $transaction );
	}
	
	public function curl_transaction($data) {
		$data = "";
		$data['result'] = 'BLAH BLAH BLAH BLAH whatever something blah blah<?xml version="1.0"?>' . "\n" . '<XML><Response><Status>AOK</Status><ImportantData><thing>stuff</thing><otherthing>12</otherthing></ImportantData><errorswarnings><warning><code>128</code><message>Your shoe\'s untied...</message></warning><warning><code>45</code><message>Low clearance!</message></warning></errorswarnings></Response></XML>';
		$this->setTransactionResult( $data );
		return true;
	}
}

