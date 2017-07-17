<?php
use SmashPig\Tests\TestingContext;
use SmashPig\Tests\TestingGlobalConfiguration;

/**
 * @group Amazon
 * @group DonationInterface
 * @group DonationInterfaceApi
 * @group Fundraising
 * @group medium
 */
class AmazonApiTest extends DonationInterfaceApiTestCase {
	public function setUp() {
		parent::setUp();
		TestingAmazonAdapter::$mockClient = new MockAmazonClient();
	}

	public function tearDown() {
		TestingAmazonAdapter::$mockClient = null;
		parent::tearDown();
	}

	public function testDoPaymentSuccess() {
		$params = array(
			'amount' => '1.55',
			'currency' => 'USD',
			'recurring' => '0',
			'wmf_token' => 'e601502632e5e51dc2a17a0045162272+\\',
			'orderReferenceId' => mt_rand( 0, 10000000 ),
			'action' => 'di_amazon_bill',
		);
		$session = array(
			'Donor' => array(
				'amount' => '1.55',
				'currency' => 'USD',
				'recurring' => '0',
				'contribution_tracking_id' => mt_rand( 0, 10000000 ),
				'country' => 'US',
			),
			'amazonEditToken' => 'kjaskdjahsdkjsad',
		);
		$apiResult = $this->doApiRequest( $params, $session );
		$redirect = $apiResult[0]['redirect'];
		$this->assertEquals( 'https://wikimediafoundation.org/wiki/Thank_You/en?country=US', $redirect );
		$mockClient = TestingAmazonAdapter::$mockClient;
		$setOrderReferenceDetailsArgs = $mockClient->calls['setOrderReferenceDetails'][0];
		$oid = $session['Donor']['contribution_tracking_id'] . '-0';
		$this->assertEquals( $oid, $setOrderReferenceDetailsArgs['seller_order_id'], 'Did not set order id on order reference' );
		$this->assertEquals( $params['amount'], $setOrderReferenceDetailsArgs['amount'], 'Did not set amount on order reference' );

		$this->assertEquals( $params['currency'], $setOrderReferenceDetailsArgs['currency_code'], 'Did not set currency code on order reference' );
		$message = DonationQueue::instance()->pop( 'donations' );
		$this->assertNotNull( $message, 'Not sending a message to the donations queue' );
		$this->assertEquals( 'S01-0391295-0674065-C095112', $message['gateway_txn_id'], 'Queue message has wrong txn ID' );
	}
}
