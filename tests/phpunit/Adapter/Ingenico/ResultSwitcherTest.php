<?php

use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\CrmLink\Messages\SourceFields;

/**
 * @group Fundraising
 * @group DonationInterface
 * @group Ingenico
 */
class DonationInterface_Adapter_Ingenico_ResultSwitcherTest extends BaseIngenicoTestCase {

	/**
	 * Assuming we've popped out of the frame, does processing succeed?
	 */
	public function testResultSwitcherLiberatedSuccess() {
		$donorTestData = $this->getDonorTestData( 'FR' );
		$donorTestData['payment_method'] = 'cc';
		$donorTestData['payment_submethod'] = 'visa';
		$donorTestData['email'] = 'innocent@localhost.net';
		$donorTestData['order_id'] = mt_rand();
		$this->hostedPaymentStatusResponse['createdPaymentOutput']
			['payment']
			['paymentOutput']
			['amountOfMoney'] = [
				'amount' => $donorTestData['amount'] * 100,
				'currencyCode' => $donorTestData['currency']
			];
		$session['Donor'] = $donorTestData;
		// Mark the order as already popped out of the iframe
		$session['order_status'][$donorTestData['order_id']] = 'liberated';
		$request = [
			'merchantReference' => $donorTestData['order_id'],
			'language' => 'fr', // FIXME: verifyFormOutput conflates request with other stuff
		];
		$assertNodes = [
			'headers' => [
				'Location' => function ( $location ) use ( $donorTestData ) {
					// Do this after the real processing to avoid side effects
					$gateway = $this->getFreshGatewayObject( $donorTestData );
					$url = ResultPages::getThankYouPage( $gateway );
					$this->assertEquals( $url, $location );
				}
			]
		];
		$this->hostedCheckoutProvider->expects( $this->once() )
			->method( 'getHostedPaymentStatus' )
			->willReturn( $this->hostedPaymentStatusResponse );
		$this->hostedCheckoutProvider->expects( $this->once() )
			->method( 'approvePayment' )
			->willReturn( $this->approvePaymentResponse );
		$this->verifyFormOutput( 'IngenicoGatewayResult', $request, $assertNodes, false, $session );
		$queueMessage = QueueWrapper::getQueue( 'donations' )->pop();
		SourceFields::removeFromMessage( $queueMessage );
		$expected = $donorTestData;
		$expected['gross'] = $donorTestData['amount'];
		unset( $expected['ffname'] );
		unset( $expected['referrer'] );
		unset( $expected['amount'] );
		unset( $expected['processor_form'] );
		unset( $expected['postal_code'] );
		$this->assertArraySubset(
			$expected,
			$queueMessage
		);
	}
}
