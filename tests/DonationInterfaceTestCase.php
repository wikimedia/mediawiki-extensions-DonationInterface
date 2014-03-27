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

require_once __DIR__ . '/TestConfiguration.php';
require_once dirname( __FILE__ ) . '/includes/test_gateway/test.adapter.php';

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
	 * Returns an array of the vars we expect to be set before people hit payments.
	 * @var array
	 */
	public $initial_vars = array (
		'ffname' => 'testytest',
		'referrer' => 'www.yourmom.com', //please don't go there.
		'currency_code' => 'USD',
	);

	/**
	 * This will be set by a test method with the adapter object.
	 *
	 * @var GatewayAdapter	$gatewayAdapter
	 */
	protected $gatewayAdapter;

	public function __construct() {

		//Just in case you got here without running the configuration...
		global $wgDonationInterfaceTestMode;
		$wgDonationInterfaceTestMode = true;

		$adapterclass = TESTS_ADAPTER_DEFAULT;
		$this->testAdapterClass = $adapterclass;

		$this->resetAllEnv();

		parent::__construct();
	}

	protected function setupServer() {

	}

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

		$this->gatewayAdapter = $this->getFreshGatewayObject( $options );

		$this->gatewayAdapter->setCurrentTransaction('INSERT_ORDERWITHPAYMENT');

		$request = trim( $this->gatewayAdapter->_buildRequestXML() );

		$expected = $this->getExpectedXmlRequestForGlobalCollect( $optionsForTestData, $options );
		
		$this->assertEquals( $expected, $request, 'The constructed XML for payment_method [' . $optionsForTestData['payment_method'] . '] and payment_submethod [' . $optionsForTestData['payment_submethod'] . '] does not match our expected request.' );
	}

	/**
	 *
	 * @param string $country The country we want the test user to be from.
	 * @return array Donor data to use
	 * @throws MWException when there is no data available for the requested country
	 */
	public function getDonorTestData( $country = '' ) {
		$donortestdata = array (
			'US' => array ( //default
				'city' => 'San Francisco',
				'state' => 'CA',
				'zip' => '94105',
				'currency_code' => 'USD',
				'street' => '123 Fake Street',
				'fname' => 'Firstname',
				'lname' => 'Surname',
				'amount' => '1.55',
				'language' => 'en',
			),
			'ES' => array (
				'city' => 'Barcelona',
				'state' => 'XX',
				'zip' => '0',
				'currency_code' => 'EUR',
				'street' => '123 Calle Fake',
				'fname' => 'Nombre',
				'lname' => 'Apellido',
				'amount' => '1.55',
				'language' => 'es',
			),
			'NO' => array (
				'city' => 'Oslo',
				'state' => 'XX',
				'zip' => '0',
				'currency_code' => 'EUR',
				'street' => '123 Fake Gate',
				'fname' => 'Fornavn',
				'lname' => 'Etternavn',
				'amount' => '1.55',
				'language' => 'no',
			),
		);
		//default to US
		if ( $country === '' ) {
			$country = 'US';
		}

		if ( array_key_exists( $country, $donortestdata ) ) {
			$donortestdata = array_merge( $this->initial_vars, $donortestdata[$country] );
			$donortestdata['country'] = $country;
			return $donortestdata;
		}
		throw new MWException( __FUNCTION__ . ": No donor data for country '$country'" );
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
		$merchantref = $this->gatewayAdapter->_getData_Staged( 'contribution_tracking_id' );
		//@TODO: WHY IN THE NAME OF ZARQUON are we building XML in a STRING format here?!?!?!!!1one1!?. Great galloping galumphing giraffes.
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
		$expected .= 				'<AMOUNT>' . $options['amount'] * 100 . '</AMOUNT>';
		$expected .= 				'<CURRENCYCODE>' . $options['currency_code'] . '</CURRENCYCODE>';
		$expected .= 				'<LANGUAGECODE>' . $options['language'] . '</LANGUAGECODE>';
		$expected .= 				'<COUNTRYCODE>' . $options['country'] . '</COUNTRYCODE>';
		$expected .= '<MERCHANTREFERENCE>' . $merchantref . '</MERCHANTREFERENCE>';

		if ( isset( $wgRequest ) ) {
			$expected .=			'<IPADDRESSCUSTOMER>' . $wgRequest->getIP() . '</IPADDRESSCUSTOMER>';
		}

		$expected .=				'<EMAIL>' . TESTS_EMAIL . '</EMAIL>';
		$expected .= 			'</ORDER>';
		$expected .= 			'<PAYMENT>';
		$expected .= 				'<PAYMENTPRODUCTID>' . $optionsForTestData['payment_product_id'] . '</PAYMENTPRODUCTID>';
		$expected .= 				'<AMOUNT>' . $options['amount'] * 100 . '</AMOUNT>';
		$expected .= 				'<CURRENCYCODE>' . $options['currency_code'] . '</CURRENCYCODE>';
		$expected .= 				'<LANGUAGECODE>' . $options['language'] . '</LANGUAGECODE>';
		$expected .= 				'<COUNTRYCODE>' . $options['country'] . '</COUNTRYCODE>';
		$expected .= 				'<HOSTEDINDICATOR>1</HOSTEDINDICATOR>';
		$expected .= 				'<RETURNURL>' . $wgDonationInterfaceThankYouPage . '/' . $options['language'] . '</RETURNURL>';
		$expected .=				'<AUTHENTICATIONINDICATOR>0</AUTHENTICATIONINDICATOR>';
		$expected .= 				'<FIRSTNAME>' . $options['fname'] . '</FIRSTNAME>';
		$expected .= 				'<SURNAME>' . $options['lname'] . '</SURNAME>';
		$expected .= 				'<STREET>' . $options['street'] . '</STREET>';
		$expected .= 				'<CITY>' . $options['city'] . '</CITY>';
		$expected .= 				'<STATE>' . $options['state'] . '</STATE>';
		$expected .= 				'<ZIP>' . $options['zip'] . '</ZIP>';
		$expected .= '<EMAIL>' . TESTS_EMAIL . '</EMAIL>';

		// Set the issuer id if it is passed.
		if ( isset( $optionsForTestData['descriptor'] ) ) {
			$expected .= '<DESCRIPTOR>' . $optionsForTestData['descriptor'] . '</DESCRIPTOR>';
		}

		// Set the issuer id if it is passed.
		if ( isset( $optionsForTestData['issuer_id'] ) ) {
			$expected .= 				'<ISSUERID>' . $optionsForTestData['issuer_id'] . '</ISSUERID>';
		}


		// If we're doing Direct Debit...
		//@TODO: go ahead and split this out into a "Get the direct debit I_OWP XML block function" the second this gets even slightly annoying.
		if ( $optionsForTestData['payment_method'] === 'dd' ) {
			$expected .= '<DATECOLLECT>' . gmdate( 'Ymd' ) . '</DATECOLLECT>'; //is this cheating? Probably.
			$expected .= '<ACCOUNTNAME>' . $optionsForTestData['account_name'] . '</ACCOUNTNAME>';
			$expected .= '<ACCOUNTNUMBER>' . $optionsForTestData['account_number'] . '</ACCOUNTNUMBER>';
			$expected .= '<BANKCODE>' . $optionsForTestData['bank_code'] . '</BANKCODE>';
			$expected .= '<BRANCHCODE>' . $optionsForTestData['branch_code'] . '</BRANCHCODE>';
			$expected .= '<BANKCHECKDIGIT>' . $optionsForTestData['bank_check_digit'] . '</BANKCHECKDIGIT>';
			$expected .= '<DIRECTDEBITTEXT>' . $optionsForTestData['direct_debit_text'] . '</DIRECTDEBITTEXT>';
		}

		$expected .= 			'</PAYMENT>';
		$expected .= 		'</PARAMS>';
		$expected .= 	'</REQUEST>';
		$expected .= '</XML>';
		
		return $expected;
		
	}

	/**
	 * Get a fresh gateway object of the type specified in the variable
	 * $this->testAdapterClass.
	 * @param array $external_data If you want to shoehorn in some external
	 * data, do that here.
	 * @param array $setup_hacks An array of things that override stuff in
	 * the constructor of the gateway object that I can't get to without
	 * refactoring the whole thing. @TODO: Refactor the gateway adapter
	 * constructor.
	 * @return \class The new relevant gateway adapter object.
	 */
	function getFreshGatewayObject( $external_data = null, $setup_hacks = null ) {
		$p1 = null;
		if ( !is_null( $external_data ) ) {
			$p1 = array (
				'external_data' => $external_data,
			);
		}

		if ( !is_null( $setup_hacks ) ) {
			$p1 = array_merge( $p1, $setup_hacks );
		}

		$class = $this->testAdapterClass;
		$gateway = new $class( $p1 );

		return $gateway;
	}

	function resetAllEnv() {
		$_SESSION = array ( );
		$_GET = array ( );
		$_POST = array ( );

		$_SERVER = array ( );
		$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
		$_SERVER['HTTP_HOST'] = TESTS_HOSTNAME;
		$_SERVER['SERVER_NAME'] = TESTS_HOSTNAME;
		$_SERVER['SCRIPT_NAME'] = __FILE__;
	}

}
