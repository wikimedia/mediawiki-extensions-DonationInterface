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

	protected function setUp(): void {
		// TODO Use TestConfiguration.php instead?
		$this->setMwGlobals( [
			'wgGlobalCollectGatewayEnabled' => true,
			'wgGlobalCollectGatewayCustomFiltersInitialFunctions' => []
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
		$this->assertArrayNotHasKey( 'errors', $result );
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
			'gross' => '4.55',
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

		$init['wmf_token'] = $this->saltedToken;
		$session = $this->getDonorSession();

		$this->doApiRequest( $init, $session );
		$message = QueueWrapper::getQueue( 'pending' )->pop();
		$this->assertSame( '1', $message['opt_in'] );
	}

	public function testSubmitOptInFalse() {
		$init = DonationInterfaceTestCase::getDonorTestData( 'GB' );
		$init['email'] = 'good@innocent.com';
		$init['postal_code'] = 'T3 5TA';
		$init['payment_method'] = 'cc';
		$init['gateway'] = 'globalcollect';
		$init['action'] = 'donate';
		$init['opt_in'] = '0';

		$init['wmf_token'] = $this->saltedToken;
		$session = $this->getDonorSession();

		$this->doApiRequest( $init, $session );
		$message = QueueWrapper::getQueue( 'pending' )->pop();
		$this->assertSame( '0', $message['opt_in'] );
	}

	public function testSubmitFailInitialFilters() {
		$this->setInitialFiltersToFail();
		$init = DonationInterfaceTestCase::getDonorTestData();
		$init['email'] = 'good@innocent.com';
		$init['postal_code'] = 'T3 5TA';
		$init['payment_method'] = 'cc';
		$init['gateway'] = 'globalcollect';
		$init['action'] = 'donate';
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

	protected function setInitialFiltersToFail() {
		$this->setMwGlobals( [
			// We have to set this explicitly, since setMwGlobals doesn't provide
			// a way to unset a global setting.
			'wgGlobalCollectGatewayCustomFiltersInitialFunctions' => [
				'getScoreUtmSourceMap' => 100
			]
		] );

		parent::setInitialFiltersToFail();
	}
}
