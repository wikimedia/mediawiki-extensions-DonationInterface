<?php
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\CrmLink\Messages\SourceFields;

/**
 * @group Fundraising
 * @group DonationInterface
 * @group GlobalCollect
 * @group GlobalCollectApi
 * @group DonationInterfaceApi
 * @group medium
 */
class GlobalCollectApiTest extends DonationInterfaceApiTestCase {

	public function testGoodSubmit() {
		$init = DonationInterfaceTestCase::getDonorTestData();
		$init['email'] = 'good@innocent.com';
		$init['payment_method'] = 'cc';
		$init['gateway'] = 'globalcollect';
		$init['action'] = 'donate';

		$apiResult = $this->doApiRequest( $init );
		$result = $apiResult[0]['result'];
		$this->assertTrue( empty( $result['errors'] ) );
		$orderId = $result['orderid'];

		$this->assertEquals( 'url_placeholder', $result['formaction'], 'GC API not setting formaction' );
		$this->assertTrue( is_numeric( $orderId ), 'GC API not setting numeric order ID' );
		$this->assertTrue( $result['status'], 'GC API result status should be true' );
		preg_match( "/Special:GlobalCollectGatewayResult\?order_id={$orderId}\$/", $result['returnurl'], $match );
		$this->assertNotEmpty( $match, 'GC API not setting proper return url' );
		$message = QueueWrapper::getQueue( 'pending' )->pop();
		$this->assertNotNull( $message, 'Not sending a message to the pending queue' );
		DonationInterfaceTestCase::unsetVariableFields( $message );
		$expected = array(
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
		);
		$this->assertArraySubset( $expected, $message );
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

		$apiResult = $this->doApiRequest( $init );
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
		unset($init['ffname']);

		$this->doApiRequest( $init );
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
		unset($init['ffname']);

		$this->doApiRequest( $init );
		$message = QueueWrapper::getQueue( 'pending' )->pop();
		$this->assertEquals( '0', $message['opt_in'] );
	}
}
