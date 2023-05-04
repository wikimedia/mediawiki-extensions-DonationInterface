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

use SmashPig\Core\DataStores\QueueWrapper;

/**
 *
 * @group Fundraising
 * @group DonationInterface
 * @group Ingenico
 * @group Recurring
 */
class DonationInterface_Adapter_Ingenico_RecurringTest extends BaseIngenicoTestCase {

	/**
	 * This test could span both recurring and non-recurring with the omission
	 * of the tokenize and recurringPaymentSequenceIndicator flags.
	 */
	public function testSetupTokenizedCheckout() {
		$this->setMwGlobals( self::getAllGlobalVariants( [
			'3DSRules' => []
		] ) );
		$init = $this->getDonorTestData( 'FR' );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['email'] = 'innocent@localhost.net';
		$init['recurring'] = 1;
		$this->setUpRequest( $init );
		$gateway = new IngenicoAdapter();
		$this->setUpIntegrationMocks();
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )
			->with(
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->callback( function ( $encoded ) {
					$arg = json_decode( $encoded, true );
					$this->assertArraySubmapSame( [
						'cardPaymentMethodSpecificInput' => [
							'tokenize' => 'true',
							'recurring' => [
								'recurringPaymentSequenceIndicator' => 'first',
							],
							'threeDSecure' => [
								'skipAuthentication' => 'true',
							]
						],
						'hostedCheckoutSpecificInput' => [
							'returnCancelState' => true,
						]
					], $arg );
					return true;
				} ) )
			->willReturn( $this->getGoodHostedCheckoutCurlResponse() );
		$gateway->doPayment();
	}

	public function testProcessTokenizedPayment() {
		$token = '229a1d6e-1b26-4c91-8e00-969a49c9d041';
		$init = $this->getDonorTestData( 'FR' );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['email'] = 'innocent@localhost.net';
		$init['recurring'] = 1;
		$init['order_id'] = mt_rand();
		$session['Donor'] = $init;
		$this->setUpRequest( $init, $session );
		$gateway = new IngenicoAdapter();

		$statusResponse = $this->hostedPaymentStatusResponse->getRawResponse();
		// Sandbox testing shows this potential array flattened to a string
		$statusResponse['createdPaymentOutput']['tokens'] = $token;

		$this->hostedPaymentStatusResponse->setRawResponse( $statusResponse )
			->setSuccessful( true )
			->setRecurringPaymentToken( $token );

		$this->hostedCheckoutProvider->expects( $this->once() )
			->method( 'getLatestPaymentStatus' )
			->willReturn( $this->hostedPaymentStatusResponse );

		$this->hostedCheckoutProvider->method( 'approvePayment' )
			->willReturn( $this->approvePaymentResponse );

		$result = $gateway->processDonorReturn( [
			'merchantReference' => $init['order_id'],
			'cvvResult' => 'M',
			'avsResult' => '0'
		] );
		$this->assertFalse( $result->isFailed() );
		$this->assertSame( [], $result->getErrors() );
		$message = QueueWrapper::getQueue( 'donations' )->pop();
		$this->assertNotNull( $message );
		$this->assertSame( '1', $message['recurring'] );
		$this->assertEquals(
			$token,
			$message['recurring_payment_token']
		);
	}
}
