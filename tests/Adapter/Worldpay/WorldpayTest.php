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
use Psr\Log\LogLevel;

/**
 *
 * @group Fundraising
 * @group DonationInterface
 * @group Worldpay
 */
class DonationInterface_Adapter_Worldpay_WorldpayTest extends DonationInterfaceTestCase {

	/**
	 * @param $name string The name of the test case
	 * @param $data array Any parameters read from a dataProvider
	 * @param $dataName string|int The name or index of the data set
	 */
	function __construct( $name = null, array $data = array(), $dataName = '' ) {
		parent::__construct( $name, $data, $dataName );
		$this->testAdapterClass = 'TestingWorldpayAdapter';
	}

	public function setUp() {
		global $wgWorldpayGatewayHtmlFormDir;
		parent::setUp();

		$this->setMwGlobals( array(
			'wgWorldpayGatewayEnabled' => true,
			'wgDonationInterfaceAllowedHtmlForms' => array(
				'testytest' => array(
					'gateway' => 'worldpay',
				),
				'worldpay' => array(
					'file' => $wgWorldpayGatewayHtmlFormDir . '/worldpay.html',
					'gateway' => 'worldpay',
					'countries' => array( '+' => array( 'AU', 'BE', 'CA', 'FR', 'GB', 'IL', 'NZ', 'US' ) ),
					'currencies' => array( '+' => 'ALL' ),
					'payment_methods' => array( 'cc' => 'ALL' ),
					'selection_weight' => 10
				),
			),
		) );
	}

	/**
	 * Just making sure we can instantiate the thing without blowing up completely
	 */
	function testConstruct() {
		$options = $this->getDonorTestData();
		$class = $this->testAdapterClass;

		$_SERVER['REQUEST_URI'] = GatewayFormChooser::buildPaymentsFormURL( 'testytest', array ( 'gateway' => $class::getIdentifier() ) );
		$gateway = $this->getFreshGatewayObject( $options );

		$this->assertInstanceOf( 'TestingWorldpayAdapter', $gateway );
	}

	/**
	 * Test the AntiFraud hooks
	 */
	function testAntiFraudHooks() {
		$options = $this->getDonorTestData( 'US' );
		$options['utm_source'] = "somethingmedia";
		$options['email'] = "somebody@wikipedia.org";

		$gateway = $this->getFreshGatewayObject( $options );

		$gateway->runAntifraudHooks();

		$this->assertEquals( 'reject', $gateway->getValidationAction(), 'Validation action is not as expected' );
		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$this->assertEquals( 113, $exposed->risk_score, 'RiskScore is not as expected' );
	}

	/**
	 * Just making sure we can instantiate the thing without blowing up completely
	 */
	function testNeverLog() {
		$options = $this->getDonorTestData();
		$options['cvv'] = '123';
		$class = $this->testAdapterClass;

		$_SERVER['REQUEST_URI'] = GatewayFormChooser::buildPaymentsFormURL( 'testytest', array ( 'gateway' => $class::getIdentifier() ) );
		$gateway = $this->getFreshGatewayObject( $options );

		$this->assertInstanceOf( 'TestingWorldpayAdapter', $gateway );
		$gateway->do_transaction( 'AuthorizePaymentForFraud' );

		$loglines = $this->getLogMatches( LogLevel::INFO, '/Request XML/' );

		$this->assertEquals( 1, count( $loglines ), "We did not receive exactly one logline back that contains request XML" );
		$this->assertEquals( 1, preg_match( '/Cleaned/', $loglines[0] ), 'The logline did not come back marked as "Cleaned".' );
		$this->assertEquals( 0, preg_match( '/CNV/', $loglines[0] ), 'The "Cleaned" logline contained CVN data!' );
	}

	function testWorldpayFormLoad() {
		$init = $this->getDonorTestData();
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';
		$init['email'] = 'somebody@wikipedia.org';
		$init['payment_submethod'] = 'visa';
		$init['ffname'] = 'worldpay';
		$init['currency_code'] = 'EUR';

		$assertNodes = array (
			'selected-amount' => array (
				'nodename' => 'span',
				'innerhtml' => '€1.55',
			),
			'fname' => array(
				'nodename' => 'input',
				'value' => 'Firstname',
			),
			'lname' => array(
				'nodename' => 'input',
				'value' => 'Surname',
			),
			'street' => array(
				'nodename' => 'input',
				'value' => '123 Fake Street',
			),
			'city' => array(
				'nodename' => 'input',
				'value' => 'San Francisco',
			),
			'zip' => array(
				'nodename' => 'input',
				'value' => '94105',
			),
			'country' => array(
				'nodename' => 'input',
				'value' => 'US',
			),
			'emailAdd' => array(
				'nodename' => 'input',
				'value' => 'somebody@wikipedia.org',
			),
			'language' => array(
				'nodename' => 'input',
				'value' => 'en',
			),
			'state' => array(
				'nodename' => 'select',
				'selected' => 'CA',
			),
			'informationsharing' => array (
				'nodename' => 'p',
				'innerhtml' => "By donating, you agree to share your personal information with the Wikimedia Foundation, the nonprofit organization that hosts Wikipedia and other Wikimedia projects, and its service providers pursuant to our <a href=\"//wikimediafoundation.org/wiki/Donor_policy\">donor policy</a>. Wikimedia Foundation and its service providers are located in the United States and in other countries whose privacy laws may not be equivalent to your own. We do not sell or trade your information to anyone. For more information please read our <a href=\"//wikimediafoundation.org/wiki/Donor_policy\">donor policy</a>."
			),
		);

		$this->verifyFormOutput( 'TestingWorldpayGateway', $init, $assertNodes, true );
	}

	function testPaymentFormSubmit() {
		$init = $this->getDonorTestData( 'FR' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['ffname'] = 'worldpay';
		$init['currency_code'] = 'EUR';
		$init['email'] = 'noemailfraudscore@test.org';

		$init['OTT'] = 'SALT123456789';

		$assertNodes = array(
			'headers' => array(
				'Location' => 'https://wikimediafoundation.org/wiki/Thank_You/fr?country=FR',
			),
		);

		$this->verifyFormOutput( 'TestingWorldpayGateway', $init, $assertNodes, true );
	}

	function testWorldpayFormLoad_FR() {
		$init = $this->getDonorTestData( 'FR' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['ffname'] = 'worldpay';

		$assertNodes = array (
			'selected-amount' => array (
				'nodename' => 'span',
				'innerhtml' => '€1.55',
			),
			'fname' => array (
				'nodename' => 'input',
				'value' => 'Prénom',
			),
			'lname' => array (
				'nodename' => 'input',
				'value' => 'Nom',
			),
			'country' => array (
				'nodename' => 'input',
				'value' => 'FR',
			),
		);

		$this->verifyFormOutput( 'TestingWorldpayGateway', $init, $assertNodes, true );
	}

	/**
	 * Make sure Belgian form loads in all of that country's supported languages
	 * @dataProvider belgiumLanguageProvider
	 */
	public function testWorldpayFormLoad_BE( $language ) {
		$init = $this->getDonorTestData( 'BE' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['ffname'] = 'worldpay';
		$init['language'] = $language;

		$assertNodes = array (
			'selected-amount' => array (
				'nodename' => 'span',
				'innerhtml' => '€1.55',
			),
			'fname-label' => array (
				'nodename' => 'label',
				'innerhtml' => wfMessage( 'donate_interface-donor-fname' )->inLanguage( $language )->text(),
			),
			'lname-label' => array (
				'nodename' => 'label',
				'innerhtml' => wfMessage( 'donate_interface-donor-lname' )->inLanguage( $language )->text(),
			),
			'emailAdd-label' => array (
				'nodename' => 'label',
				'innerhtml' => wfMessage( 'donate_interface-donor-email' )->inLanguage( $language )->text(),
			),
			'informationsharing' => array (
				'nodename' => 'p',
				'innerhtml' => wfMessage( 'donate_interface-informationsharing', '.*' )->inLanguage( $language )->text(),
			),
		);

		$this->verifyFormOutput( 'TestingWorldpayGateway', $init, $assertNodes, true );
	}

	/**
	 * Testing that we can retrieve the cvv_match value and run antifraud on it correctly
	 */
	function testAntifraudCVVMatch() {
		$options = $this->getDonorTestData(); //don't really care: We'll be using the dummy response directly.

		$gateway = $this->getFreshGatewayObject( $options );
		$gateway->do_transaction( 'AuthorizePaymentForFraud' );

		$this->assertEquals( '1', $gateway->getData_Unstaged_Escaped( 'cvv_result' ), 'cvv_result was not set after AuthorizePaymentForFraud' );
		$this->assertTrue( $gateway->getCVVResult(), 'getCVVResult not passing somebody with a match.' );

		//and now, for fun, test a wrong code.
		$gateway->addResponseData( array ( 'cvv_result' => '2' ) );
		$this->assertFalse( $gateway->getCVVResult(), 'getCVVResult not failing somebody with garbage.' );
	}

	/**
	 * Ensure we don't give too high a risk score when AVS address / zip match was not performed
	 */
	function testAntifraudAllowsAvsNotPerformed() {
		$options = $this->getDonorTestData('FR'); //don't really care: We'll be using the dummy response directly.

		$gateway = $this->getFreshGatewayObject( $options );
		$gateway->setDummyGatewayResponseCode( 9000 );
		$gateway->do_transaction( 'AuthorizePaymentForFraud' );

		$this->assertEquals( '9', $gateway->getData_Unstaged_Escaped( 'avs_address' ), 'avs_address was not set after AuthorizePaymentForFraud' );
		$this->assertEquals( '9', $gateway->getData_Unstaged_Escaped( 'avs_zip' ), 'avs_zip was not set after AuthorizePaymentForFraud' );
		$this->assertTrue( $gateway->getAVSResult() < 25, 'getAVSResult returning too high a score for AVS not performed.' );
	}

	/**
	 * Check to make sure we don't run antifraud filters (and burn a minfraud query) when we know the transaction has already failed
	 */
	function testAntifraudNotPerformedOnGatewayError() {
		$options = $this->getDonorTestData( 'FR' ); //don't really care: We'll be using the dummy response directly.

		$gateway = $this->getFreshGatewayObject( $options );
		$gateway->setDummyGatewayResponseCode( 2208 ); //account problems
		$gateway->do_transaction( 'AuthorizePaymentForFraud' );

		//assert that:
		//#1 - the gateway object has an appropriate transaction error set
		//#2 - antifraud checks were not performed.

		//check for the error code that corresponds to the transaction coming back with a failure, rather than the one that we use for fraud fail.
		$errors = $gateway->getTransactionErrors();
		$this->assertTrue( !empty( $errors ), 'No errors in getTransactionErrors after a bad "AuthorizePaymentForFraud"' );
		$this->assertTrue( array_key_exists( 'internal-0001', $errors ), 'Unexpected error code' );

		//check more things to make sure we didn't run any fraud filters
		$loglines = $this->getLogMatches( LogLevel::INFO, '/Preparing to run custom filters/' );
		$this->assertEmpty( $loglines, 'According to the logs, we ran antifraud filters and should not have' );
		$this->assertEquals( 'process', $gateway->getValidationAction(), 'Validation action is not as expected' );
		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$this->assertEquals( 0, $exposed->risk_score, 'RiskScore is not as expected' );

	}

	/**
	 * Check to make sure we do run antifraud filters when we know the transaction is okay to go
	 */
	function testAntifraudPerformedOnGatewayNoError() {
		$options = $this->getDonorTestData( 'FR' ); //don't really care: We'll be using the dummy response directly.
		$options['email'] = 'test@something.com';

		$gateway = $this->getFreshGatewayObject( $options );
//		$gateway->setDummyGatewayResponseCode( 2208 ); //account problems
		$gateway->do_transaction( 'AuthorizePaymentForFraud' );

		//assert that:
		//#1 - the gateway object has no errors set
		//#2 - antifraud checks were performed.
		$errors = $gateway->getTransactionErrors();
		$this->assertTrue( empty( $errors ), 'Errors assigned in getTransactionErrors after a good "AuthorizePaymentForFraud"' );
		//check more things to make sure we did run the fraud filters
		$loglines = $this->getLogMatches( LogLevel::INFO, '/CustomFiltersScores/' );
		$this->assertNotEmpty( $loglines, 'No antifraud filters were run, according to the logs' );
		$this->assertEquals( 'process', $gateway->getValidationAction(), 'Validation action is not as expected' );
		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$this->assertEquals( 0, $exposed->risk_score, 'RiskScore is not as expected' );
	}

	/**
	 * Ensure we're staging a punctuation-stripped version of the email address in merchant_reference_2
	 */
	function testMerchantReference2() {
		$options = $this->getDonorTestData();
		$options['email'] = 'little+teapot@short.stout.com';
		$gateway = $this->getFreshGatewayObject( $options );
		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$exposed->stageData();
		$staged = $exposed->getData_Staged( 'merchant_reference_2' );
		$this->assertEquals( 'little teapot short stout com', $staged );
	}

	function testTransliterateUtf8forEurocentricProcessor() {
		$options = $this->getDonorTestData();
		$options['fname'] = 'Barnabáš';
		$options['lname'] = 'Voříšek';
		$options['street'] = 'Truhlářská 320/62';
		$options['city'] = 'České Budějovice';
		$class = $this->testAdapterClass;

		$_SERVER['REQUEST_URI'] = GatewayFormChooser::buildPaymentsFormURL( 'testytest', array ( 'gateway' => $class::getIdentifier() ) );
		$gateway = $this->getFreshGatewayObject( $options );

		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$exposed->stageData();
		$gateway->do_transaction( 'AuthorizeAndDepositPayment' );
		$xml = new SimpleXMLElement( preg_replace( '/StringIn=/', '', $gateway->curled ) );
		$this->assertEquals( 'Barnabás', $xml->FirstName );
		$this->assertEquals( 'Vorísek', $xml->LastName );
		$this->assertEquals( 'Truhlárská 320/62', $xml->Address1 );
		$this->assertEquals( 'Ceské Budejovice', $xml->City );
	}

	/**
	 * Check that whacky #.# format orderid is unmolested by order_id_meta validation.
	 */
	function testWackyOrderIdPassedValidation() {
		$externalData = self::$initial_vars;
		$externalData['order_id'] = '2143.0';
		$options = array(
			'external_data' => $externalData,
			'batch_mode' => true,
		);

		$gateway = new TestingWorldpayAdapter( $options );
		$this->assertEquals( $externalData['order_id'], $gateway->getData_Unstaged_Escaped( 'order_id' ),
			'Decimal Order ID should be allowed by orderIdMeta validation' );
	}

	/**
	 * Check that order_id is built from contribution_tracking id.
	 */
	function testWackyOrderIdBasedOnContributionTracking() {
		$externalData = self::$initial_vars;
		$externalData['contribution_tracking_id'] = mt_rand();
		$session = array( 'sequence' => 2 );

		$this->setUpRequest( array(), $session );
		$gateway = $this->getFreshGatewayObject( $externalData, array( 'batch_mode' => TRUE, ) );
		$expected_order_id = "{$externalData['contribution_tracking_id']}.{$session['sequence']}";
		$this->assertEquals( $expected_order_id, $gateway->getData_Unstaged_Escaped( 'order_id' ),
			'Decimal Order ID is not correctly built from Contribution Tracking ID.' );
	}

	/**
	 * Ensure processResponse doesn't fail trxn for special accounts when AVS
	 * nodes are missing.
	 */
	function testProcessResponseAllowsSnowflakeAVSMissing() {
		$options = $this->getDonorTestData( 'FJ' ); // 'FJ' store ID is set up as a special exception

		$gateway = $this->getFreshGatewayObject( $options );
		$gateway->setDummyGatewayResponseCode( 'snowflake' );
		$results = $gateway->do_transaction( 'AuthorizePaymentForFraud' );

		// internal-0001 is the error code processRespose adds for missing nodes
		$this->assertFalse( array_key_exists( 'internal-0001', $results->getErrors() ),
			'processResponse is failing a special snowflake account with a response missing AVS nodes' );
	}

	/**
	 * Ensure we don't give too high a risk score for special accounts when
	 * AVS address / zip match was not performed and CVV reports failure
	 */
	function testAntifraudAllowsSnowflakeAVSMissingAndCVVMismatch() {
		$options = $this->getDonorTestData( 'FJ' ); // 'FJ' store ID is set up as a special exception

		$gateway = $this->getFreshGatewayObject( $options );
		$gateway->setDummyGatewayResponseCode( 'snowflake' );
		$gateway->do_transaction( 'AuthorizePaymentForFraud' );

		$this->assertTrue( $gateway->getCVVResult(), 'getCVVResult failing snowflake account' );

		$this->assertTrue( $gateway->getAVSResult() < 25, 'getAVSResult giving snowflake account too high a risk score' );
	}

	function testNarrativeStatement1() {
		$class = $this->testAdapterClass;
		$_SERVER['REQUEST_URI'] = GatewayFormChooser::buildPaymentsFormURL( 'testytest', array ( 'gateway' => $class::getIdentifier() ) );
		$options = $this->getDonorTestData();
		$options['contribution_tracking_id'] = mt_rand();
		$gateway = $this->getFreshGatewayObject( $options );

		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$exposed->stageData();
		$gateway->do_transaction( 'AuthorizeAndDepositPayment' );
		$xml = new SimpleXMLElement( preg_replace( '/StringIn=/', '', $gateway->curled ) );
		$this->assertEquals( "Wikimedia {$options['contribution_tracking_id']}", $xml->NarrativeStatement1 );
	}

	/**
	 * Check that we send different OrderNumbers for each transaction in a donation.
	 */
	function testDistinctOrderNumberForEachTxn() {
		$options = $this->getDonorTestData();
		$gateway = $this->getFreshGatewayObject( $options );

		$getOrderNumber = function() use ( $gateway ) {
			$xml = new SimpleXMLElement( preg_replace( '/StringIn=/', '', $gateway->curled ) );
			return $xml->OrderNumber;
		};

		$gateway->do_transaction( 'AuthorizePaymentForFraud' );
		$fraudOrderNumber = $getOrderNumber();
		$gateway->do_transaction( 'AuthorizeAndDepositPayment' );
		$saleOrderNumber = $getOrderNumber();

		$this->assertNotEquals( $fraudOrderNumber, $saleOrderNumber,
			'Sending same OrderNumber for both fraud auth and sale.' );
	}

	/**
	 * doPayment should return an empty result with normal data
	 */
	function testDoPaymentSuccess() {
		$init = $this->getDonorTestData();
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['email'] = 'innocent@clean.com';
		$init['ffname'] = 'worldpay';
		$init['currency_code'] = 'EUR';
		$init['OTT'] = 'SALT123456789';
		unset( $init['order_id'] );

		$gateway = $this->getFreshGatewayObject( $init );
		$result = $gateway->doPayment();
		$this->assertEmpty( $result->isFailed(), 'PaymentResult should not be failed' );
		$this->assertEmpty( $result->getErrors(), 'PaymentResult should have no errors' );
	}

	/**
	 * doPayment should return a failed result with data that triggers the fraud
	 * filter
	 */
	function testDoPaymentFailed() {
		$init = $this->getDonorTestData();
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['email'] = 'nefarious@wikimedia.org'; //configured as fraudy
		$init['ffname'] = 'worldpay';
		$init['currency_code'] = 'EUR';
		$init['OTT'] = 'SALT123456789';
		unset( $init['order_id'] );

		$gateway = $this->getFreshGatewayObject( $init );
		$result = $gateway->doPayment();
		$this->assertTrue( $result->isFailed(), 'PaymentResult should be failed' );
		$errors = $result->getErrors();

		$this->assertEquals(
			'Failed post-process checks for transaction type AuthorizePaymentForFraud.',
			$errors['internal-0000']['debugInfo'],
			'PaymentResult errors should include fraud check failure'
		);
	}
}
