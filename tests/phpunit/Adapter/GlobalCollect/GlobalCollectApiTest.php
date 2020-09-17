<?php
use SmashPig\Core\DataStores\QueueWrapper;

/**
 * @group Fundraising
 * @group DonationInterface
 * @group GlobalCollect
 * @group GlobalCollectApi
 * @group DonationInterfaceApi
 * @group medium
 */
class GlobalCollectApiTest extends DonationInterfaceApiTestCase {

	public function setUp(): void {
		$this->setMwGlobals( [
			'wgGlobalCollectGatewayEnabled' => true
		] );
		parent::setUp();
	}

	public function testGoodSubmit() {
		$init = DonationInterfaceTestCase::getDonorTestData();
		$init['email'] = 'good@innocent.com';
		$init['payment_method'] = 'cc';
		$init['gateway'] = 'globalcollect';
		$init['action'] = 'donate';
		$init['wmf_token'] = $this->saltedToken;
		$session = $this->getDonorSession();

		$apiResult = $this->doApiRequest( $init, $session );
		$result = $apiResult[0]['result'];
		$this->assertTrue( empty( $result['errors'] ) );
		$actualUrl = $result['iframe'];
		$this->assertEquals( 'url_placeholder', $actualUrl, 'GC API not setting iframe' );
		$message = QueueWrapper::getQueue( 'pending' )->pop();
		$this->assertNotNull( $message, 'Not sending a message to the pending queue' );

		$orderId = $message['order_id'];
		$this->assertTrue( is_numeric( $orderId ), 'GC API not setting numeric order ID' );

		DonationInterfaceTestCase::unsetVariableFields( $message );
		$expected = [
			'gateway_txn_id' => '626113410',
			'response' => 'Response Status: 20',
			'fee' => 0,
			'utm_source' => '..cc',
			'language' => 'en',
			'email' => 'good@innocent.com',
			'first_name' => 'Firstname',
			'last_name' => 'Surname',
			'country' => 'US',
			'gateway' => 'globalcollect',
			'order_id' => '626113410',
			'recurring' => '',
			'payment_method' => 'cc',
			'payment_submethod' => '',
			'currency' => 'USD',
			'gross' => '1.55',
			'user_ip' => '127.0.0.1',
			'street_address' => '123 Fake Street',
			'city' => 'San Francisco',
			'state_province' => 'CA',
			'postal_code' => '94105'
		];
		$this->assertArraySubmapSame( $expected, $message );
		// Don't send any value for opt_in if not set or shown
		$this->assertTrue( !isset( $message['opt_in'] ) );
		$message = QueueWrapper::getQueue( 'pending' )->pop();
		$this->assertNull( $message, 'Sending extra pending messages' );
	}

	public function testTooSmallDonation() {
		$init = DonationInterfaceTestCase::getDonorTestData();
		$init['email'] = 'good@innocent.com';
		$init['payment_method'] = 'cc';
		$init['gateway'] = 'globalcollect';
		$init['action'] = 'donate';
		$init['amount'] = 0.75;
		$init['wmf_token'] = $this->saltedToken;
		$session = $this->getDonorSession();

		$apiResult = $this->doApiRequest( $init, $session );
		$result = $apiResult[0]['result'];
		$this->assertNotEmpty( $result['errors'], 'Should have returned an error' );
		$this->assertNotEmpty( $result['errors']['amount'], 'Error should be in amount' );
		$message = QueueWrapper::getQueue( 'pending' )->pop();
		$this->assertNull( $message, 'Sending pending message for error' );
	}

	public function testSubmitOptInTrue() {
		$init = DonationInterfaceTestCase::getDonorTestData( 'GB' );
		$init['email'] = 'good@innocent.com';
		$init['postal_code'] = 'T3 5TA';
		$init['payment_method'] = 'cc';
		$init['gateway'] = 'globalcollect';
		$init['action'] = 'donate';
		$init['opt_in'] = '1';

		// ffname causes a validation trip up
		// set here: DonationInterface/tests/phpunit/DonationInterfaceTestCase.php:41
		unset( $init['ffname'] );
		$init['wmf_token'] = $this->saltedToken;
		$session = $this->getDonorSession();

		$this->doApiRequest( $init, $session );
		$message = QueueWrapper::getQueue( 'pending' )->pop();
		$this->assertEquals( '1', $message['opt_in'] );
	}

	public function testSubmitOptInFalse() {
		$init = DonationInterfaceTestCase::getDonorTestData( 'GB' );
		$init['email'] = 'good@innocent.com';
		$init['postal_code'] = 'T3 5TA';
		$init['payment_method'] = 'cc';
		$init['gateway'] = 'globalcollect';
		$init['action'] = 'donate';
		$init['opt_in'] = '0';

		// ffname causes a validation trip up
		// set here: DonationInterface/tests/phpunit/DonationInterfaceTestCase.php:41
		unset( $init['ffname'] );
		$init['wmf_token'] = $this->saltedToken;
		$session = $this->getDonorSession();

		$this->doApiRequest( $init, $session );
		$message = QueueWrapper::getQueue( 'pending' )->pop();
		$this->assertEquals( '0', $message['opt_in'] );
	}

	public function testSubmitFailInitialFilters() {
		$this->setInitialFiltersToFail();
		$init = DonationInterfaceTestCase::getDonorTestData();
		$init['email'] = 'good@innocent.com';
		$init['postal_code'] = 'T3 5TA';
		$init['payment_method'] = 'cc';
		$init['gateway'] = 'globalcollect';
		$init['action'] = 'donate';
		unset( $init['ffname'] );
		$init['wmf_token'] = $this->saltedToken;
		$session = $this->getDonorSession();

		$apiResult = $this->doApiRequest( $init, $session );
		$result = $apiResult[0]['result'];
		$this->assertNotEmpty( $result['errors'], 'Should have returned an error' );
	}

	protected function getDonorSession() {
		return [
			'Donor' => [ 'contribution_tracking_id' => mt_rand( 0, 10000000 ) ],
			'globalcollectEditToken' => 'blahblah',
		];
	}
}
