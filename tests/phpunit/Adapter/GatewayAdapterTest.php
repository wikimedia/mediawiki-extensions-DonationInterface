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

use SmashPig\PaymentData\ValidationAction;
use Wikimedia\TestingAccessWrapper;

/**
 * TODO: Test everything.
 * Make sure all the basic functions in the gateway_adapter are tested here.
 * Also, the filters firing properly and... that the fail score they give
 * back is acted upon in the way we think it does.
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

	public function setUp(): void {
		parent::setUp();

		$this->setMwGlobals( [
			'wgDonationInterfaceAllowedHtmlForms' => [
				'testytest' => [
					'gateway' => 'globalcollect', // RAR.
				],
				'rapidFailError' => [
					'file' => 'error-cc.html',
					'gateway' => [ 'globalcollect', 'adyen', 'amazon', 'astropay', 'paypal' ],
					'special_type' => 'error',
				]
			],
		] );
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

		$gateway = $this->getFreshGatewayObject( $options );

		$this->assertInstanceOf( TESTS_ADAPTER_DEFAULT, $gateway );

		self::resetAllEnv();
		$gateway = $this->getFreshGatewayObject( $options = [] );
		$this->assertInstanceOf( TESTS_ADAPTER_DEFAULT, $gateway, "Having trouble constructing a blank adapter." );
	}

	/**
	 * Test that the required fields are read out of country_fields.yaml
	 * @dataProvider getRequiredFields
	 * @param string $country test donor country
	 * @param array $fields expected required fields
	 */
	public function testRequiredFields( $country, $fields ) {
		$init = $this->getDonorTestData( $country );
		$init['contribution_tracking_id'] = '45931210';
		$init['payment_method'] = 'cc';
		$this->setUpRequest( $init, [ 'Donor' => $init ] );
		$gateway = $this->getFreshGatewayObject( $init );
		$requiredFields = $gateway->getRequiredFields();
		$this->assertArrayEquals( $fields, $requiredFields );
	}

	public function getRequiredFields() {
		return [
			[ 'AU', [
				'country', 'first_name', 'last_name',
				'email', 'state_province'
			] ],
			[ 'ES', [
				'country', 'first_name', 'last_name',
				'email'
			] ],
			[ 'US', [
				'country', 'first_name', 'last_name',
				'email', 'street_address', 'city',
				'postal_code', 'state_province'
			] ],
		];
	}

	public function testOptionalFieldsConfig() {
		$this->setMwGlobals( [
			'wgDonationInterfaceVariantConfigurationDirectory' =>
				__DIR__ . '/../includes/variants'
		] );

		$init = $this->getDonorTestData( 'US' );
		$init['contribution_tracking_id'] = '45931210';
		$init['payment_method'] = 'cc';
		$init['variant'] = 'optional';
		$this->setUpRequest( $init, [ 'Donor' => $init ] );
		$gateway = $this->getFreshGatewayObject(
			$init, [ 'variant' => 'optional' ]
		);

		// all form fields including required and optional
		$allFields = $gateway->getFormFields();
		$requiredFields = $gateway->getRequiredFields();

		// check the ConfigurationReader loaded in the optional fields and they're left over
		// when requiredFields are filtered out
		$optionals = array_filter( array_keys( $allFields ), function ( $field ) use ( $requiredFields ) {
			// we only want values NOT in requiredFields
			return !in_array( $field, $requiredFields );
		} );

		// optionals are set in tests/phpunit/includes/variants/optional/globalcollect/country_fields.yaml
		$this->assertEquals( [ 'last_name', 'state_province' ], array_values( $optionals ) );
	}

	/**
	 * Load an alternate yaml file based on 'variant'
	 */
	public function testVariantConfig() {
		$this->setMwGlobals( [
			'wgDonationInterfaceVariantConfigurationDirectory' =>
				__DIR__ . '/../includes/variants'
		] );
		$init = $this->getDonorTestData( 'US' );
		$init['contribution_tracking_id'] = '45931210';
		$init['payment_method'] = 'cc';
		$init['variant'] = 'nostate';
		$this->setUpRequest( $init, [ 'Donor' => $init ] );
		$gateway = $this->getFreshGatewayObject(
			$init, [ 'variant' => 'nostate' ]
		);
		// The 'nostate' variant requires fewer fields in the US
		$requiredFields = $gateway->getRequiredFields();
		$this->assertEquals( [
			'country', 'first_name', 'last_name',
			'email', 'street_address', 'postal_code'
		], $requiredFields );
	}

	/**
	 * Don't allow directory traversal via 'variant'
	 */
	public function testIllegalVariantConfig() {
		$this->setMwGlobals( [
			'wgDonationInterfaceVariantConfigurationDirectory' =>
				__DIR__ . '/../includes/variants'
		] );
		$init = $this->getDonorTestData( 'US' );
		$init['contribution_tracking_id'] = '45931210';
		$init['payment_method'] = 'cc';
		$init['variant'] = '../notallowedvariants/nostate';
		$this->setUpRequest( $init, [ 'Donor' => $init ] );
		$gateway = $this->getFreshGatewayObject(
			$init, [ 'variant' => '../notallowedvariants/nostate' ]
		);
		$requiredFields = $gateway->getRequiredFields();
		$this->assertArrayEquals( [
			'country', 'first_name', 'last_name',
			'email', 'street_address', 'city',
			'postal_code', 'state_province'
		], $requiredFields );
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

		$this->assertInstanceOf( TestingGlobalCollectAdapter::class, $gateway );

		// please define this function only inside the TESTS_ADAPTER_DEFAULT,
		// which should be a test adapter object that descende from one of the
		// production adapters.
		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$this->assertInstanceOf( DonationData::class, $exposed->dataObj );
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
		$init['payment_method'] = 'cc';
		$firstRequest = $this->setUpRequest( $init );
		$globalcollect_gateway = new TestingGlobalCollectAdapter();
		$globalcollect_gateway->do_transaction( 'INSERT_ORDERWITHPAYMENT' );

		$session = $firstRequest->getSessionArray();
		$this->assertEquals( 'globalcollect', $session['Donor']['gateway'], 'Test setup failed.' );

		// Then simulate switching to Adyen
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
		$oneTimeOrderId = $gateway->getData_Unstaged_Escaped( 'order_id' );
		$gateway->do_transaction( 'INSERT_ORDERWITHPAYMENT' );

		$donorData = $firstRequest->getSessionData( 'Donor' );
		$this->assertEquals( '', $donorData['recurring'], 'Test setup failed.' );

		// Then they go back and decide they want to make a recurring donation

		$init['recurring'] = '1';
		$secondRequest = $this->setUpRequest( $init, $firstRequest->getSessionArray() );

		$gateway = new TestingGlobalCollectAdapter();
		$recurOrderId = $gateway->getData_Unstaged_Escaped( 'order_id' );
		$gateway->do_transaction( 'INSERT_ORDERWITHPAYMENT' );
		$donorData = $secondRequest->getSessionData( 'Donor' );
		$this->assertEquals( '1', $donorData['recurring'], 'Test setup failed.' );

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
		$gateway->do_transaction( 'NewInvoice' );

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
		unset( $options['street_address'] );
		$options['payment_method'] = 'cc';
		$options['payment_submethod'] = 'visa';
		$this->setUpRequest( $options );
		$gateway = new TestingGlobalCollectAdapter();

		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$exposed->stageData();

		$this->assertEquals(
			StreetAddress::STREET_ADDRESS_PLACEHOLDER,
			$exposed->getData_Staged( 'street_address' ),
			'Street must be stuffed with fake data to prevent AVS scam.'
		);
	}

	public function testPostalCodeStaging() {
		$options = $this->getDonorTestData( 'BR' );
		unset( $options['postal_code'] );
		$options['payment_method'] = 'cc';
		$options['payment_submethod'] = 'visa';
		$this->setUpRequest( $options );
		$gateway = new TestingGlobalCollectAdapter();

		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$exposed->stageData();

		$this->assertEquals(
			StreetAddress::POSTAL_CODE_PLACEHOLDER,
			$exposed->getData_Staged( 'postal_code' ),
			'Postal code must be stuffed with fake data to prevent AVS scam.'
		);
	}

	public function testStreetUnStaging() {
		$options = $this->getDonorTestData( 'BR' );
		unset( $options['street_address'] );
		$options['payment_method'] = 'cc';
		$options['payment_submethod'] = 'visa';
		$this->setUpRequest( $options );
		$gateway = new TestingGlobalCollectAdapter();

		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$exposed->stageData();

		$this->assertEquals(
			StreetAddress::STREET_ADDRESS_PLACEHOLDER,
			$exposed->getData_Staged( 'street_address' ),
			'Setup failed.'
		);
		$exposed->unstageData();

		$this->assertEquals(
			'',
			$exposed->getData_Unstaged_Escaped( 'street_address' ),
			'The street address placeholder is only for AVS, not for us.'
		);
	}

	public function testPostalCodeUnStaging() {
		$options = $this->getDonorTestData( 'BR' );
		unset( $options['postal_code'] );
		$options['payment_method'] = 'cc';
		$options['payment_submethod'] = 'visa';
		$this->setUpRequest( $options );
		$gateway = new TestingGlobalCollectAdapter();

		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$exposed->stageData();

		$this->assertEquals(
			StreetAddress::POSTAL_CODE_PLACEHOLDER,
			$exposed->getData_Staged( 'postal_code' ),
			'Setup failed.'
		);
		$exposed->unstageData();

		$this->assertEquals(
			'',
			$exposed->getData_Unstaged_Escaped( 'postal_code' ),
			'The postal code placeholder is only for AVS, not for our records.'
		);
	}

	public function testGetRapidFailPage() {
		$this->setMwGlobals( [
			'wgDonationInterfaceRapidFail' => true,
		] );
		$options = $this->getDonorTestData( 'US' );
		$options['payment_method'] = 'cc';
		$gateway = $this->getFreshGatewayObject( $options );
		$this->assertEquals( 'rapidFailError', ResultPages::getFailPage( $gateway ) );
	}

	public function testGetFallbackFailPage() {
		$this->setMwGlobals( [
			'wgDonationInterfaceRapidFail' => false,
			'wgDonationInterfaceFailPage' => 'Main_Page', // coz we know it exists
		] );
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
		$this->setMwGlobals( [
			'wgDonationInterfaceCancelPage' => 'Ways to give'
		] );
		$gateway = $this->getFreshGatewayObject();
		$url = ResultPages::getCancelPage( $gateway );
		$expectedTitle = Title::newFromText( 'Ways to give/en' );
		$this->assertEquals( $expectedTitle->getFullURL( '', false, PROTO_CURRENT ), $url );
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

		$gateway = $this->getFreshGatewayObject( $data, [ 'batch_mode' => true ] );
		$this->assertEquals( '8.8.8.8', $gateway->getData_Unstaged_Escaped( 'user_ip' ) );
	}

	public function testGetScoreName() {
		$rule = [
			'KeyMapA' => [ 'a','s','d','f','q','w','e','r','t' ],
			'KeyMapB' => [],
			'GibberishWeight' => 0.9,
			'Score' => 10,
			'MinimumLength' => 2,
		];
		$this->setMwGlobals(
			[ 'wgDonationInterfaceNameFilterRules' => [ $rule ] ]
		);
		$init = $this->getDonorTestData();
		$init['first_name'] = 'asdf';
		$init['last_name'] = 'qwert';

		$gateway = $this->getFreshGatewayObject( $init );
		$result = $gateway->getScoreName();
		$this->assertNotEquals( 0, $result, 'Bad name not detected' );
	}

	public function testGetScoreNameMinimumLength() {
		$rule = [
			'KeyMapA' => [ 'a','s','d','f','q','w','e','r','t' ],
			'KeyMapB' => [],
			'GibberishWeight' => 0.9,
			'Score' => 10,
			'MinimumLength' => 2,
		];
		$this->setMwGlobals(
			[ 'wgDonationInterfaceNameFilterRules' => [ $rule ] ]
		);
		$init = $this->getDonorTestData();
		$init['first_name'] = 'a';
		$init['last_name'] = 'q';

		$gateway = $this->getFreshGatewayObject( $init );
		$result = $gateway->getScoreName();
		$this->assertEquals( 0, $result, 'Short name not skipped' );
	}

	public function TestSetValidationAction() {
		$data = $this->getDonorTestData( 'FR' );
		$gateway = $this->getFreshGatewayObject( $data );
		$gateway->setValidationAction( ValidationAction::PROCESS );
		$this->assertEquals( ValidationAction::PROCESS, $gateway->getValidationAction(), 'Setup failed' );
		$gateway->setValidationAction( ValidationAction::REJECT );
		$this->assertEquals( ValidationAction::REJECT, $gateway->getValidationAction(), 'Unable to escalate action' );
		$gateway->setValidationAction( ValidationAction::PROCESS );
		$this->assertEquals( ValidationAction::REJECT, $gateway->getValidationAction(), 'De-escalating action without reset!' );
	}

	public function testRectifyOrphan() {
		$orphan = $this->createOrphan( [ 'gateway' => 'donation' ] );
		$gateway = $this->getFreshGatewayObject( $orphan );
		// FIXME: dummy communication status, currently returns false because orpphan can't be rectifiied!
		$is_rectified = $gateway->rectifyOrphan();
		$this->assertEquals( PaymentResult::newEmpty(), $is_rectified, 'rectifyOrphan did not return empty PaymentResult' );
	}

	public function testGetDonationQueueMessage() {
		$data = $this->getDonorTestData( 'FR' );
		$gateway = $this->getFreshGatewayObject( $data );
		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$message = $exposed->getQueueDonationMessage();
		$expected = array_intersect_key( $data, array_flip( DonationData::getMessageFields() ) );
		$expected += [
			'gateway_txn_id' => false,
			'response' => false,
			'gateway_account' => 'test',
			'fee' => 0,
			'contribution_tracking_id' => $exposed->getData_Unstaged_Escaped( 'contribution_tracking_id' ),
			'utm_source' => '..',
			'email' => '',
			'gateway' => $gateway::getIdentifier(),
			'order_id' => $exposed->getData_Unstaged_Escaped( 'order_id' ),
			'recurring' => '',
			'payment_method' => '',
			'payment_submethod' => '',
			'gross' => $data['amount'],
			'user_ip' => RequestContext::getMain()->getRequest()->getIP()
		];
		unset( $message['date'] );
		unset( $expected['amount'] );
		$this->assertEquals( $expected, $message );
	}

	/**
	 * Add contact_id and contact_hash to the message when both exist
	 */
	public function testGetDonationQueueMessageContactId() {
		$data = $this->getDonorTestData( 'FR' );
		$data['contact_id'] = mt_rand();
		$data['contact_hash'] = 'asdasd' . $data['contact_id']; // super secure
		$gateway = $this->getFreshGatewayObject( $data );
		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$message = $exposed->getQueueDonationMessage();
		$expected = array_intersect_key( $data, array_flip( DonationData::getMessageFields() ) );
		$expected += [
			'contact_id' => $data['contact_id'],
			'contact_hash' => $data['contact_hash'],
			'gateway_txn_id' => false,
			'response' => false,
			'gateway_account' => 'test',
			'fee' => 0,
			'contribution_tracking_id' => $exposed->getData_Unstaged_Escaped( 'contribution_tracking_id' ),
			'utm_source' => '..',
			'email' => '',
			'gateway' => $gateway::getIdentifier(),
			'order_id' => $exposed->getData_Unstaged_Escaped( 'order_id' ),
			'recurring' => '',
			'payment_method' => '',
			'payment_submethod' => '',
			'gross' => $data['amount'],
			'user_ip' => RequestContext::getMain()->getRequest()->getIP()
		];
		unset( $message['date'] );
		unset( $expected['amount'] );
		$this->assertEquals( $expected, $message );
	}

	/**
	 * Don't add contact_id without contact_hash
	 */
	public function testGetDonationQueueMessageContactIdNoHash() {
		$data = $this->getDonorTestData( 'FR' );
		$data['contact_id'] = mt_rand();
		$gateway = $this->getFreshGatewayObject( $data );
		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$message = $exposed->getQueueDonationMessage();
		$expected = array_intersect_key( $data, array_flip( DonationData::getMessageFields() ) );
		$expected += [
			'gateway_txn_id' => false,
			'response' => false,
			'gateway_account' => 'test',
			'fee' => 0,
			'contribution_tracking_id' => $exposed->getData_Unstaged_Escaped( 'contribution_tracking_id' ),
			'utm_source' => '..',
			'email' => '',
			'gateway' => $gateway::getIdentifier(),
			'order_id' => $exposed->getData_Unstaged_Escaped( 'order_id' ),
			'recurring' => '',
			'payment_method' => '',
			'payment_submethod' => '',
			'gross' => $data['amount'],
			'user_ip' => RequestContext::getMain()->getRequest()->getIP()
		];
		unset( $message['date'] );
		unset( $expected['amount'] );
		$this->assertEquals( $expected, $message );
	}
}
