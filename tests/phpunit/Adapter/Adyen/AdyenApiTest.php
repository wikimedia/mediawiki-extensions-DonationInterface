<?php
use Psr\Log\LogLevel;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\PaymentProviders\Adyen\Tests\AdyenTestConfiguration;
use SmashPig\Tests\TestingContext;

/**
 * @group Fundraising
 * @group DonationInterface
 * @group Adyen
 * @group DonationInterfaceApi
 * @group medium
 */
class AdyenApiTest extends DonationInterfaceApiTestCase {

	public function setUp() {
		parent::setUp();
		$ctx = TestingContext::get();
		$ctx->providerConfigurationOverride =
			AdyenTestConfiguration::createWithSuccessfulApi(
			$ctx->getGlobalConfiguration()
		);
		$this->setMwGlobals( [
			'wgAdyenGatewayEnabled' => true,
			'wgAdyenGatewayTestingURL' => 'https://example.org',
		] );
	}

	public function testGoodSubmit() {
		$init = $this->getDonorData();
		$session = $this->getDonorSession();
		$apiResult = $this->doApiRequest( $init, $session );
		$result = $apiResult[0]['result'];
		$this->assertTrue( empty( $result['errors'] ) );

		$this->assertEquals(
			'https://example.org/hpp/pay.shtml',
			$result['iframe'],
			'Adyen API not setting correct iframe'
		);

		// the shopperReference field should match our ct_id.sequence ID
		$expectedShopperReference = $session['Donor']['contribution_tracking_id'] . ".1";

		$expectedParams = [
			'allowedMethods' => 'card',
			'brandCode' => 'visa',
			'card.cardHolderName' => 'Firstname Surname',
			'currencyCode' => 'USD',
			'merchantAccount' => 'wikitest',
			'offset' => '20',
			'paymentAmount' => '155',
			'skinCode' => 'testskin',
			'shopperLocale' => 'en_US',
			'shopperEmail' => 'good@innocent.com',
			'shopperReference' => $expectedShopperReference,
			'billingAddress.street' => '123 Fake Street',
			'billingAddress.city' => 'NA',
			'billingAddress.stateOrProvince' => 'NA',
			'billingAddress.postalCode' => '94105',
			'billingAddress.country' => 'US',
			'billingAddressType' => '2',
			'billingAddress.houseNumberOrName' => 'NA'
		];
		$actualParams = $result['formData'];
		unset( $actualParams['sessionValidity'] );
		unset( $actualParams['shipBeforeDate'] );
		unset( $actualParams['merchantSig'] );
		unset( $actualParams['merchantReference'] );
		$this->assertEquals(
			$expectedParams,
			$actualParams,
			'Adyen API not setting correct formData'
		);
		$message = QueueWrapper::getQueue( 'pending' )->pop();
		$this->assertNotNull( $message, 'Not sending a message to the pending queue' );
		DonationInterfaceTestCase::unsetVariableFields( $message );
		$expected = [
			'gateway_txn_id' => false,
			'response' => false,
			'fee' => 0,
			'utm_source' => '..cc',
			'language' => 'en',
			'email' => 'good@innocent.com',
			'first_name' => 'Firstname',
			'last_name' => 'Surname',
			'country' => 'US',
			'gateway' => 'adyen',
			'recurring' => '',
			'payment_method' => 'cc',
			'payment_submethod' => 'visa',
			'currency' => 'USD',
			'gross' => '1.55',
			'user_ip' => '127.0.0.1',
			'street_address' => '123 Fake Street',
			'postal_code' => '94105',
			'risk_score' => 20
		];
		$this->assertArraySubset( $expected, $message );
		$message = QueueWrapper::getQueue( 'pending' )->pop();
		$this->assertNull( $message, 'Sending extra pending messages' );
		$logged = DonationInterfaceTestCase::getLogMatches(
			LogLevel::INFO, '/^Redirecting for transaction: /'
		);
		$this->assertEquals( 1, count( $logged ), 'Should have logged details once' );
		preg_match( '/Redirecting for transaction: (.*)$/', $logged[0], $matches );
		$detailString = $matches[1];
		$actual = json_decode( $detailString, true );
		$this->assertArraySubset( $expected, $actual, 'Logged the wrong stuff!' );
	}

	public function testTooSmallDonation() {
		$init = $this->getDonorData();
		$init['amount'] = 0.75;

		$apiResult = $this->doApiRequest( $init );
		$result = $apiResult[0]['result'];
		$this->assertNotEmpty( $result['errors'], 'Should have returned an error' );
		$this->assertNotEmpty( $result['errors']['amount'], 'Error should be in amount' );
		$message = QueueWrapper::getQueue( 'pending' )->pop();
		$this->assertNull( $message, 'Sending pending message for error' );
		$logged = DonationInterfaceTestCase::getLogMatches(
			LogLevel::INFO, '/^Redirecting for transaction: /'
		);
		$this->assertEmpty( $logged, 'Logs are a lie, we did not redirect' );
	}

	public function testMissingPostalCode() {
		$init = $this->getDonorData();
		unset( $init['postal_code'] );

		$apiResult = $this->doApiRequest( $init );
		$result = $apiResult[0]['result'];
		$this->assertNotEmpty( $result['errors'], 'Should have returned an error' );
		$this->assertNotEmpty(
			$result['errors']['postal_code'],
			'Error should be in postal_code'
		);
		$message = QueueWrapper::getQueue( 'pending' )->pop();
		$this->assertNull( $message, 'Sending pending message for error' );
		$logged = DonationInterfaceTestCase::getLogMatches(
			LogLevel::INFO, '/^Redirecting for transaction: /'
		);
		$this->assertEmpty( $logged, 'Logs are a lie, we did not redirect' );
	}

	public function testRecurringSubmit() {
		$init = $this->getDonorData();
		$init['recurring'] = 1;
		$session = $this->getDonorSession();
		$apiResult = $this->doApiRequest( $init, $session );
		$result = $apiResult[0]['result'];
		$this->assertTrue( empty( $result['errors'] ) );

		$this->assertEquals(
			'https://example.org/hpp/pay.shtml',
			$result['iframe'],
			'Adyen API not setting correct iframe'
		);

		// the shopperReference field should match our ct_id.sequence ID
		$expectedShopperReference = $session['Donor']['contribution_tracking_id'] . ".1";

		$expectedParams = [
			'allowedMethods' => 'card',
			'brandCode' => 'visa',
			'card.cardHolderName' => 'Firstname Surname',
			'currencyCode' => 'USD',
			'merchantAccount' => 'wikitest',
			'offset' => '20',
			'paymentAmount' => '155',
			'skinCode' => 'testskin',
			'shopperLocale' => 'en_US',
			'shopperEmail' => 'good@innocent.com',
			'shopperReference' => $expectedShopperReference,
			'recurringContract' => 'RECURRING',
			'billingAddress.street' => '123 Fake Street',
			'billingAddress.city' => 'NA',
			'billingAddress.stateOrProvince' => 'NA',
			'billingAddress.postalCode' => '94105',
			'billingAddress.country' => 'US',
			'billingAddressType' => '2',
			'billingAddress.houseNumberOrName' => 'NA'
		];
		$actualParams = $result['formData'];
		unset( $actualParams['sessionValidity'] );
		unset( $actualParams['shipBeforeDate'] );
		unset( $actualParams['merchantSig'] );
		unset( $actualParams['merchantReference'] );
		$this->assertEquals(
			$expectedParams,
			$actualParams,
			'Adyen API not setting correct formData'
		);
		$message = QueueWrapper::getQueue( 'pending' )->pop();
		$this->assertNotNull( $message, 'Not sending a message to the pending queue' );
		DonationInterfaceTestCase::unsetVariableFields( $message );
		$expected = [
			'gateway_txn_id' => false,
			'response' => false,
			'fee' => 0,
			'utm_source' => '..rcc',
			'language' => 'en',
			'email' => 'good@innocent.com',
			'first_name' => 'Firstname',
			'last_name' => 'Surname',
			'country' => 'US',
			'gateway' => 'adyen',
			'recurring' => '1',
			'recurring_payment_token' => $expectedShopperReference,
			'payment_method' => 'cc',
			'payment_submethod' => 'visa',
			'currency' => 'USD',
			'gross' => '1.55',
			'user_ip' => '127.0.0.1',
			'street_address' => '123 Fake Street',
			'postal_code' => '94105',
			'risk_score' => 20
		];
		$this->assertArraySubset( $expected, $message );
		$message = QueueWrapper::getQueue( 'pending' )->pop();
		$this->assertNull( $message, 'Sending extra pending messages' );
		$logged = DonationInterfaceTestCase::getLogMatches(
			LogLevel::INFO, '/^Redirecting for transaction: /'
		);
		$this->assertEquals( 1, count( $logged ), 'Should have logged details once' );
		preg_match( '/Redirecting for transaction: (.*)$/', $logged[0], $matches );
		$detailString = $matches[1];
		$actual = json_decode( $detailString, true );
		$this->assertArraySubset( $expected, $actual, 'Logged the wrong stuff!' );
	}

	protected function getDonorData() {
		$init = DonationInterfaceTestCase::getDonorTestData();
		$init['email'] = 'good@innocent.com';
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['gateway'] = 'adyen';
		$init['action'] = 'donate';
		// The US form doesn't have these two as we can look them up by zip
		unset( $init['city'] );
		unset( $init['state_province'] );
		return $init;
	}

	protected function getDonorSession() {
		// this ends up being the first part of our order_id.sequence which maps to shopperReference
		$ct_id = mt_rand( 0, 10000000 );
		return [ 'Donor' => [ 'contribution_tracking_id' => $ct_id ] ];
	}

	public function testSubmitFailInitialFilters() {
		$this->setInitialFiltersToFail();
		$init = $this->getDonorData();
		$apiResult = $this->doApiRequest( $init );
		$result = $apiResult[0]['result'];
		$this->assertNotEmpty( $result['errors'], 'Should have returned an error' );
	}
}
