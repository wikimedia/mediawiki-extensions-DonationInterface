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
 * @group DonationInterface
 * @group Splunge
 */
class DonationInterface_Adapter_GatewayAdapterTest extends DonationInterfaceTestCase {

	/**
	 * @param $name string The name of the test case
	 * @param $data array Any parameters read from a dataProvider
	 * @param $dataName string|int The name or index of the data set
	 */
	public function __construct( $name = null, array $data = array(), $dataName = '' ) {
		global $wgDonationInterfaceAllowedHtmlForms;
		global $wgDonationInterfaceTest;
		$wgDonationInterfaceTest = true;
		parent::__construct( $name, $data, $dataName );
	}

	public function setUp() {
		parent::setUp();

		$this->setMwGlobals( array(
			'wgDonationInterfaceAllowedHtmlForms' => array(
				'testytest' => array(
					'gateway' => 'globalcollect', // RAR.
				),
				'rapidFailError' => array(
					'file' => 'error-cc.html',
					'gateway' => array( 'globalcollect', 'adyen', 'amazon', 'astropay', 'paypal' ),
					'special_type' => 'error',
				)
			),
		) );
	}

	/**
	 *
	 * @covers GatewayAdapter::__construct
	 * @covers GatewayAdapter::defineVarMap
	 * @covers GatewayAdapter::defineReturnValueMap
	 * @covers GatewayAdapter::defineTransactions
	 */
	public function testConstructor() {

		$options = $this->getDonorTestData();
		$class = $this->testAdapterClass;

		$_SERVER['REQUEST_URI'] = GatewayFormChooser::buildPaymentsFormURL(
			'testytest', array( 'gateway' => $class::getIdentifier() )
		);
		$gateway = $this->getFreshGatewayObject( $options );

		$this->assertInstanceOf( TESTS_ADAPTER_DEFAULT, $gateway );

		$this->resetAllEnv();
		$gateway = $this->getFreshGatewayObject( $options = array() );
		$this->assertInstanceOf( TESTS_ADAPTER_DEFAULT, $gateway, "Having trouble constructing a blank adapter." );
	}

	/**
	 *
	 * @covers GatewayAdapter::__construct
	 * @covers DonationData::__construct
	 */
	public function testConstructorHasDonationData() {

		$_SERVER['REQUEST_URI'] = '/index.php/Special:GlobalCollectGateway?form_name=TwoStepAmount';

		$options = $this->getDonorTestData();
		$gateway = $this->getFreshGatewayObject( $options );

		$this->assertInstanceOf( 'TestingGlobalCollectAdapter', $gateway );

		// please define this function only inside the TESTS_ADAPTER_DEFAULT,
		// which should be a test adapter object that descende from one of the
		// production adapters.
		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$this->assertInstanceOf( 'DonationData', $exposed->dataObj );
	}

	public function testLanguageChange() {
		$options = $this->getDonorTestData( 'US' );
		$options['payment_method'] = 'cc';
		$options['payment_submethod'] = 'visa';
		$gateway = $this->getFreshGatewayObject( $options );

		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$this->assertEquals( $exposed->getData_Staged( 'language' ), 'en', "'US' donor's language was inproperly set. Should be 'en'" );
		$gateway->do_transaction( 'INSERT_ORDERWITHPAYMENT' );
		// so we know it tried to screw with the session and such.

		$options = $this->getDonorTestData( 'NO' );
		$gateway = $this->getFreshGatewayObject( $options );
		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$this->assertEquals( $exposed->getData_Staged( 'language' ), 'no', "'NO' donor's language was inproperly set. Should be 'no'" );
	}

	/**
	 * Make sure data is cleared out when changing gateways.
	 * In particular, ensure order IDs aren't leaking.
	 */
	public function testResetOnGatewaySwitch() {
		// Fill the session with some GlobalCollect stuff
		$init = $this->getDonorTestData( 'FR' );
		$firstRequest = $this->setUpRequest( $init );
		$globalcollect_gateway = new TestingGlobalCollectAdapter();
		$globalcollect_gateway->do_transaction( 'Donate' );

		$session = $firstRequest->getSessionArray();
		$this->assertEquals( 'globalcollect', $session['Donor']['gateway'], 'Test setup failed.' );

		//Then simulate switching to Adyen
		$session['sequence'] = 2;
		unset( $init['order_id'] );

		$secondRequest = $this->setUpRequest( $init, $session );
		$adyen_gateway = new TestingAdyenAdapter();
		$adyen_gateway->batch_mode = true;

		$session = $secondRequest->getSessionArray();
		$ctId = $adyen_gateway->getData_Unstaged_Escaped( 'contribution_tracking_id' );
		$expected_order_id = "$ctId.{$session['sequence']}";
		$this->assertEquals( $expected_order_id, $adyen_gateway->getData_Unstaged_Escaped( 'order_id' ),
			'Order ID was not regenerated on gateway switch!' );
	}

	public function testResetOnRecurringSwitch() {
		// Donor initiates a non-recurring donation
		$init = $this->getDonorTestData();
		$init['payment_method'] = 'cc';

		$firstRequest = $this->setUpRequest( $init );

		$gateway = new TestingGlobalCollectAdapter();
		$gateway->do_transaction( 'Donate' );

		$donorData = $firstRequest->getSessionData( 'Donor' );
		$this->assertEquals( '', $donorData['recurring'], 'Test setup failed.' );
		$oneTimeOrderId = $gateway->getData_Unstaged_Escaped( 'order_id' );

		// Then they go back and decide they want to make a recurring donation

		$init['recurring'] = '1';
		$secondRequest = $this->setUpRequest( $init, $firstRequest->getSessionArray() );

		$gateway = new TestingGlobalCollectAdapter();
		$gateway->do_transaction( 'Donate' );
		$donorData = $secondRequest->getSessionData( 'Donor' );
		$this->assertEquals( '1', $donorData['recurring'], 'Test setup failed.' );

		$recurOrderId = $gateway->getData_Unstaged_Escaped( 'order_id' );

		$this->assertNotEquals( $oneTimeOrderId, $recurOrderId,
			'Order ID was not regenerated on recurring switch!' );
	}

	public function testResetSubmethodOnMethodSwitch() {
		// Donor thinks they want to make a bank transfer, submits form
		$init = $this->getDonorTestData( 'BR' );
		$init['payment_method'] = 'bt';
		$init['payment_submethod'] = 'itau';

		$firstRequest = $this->setUpRequest( $init );

		$gateway = new TestingAstroPayAdapter();
		$gateway->do_transaction( 'Donate' );

		$donorData = $firstRequest->getSessionData( 'Donor' );
		$this->assertEquals( 'itau', $donorData['payment_submethod'], 'Test setup failed.' );

		// Then they go back and decide they want to donate via credit card
		$init['payment_method'] = 'cc';
		unset( $init['payment_submethod'] );

		$secondRequest = $this->setUpRequest( $init, $firstRequest->getSessionArray() );

		$gateway = new TestingAstroPayAdapter();
		$newMethod = $gateway->getData_Unstaged_Escaped( 'payment_method' );
		$newSubmethod = $gateway->getData_Unstaged_Escaped( 'payment_submethod' );

		$this->assertEquals( 'cc', $newMethod, 'Test setup failed' );
		$this->assertEquals( '', $newSubmethod, 'Submethod was not blanked on method switch' );
	}

	public function testStreetStaging() {
		$options = $this->getDonorTestData( 'BR' );
		unset( $options['street'] );
		$options['payment_method'] = 'cc';
		$options['payment_submethod'] = 'visa';
		$this->setUpRequest( $options );
		$gateway = new TestingGlobalCollectAdapter();

		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$exposed->stageData();

		$this->assertEquals( 'N0NE PROVIDED', $exposed->getData_Staged( 'street' ),
			'Street must be stuffed with fake data to prevent AVS scam.' );
	}

	public function testZipStaging() {
		$options = $this->getDonorTestData( 'BR' );
		unset( $options['zip'] );
		$options['payment_method'] = 'cc';
		$options['payment_submethod'] = 'visa';
		$this->setUpRequest( $options );
		$gateway = new TestingGlobalCollectAdapter();

		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$exposed->stageData();

		$this->assertEquals( '0', $exposed->getData_Staged( 'zip' ),
			'Postal code must be stuffed with fake data to prevent AVS scam.' );
	}

	public function testGetRapidFailPage() {
		$this->setMwGlobals( array(
			'wgDonationInterfaceRapidFail' => true,
		) );
		$options = $this->getDonorTestData( 'US' );
		$options['payment_method'] = 'cc';
		$gateway = $this->getFreshGatewayObject( $options );
		$this->assertEquals( 'rapidFailError', ResultPages::getFailPage( $gateway ) );
	}

	public function testGetFallbackFailPage() {
		$this->setMwGlobals( array(
			'wgDonationInterfaceRapidFail' => false,
			'wgDonationInterfaceFailPage' => 'Main_Page', //coz we know it exists
		) );
		$options = $this->getDonorTestData( 'US' );
		$gateway = $this->getFreshGatewayObject( $options );
		$page = ResultPages::getFailPage( $gateway );
		$expectedTitle = Title::newFromText( 'Main_Page' );
		$expectedURL = wfAppendQuery( $expectedTitle->getFullURL(), 'uselang=en' );
		$this->assertEquals( $expectedURL, $page );
	}

	// TODO: Move to ResultsPagesTest.php
	public function testGetFailPageForType() {
		$url = ResultPages::getFailPageForType( 'GlobalCollectAdapter' );
		$expectedTitle = Title::newFromText( 'Donate-error' );
		$expectedURL = wfAppendQuery( $expectedTitle->getFullURL(), 'uselang=en' );
		$this->assertEquals( $expectedURL, $url );
	}

	public function testCancelPage() {
		$this->setMwGlobals( array(
			'wgDonationInterfaceCancelPage' => 'Ways to give'
		) );
		$gateway = $this->getFreshGatewayObject();
		$url = ResultPages::getCancelPage( $gateway );
		$expectedTitle = Title::newFromText( 'Ways to give/en' );
		$this->assertEquals( $expectedTitle->getFullURL(), $url );
	}

	public function testCannotOverrideIp() {
		$data = $this->getDonorTestData( 'FR' );
		unset( $data['country'] );
		$data['user_ip'] = '8.8.8.8';

		$gateway = $this->getFreshGatewayObject( $data );
		$this->assertEquals( '127.0.0.1', $gateway->getData_Unstaged_Escaped( 'user_ip' ) );
	}

	public function testCanOverrideIpInBatchMode() {
		$data = $this->getDonorTestData( 'FR' );
		unset( $data['country'] );
		$data['user_ip'] = '8.8.8.8';

		$gateway = $this->getFreshGatewayObject( $data, array( 'batch_mode' => true ) );
		$this->assertEquals( '8.8.8.8', $gateway->getData_Unstaged_Escaped( 'user_ip' ) );
	}

	function testGetScoreName() {
		$rule = array(
			'KeyMapA' => array( 'a','s','d','f','q','w','e','r','t' ),
			'KeyMapB' => array(),
			'GibberishWeight' => .9,
			'Score' => 10
		);
		$this->setMwGlobals(
			array( 'wgDonationInterfaceNameFilterRules' => array( $rule ) )
		);
		$init = $this->getDonorTestData();
		$init['fname'] = 'asdf';
		$init['lname'] = 'qwert';

		$gateway = $this->getFreshGatewayObject( $init );
		$result = $gateway->getScoreName();
		$this->assertNotEquals( 0, $result, 'Bad name not detected');
	}

	public function TestSetValidationAction() {
		$data = $this->getDonorTestData( 'FR' );
		$gateway = $this->getFreshGatewayObject( $data );
		$gateway->setValidationAction( 'process' );
		$this->assertEquals( 'process', $gateway->getValidationAction(), 'Setup failed' );
		$gateway->setValidationAction( 'reject' );
		$this->assertEquals( 'reject', $gateway->getValidationAction(), 'Unable to escalate action' );
		$gateway->setValidationAction( 'process' );
		$this->assertEquals( 'reject', $gateway->getValidationAction(), 'De-escalating action without reset!' );
	}
}

