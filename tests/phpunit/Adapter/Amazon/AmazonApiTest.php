<?php
use SmashPig\Core\DataStores\QueueWrapper;

/**
 * @group Amazon
 * @group DonationInterface
 * @group DonationInterfaceApi
 * @group Fundraising
 * @group medium
 */
class AmazonApiTest extends DonationInterfaceApiTestCase {
	/**
	 * @var \SmashPig\PaymentProviders\Amazon\Tests\AmazonTestConfiguration
	 */
	protected $providerConfig;

	public function setUp(): void {
		parent::setUp();
		$this->providerConfig = DonationInterface_Adapter_Amazon_Test::setUpAmazonTestingContext();
	}

	public function testDoPaymentSuccess() {
		$params = [
			'amount' => '1.55',
			'currency' => 'USD',
			'recurring' => '0',
			'wmf_token' => 'e601502632e5e51dc2a17a0045162272+\\',
			'orderReferenceId' => mt_rand( 0, 10000000 ),
			'action' => 'di_amazon_bill',
		];
		$session = [
			'Donor' => [
				'amount' => '1.55',
				'currency' => 'USD',
				'recurring' => '0',
				'contribution_tracking_id' => mt_rand( 0, 10000000 ),
				'country' => 'US',
			],
			'amazonEditToken' => 'kjaskdjahsdkjsad',
		];
		$apiResult = $this->doApiRequest( $params, $session );
		$redirect = $apiResult[0]['redirect'];
		$this->assertEquals( 'https://donate.wikimedia.org/wiki/Thank_You/en?country=US', $redirect );
		$mockClient = $this->providerConfig->object( 'payments-client' );
		$setOrderReferenceDetailsArgs = $mockClient->calls['setOrderReferenceDetails'][0];
		$oid = $session['Donor']['contribution_tracking_id'] . '-1';
		$this->assertEquals( $oid, $setOrderReferenceDetailsArgs['seller_order_id'], 'Did not set order id on order reference' );
		$this->assertEquals( $params['amount'], $setOrderReferenceDetailsArgs['amount'], 'Did not set amount on order reference' );

		$this->assertEquals( $params['currency'], $setOrderReferenceDetailsArgs['currency_code'], 'Did not set currency code on order reference' );
		$message = QueueWrapper::getQueue( 'donations' )->pop();
		$this->assertNotNull( $message, 'Not sending a message to the donations queue' );
		$this->assertEquals( 'S01-0391295-0674065-C095112', $message['gateway_txn_id'], 'Queue message has wrong txn ID' );
	}

	/**
	 * InvalidPaymentMethod error should show an error message in the
	 * 'general' section.
	 */
	public function testDoPaymentErrors() {
		$params = [
			'amount' => '1.55',
			'currency' => 'USD',
			'recurring' => '0',
			'wmf_token' => 'e601502632e5e51dc2a17a0045162272+\\',
			'orderReferenceId' => mt_rand( 0, 10000000 ),
			'action' => 'di_amazon_bill',
		];
		$session = [
			'Donor' => [
				'amount' => '1.55',
				'currency' => 'USD',
				'recurring' => '0',
				'contribution_tracking_id' => mt_rand( 0, 10000000 ),
				'country' => 'US',
			],
			'amazonEditToken' => 'kjaskdjahsdkjsad',
		];
		$mockClient = $this->providerConfig->object( 'payments-client' );
		$mockClient->returns['authorize'][] = 'InvalidPaymentMethod';

		$apiResult = $this->doApiRequest( $params, $session );
		$errors = $apiResult[0]['errors'];
		$this->assertNotEmpty( $errors['general'] );
	}
}
