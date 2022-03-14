<?php
use SmashPig\Core\DataStores\QueueWrapper;

class AdyenRecurringTest extends BaseAdyenCheckoutTestCase {

	/**
	 * Can make a recurring payment
	 *
	 * @covers AdyenCheckoutAdapter::doRecurringConversion
	 */
	public function testRecurringCharge() {
		$init = $this->getDonorTestData();
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$gateway = $this->getFreshGatewayObject( $init );
		$gateway_txn_id = mt_rand( 100000, 1000000 );
		$donor_session = $this->getDonorSession( $gateway_txn_id );
		$this->setUpRequest( $init, $donor_session );
		$gateway->doRecurringConversion();

		$message = QueueWrapper::getQueue( 'recurring' )->pop();
		$this->assertNotNull( $message['recurring_payment_token'] );
	}

	protected function getDonorSession( $gateway_txn_id ) {
		// this ends up being the first part of our order_id.sequence which maps to shopperReference
		$ct_id = mt_rand( 0, 100000 );
		return [
			'Donor' => [ 'contribution_tracking_id' => $ct_id,
				'city' => "San Francisco",
				'country' => 'US',
				'currency' => 'USD',
				'email' => 'jwales@example.com',
				'fee' => 0,
				'first_name' => 'Jimmy',
				'full_name' => '',
				'gateway' => 'adyen',
				'gateway_txn_id' => $gateway_txn_id,
				'gross' => "2.00",
				'language' => 'en',
				'last_name' => 'Wales',
				'order_id' => '111.9',
				'payment_method' => "cc",
				'payment_submethod' => 'visa',
				'postal_code' => '94104',
				'recurring' => 1,
				'recurring_payment_token' => mt_rand( 1000000, 10000000 ),
				'processor_contact_id' => mt_rand( 10000000, 100000000 )
			],
			'adyenEditToken' => 'blahblah',
		];
	}
}
