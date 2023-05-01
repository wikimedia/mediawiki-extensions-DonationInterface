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
use SmashPig\CrmLink\Messages\SourceFields;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\ValidationAction;
use SmashPig\PaymentProviders\Ingenico\PaymentStatus;
use SmashPig\PaymentProviders\Responses\CancelPaymentResponse;
use SmashPig\PaymentProviders\Responses\CreatePaymentSessionResponse;
use SmashPig\PaymentProviders\Responses\PaymentDetailResponse;
use Wikimedia\TestingAccessWrapper;

/**
 *
 * @group Fundraising
 * @group DonationInterface
 * @group Ingenico
 */
class DonationInterface_Adapter_Ingenico_IngenicoTest extends BaseIngenicoTestCase {

	/**
	 * Non-exhaustive integration tests to verify that order_id, when in
	 * self-generation mode, won't regenerate until it is told to.
	 * @covers GatewayAdapter::normalizeOrderID
	 * @covers GatewayAdapter::regenerateOrderID
	 */
	public function testStickyGeneratedOrderID() {
		$init = self::$initial_vars;
		unset( $init['order_id'] );

		// no order_id from anywhere, explicit generate
		$gateway = $this->getFreshGatewayObject( $init, [ 'order_id_meta' => [ 'generate' => true ] ] );
		$this->assertNotNull( $gateway->getData_Unstaged_Escaped( 'order_id' ), 'Generated order_id is null. The rest of this test is broken.' );
		$original_order_id = $gateway->getData_Unstaged_Escaped( 'order_id' );

		$gateway->normalizeOrderID();
		$this->assertEquals( $original_order_id, $gateway->getData_Unstaged_Escaped( 'order_id' ), 'Re-normalized order_id has changed without explicit regeneration.' );

		// this might look a bit strange, but we need to be able to generate valid order_ids without making them stick to anything.
		$gateway->generateOrderID();
		$this->assertEquals( $original_order_id, $gateway->getData_Unstaged_Escaped( 'order_id' ), 'function generateOrderID auto-changed the selected order ID. Not cool.' );

		$gateway->regenerateOrderID();
		$this->assertNotEquals( $original_order_id, $gateway->getData_Unstaged_Escaped( 'order_id' ), 'Re-normalized order_id has not changed, after explicit regeneration.' );
	}

	/**
	 * Integration test to verify that order_id can be retrieved from
	 * performing an createHostedCheckout.
	 */
	public function testGatewaySessionRetrieval() {
		$init = $this->getDonorTestData();
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$this->setUpIntegrationMocks();
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )
			->willReturn( $this->getGoodHostedCheckoutCurlResponse() );
		// no order_id from anywhere, explicit generate
		$gateway = $this->getFreshGatewayObject( $init );
		$gateway->doPayment();

		$this->assertNotNull(
			$gateway->getData_Unstaged_Escaped( 'gateway_session_id' ),
			'No gateway_session_id was retrieved from createHostedCheckout'
		);
	}

	/**
	 * Test we're sending an IP address in the right place
	 */
	public function testSendCustomerIP() {
		$this->setUpIntegrationMocks();
		$init = $this->getDonorTestData();
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$gateway = $this->getFreshGatewayObject( $init );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )
			->with(
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->callback( static function ( $encoded ) use ( $gateway ) {
					$arg = json_decode( $encoded, true );
					return $gateway->getData_Unstaged_Escaped( 'user_ip' ) ===
						$arg['fraudFields']['customerIpAddress'];
				} )
			)
			->willReturn( $this->getGoodHostedCheckoutCurlResponse() );
		$gateway->doPayment();
	}

	/**
	 * Ensure we load the fraud score from the status lookup
	 */
	public function testDonorReturnLoadsFraudScore() {
		$init = $this->getDonorTestData();
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['email'] = 'innocent@safedomain.org';

		$this->setUpRequest( [ 'cvvResult' => 'N' ], [
			'Donor' => $init + [ 'gateway_session_id' => 'blahdedaa' ]
		] );
		$gateway = $this->getFreshGatewayObject( $init );

		$this->hostedCheckoutProvider->expects( $this->once() )
			->method( 'getLatestPaymentStatus' )
			->willReturn(
				$this->hostedPaymentStatusResponseBadCvv
			);

		$gateway->processDonorReturn( [] );

		$this->assertEquals(
			100, $gateway->getCVVResult(), 'CVV Result not loaded from status lookup'
		);
	}

	/**
	 * If the status is pending, we should just show the donor a TY page
	 * and not send a donations queue message. Hopefully it will not be
	 * pending by the time the pending resolver picks it up
	 */
	public function testDonorReturnPending() {
		$init = $this->getDonorTestData();
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['email'] = 'innocent@safedomain.org';

		$this->setUpRequest( [ 'cvvResult' => 'M' ], [
			'Donor' => $init + [ 'gateway_session_id' => 'blahdedaa' ]
		] );
		$gateway = $this->getFreshGatewayObject( $init );

		$hostedPaymentStatusResponse = new PaymentDetailResponse();
		$hostedPaymentStatusResponse->setRawResponse( [
				"status" => "IN_PROGRESS",
			] )
			->setSuccessful( false )
			->setStatus( FinalStatus::PENDING )
			->setRawStatus( 'IN_PROGRESS' );

		$this->hostedCheckoutProvider->expects( $this->once() )
			->method( 'getLatestPaymentStatus' )
			->willReturn( $hostedPaymentStatusResponse );

		$result = $gateway->processDonorReturn( [] );
		$this->assertArrayEquals( [], $result->getErrors(),
			'In Progress status should return no errors' );

		$this->assertFalse( $result->isFailed() );
		$messages = $this->getAllQueueMessages();
		$this->assertSame( [], $messages['donations'] );
	}

	public function testProcessDonorReturnStatusCancelledByConsumer() {
		$init = $this->getDonorTestData();
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['email'] = 'innocent@safedomain.org';

		$this->setUpRequest( [ 'cvvResult' => 'M' ], [
			'Donor' => $init + [ 'gateway_session_id' => 'blahdedaa' ]
		] );
		$gateway = $this->getFreshGatewayObject( $init );

		$hostedPaymentStatusResponse = new PaymentDetailResponse();
		$hostedPaymentStatusResponse->setRawResponse( [
				"status" => "CANCELLED_BY_CONSUMER",
			] )
			->setSuccessful( false )
			->setStatus( FinalStatus::CANCELLED )
			->setRawStatus( 'CANCELLED_BY_CONSUMER' );

		$this->hostedCheckoutProvider->expects( $this->once() )
			->method( 'getLatestPaymentStatus' )
			->willReturn( $hostedPaymentStatusResponse );

		$result = $gateway->processDonorReturn( [] );

		$this->assertEquals( FinalStatus::CANCELLED, $gateway->getFinalStatus() );

		$this->assertTrue( $result->isFailed() );
		$messages = $this->getAllQueueMessages();
		$this->assertSame( [], $messages['donations'] );
	}

	/**
	 * Make sure we're incorporating getLatestPaymentStatus AVS and CVV responses into
	 * fraud scores.
	 */
	public function testGetLatestPaymentStatusPostProcessFraud() {
		$this->setMwGlobals( [
			'wgDonationInterfaceEnableCustomFilters' => true,
			'wgIngenicoGatewayCustomFiltersFunctions' => [
				'getCVVResult' => 10,
				'getAVSResult' => 30,
			],
		] );

		$init = $this->getDonorTestData();
		$init['order_id'] = '55555';
		$init['email'] = 'innocent@manichean.com';
		$init['contribution_tracking_id'] = mt_rand();
		$init['payment_method'] = 'cc';
		$this->setUpRequest( [ 'cvvResult' => 'N' ], [
			'Donor' => $init + [ 'gateway_session_id' => 'blahdedaa' ]
		] );
		$gateway = $this->getFreshGatewayObject( $init );

		$hostedPaymentStatusResponse = new PaymentDetailResponse();
		$hostedPaymentStatusResponse->setRawResponse(
			[
				"createdPaymentOutput" => [
					"payment" => [
						"id" => "000000891566072501680000200001",
						"paymentOutput" => [
							"amountOfMoney" => [
								"amount" => 2345,
								"currencyCode" => "USD"
							],
							"references" => [
								"paymentReference" => "0"
							],
							"paymentMethod" => "card",
							"cardPaymentMethodSpecificOutput" => [
								"paymentProductId" => 1,
								"authorisationCode" => "123456",
								"card" => [
									"cardNumber" => "************7977",
									"expiryDate" => "1220"
								],
								"fraudResults" => [
									"avsResult" => "E",
									"cvvResult" => "N",
									"fraudServiceResult" => "no-advice"
								]
							]
						],
						"status" => "PENDING_APPROVAL",
						"statusOutput" => [
							"isCancellable" => true,
							"statusCode" => 600,
							"statusCodeChangeDateTime" => "20140717145840",
							"isAuthorized" => true
						]
					],
					"paymentCreationReferences" => [
						"additionalReference" => "00000089156607250168",
						"externalReference" => "000000891566072501680000200001"
					],
					"tokens" => ""
				],
				"status" => "PAYMENT_CREATED"
			] )
			->setSuccessful( true )
			->setRiskScores( [
				'cvv' => 100,
				'avs' => 100
			] )
			->setStatus( FinalStatus::PENDING_POKE );

		$this->hostedCheckoutProvider->expects( $this->once() )
			->method( 'getLatestPaymentStatus' )
			->willReturn( $hostedPaymentStatusResponse );

		$gateway->processDonorReturn( [] );
		$action = $gateway->getValidationAction();
		$this->assertEquals( ValidationAction::REVIEW, $action );

		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$this->assertEquals( 40, $exposed->risk_score,
			'Risk score was incremented correctly.' );
	}

	public function testLanguageStaging() {
		$options = $this->getDonorTestData( 'NO' );
		$options['payment_method'] = 'cc';
		$options['payment_submethod'] = 'visa';
		$gateway = $this->getFreshGatewayObject( $options );

		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$exposed->stageData();

		$this->assertEquals( 'no_NO', $exposed->getData_Staged( 'language' ), "'NO' donor's language was improperly set. Should be 'no_NO'" );
	}

	/**
	 * Make sure unstaging functions don't overwrite core donor data.
	 */
	public function testAddResponseData_underzealous() {
		$options = $this->getDonorTestData( 'Catalonia' );
		$options['payment_method'] = 'cc';
		$options['payment_submethod'] = 'visa';
		$gateway = $this->getFreshGatewayObject( $options );

		// This will set staged_data['language'] = 'en'.
		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$exposed->stageData();

		$gateway->addResponseData( [
			'amount' => '2.55',
		] );

		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		// Desired vars were written into normalized data.
		$this->assertEquals( 2.55, $exposed->dataObj->getVal( 'amount' ) );

		// Language was not overwritten.
		$this->assertEquals( 'ca', $exposed->dataObj->getVal( 'language' ) );
	}

	/**
	 * Tests that two API requests don't send the same order ID and merchant
	 * reference.  This was the case when users doubleclicked and we were
	 * using the last 5 digits of time in seconds as a suffix.  We want to see
	 * what happens when a 2nd request comes in while the 1st is still waiting
	 * for a CURL response, so here we fake that situation by having CURL throw
	 * an exception during the 1st response.
	 */
	public function testNoDupeOrderId() {
		$this->setUpRequest( [
			'action' => 'donate',
			'amount' => '3.00',
			'payment_submethod' => 'amex',
			'city' => 'Hollywood',
			'contribution_tracking_id' => '22901382',
			'country' => 'US',
			'currency' => 'USD',
			'email' => 'FaketyFake@gmail.com',
			'first_name' => 'Fakety',
			'full_name' => '',
			'format' => 'json',
			'gateway' => 'ingenico',
			'language' => 'en',
			'last_name' => 'Fake',
			'payment_method' => 'cc',
			'referrer' => 'http://en.wikipedia.org/wiki/Main_Page',
			'state_province' => 'MA',
			'street_address' => '99 Fake St',
			'utm_campaign' => 'C14_en5C_dec_dsk_FR',
			'utm_medium' => 'sitenotice',
			'utm_source' => 'B14_120921_5C_lg_fnt_sans.no-LP.cc',
			'postal_code' => '90210'
		] );

		$gateway = new IngenicoAdapter();
		$calls = [];
		$this->hostedCheckoutProvider->expects( $this->exactly( 2 ) )
			->method( 'createPaymentSession' )
			->with( $this->callback( function ( $arg ) use ( &$calls ) {
				$calls[] = $arg;
				if ( count( $calls ) === 2 ) {
					$this->assertFalse( $calls[0] === $calls[1], 'Two calls to the api did the same thing' );
				}
				return true;
			} ) )
			->will( $this->onConsecutiveCalls(
				$this->throwException( new Exception( 'test' ) ),
				$this->returnValue( ( new CreatePaymentSessionResponse() )
					->setSuccessful( true )
					->setPaymentSession( 'asdasda' )
				)
			) );
		try {
			$gateway->doPayment();
		} catch ( Exception $e ) {
			// totally expected this
		}

		// simulate another request coming in before we get anything back from GC
		$anotherGateway = new IngenicoAdapter();
		$anotherGateway->doPayment();
	}

	/**
	 * Tests that we don't loop when ct_id starts with 4 or 7
	 */
	public function testOrderIdsWith4Or7Ok() {
		$this->setUpRequest( [
			'contribution_tracking_id' => '4012301230',
		] );
		$gateway = new IngenicoAdapter( [
			'external_data' => [
				'contribution_tracking_id' => '4012301230',
			]
		] );
		$orderId = $gateway->generateOrderID();
		$this->assertSame( '4012301230.1', $orderId );

		$this->setUpRequest( [
			'contribution_tracking_id' => '7012301230',
		] );
		$gateway = new IngenicoAdapter( [
			'external_data' => [
				'contribution_tracking_id' => '7012301230',
			]
		] );
		$orderId = $gateway->generateOrderID();
		$this->assertSame( '7012301230.1', $orderId );
	}

	public function testDonorReturnSuccess() {
		$init = $this->getDonorTestData( 'FR' );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['email'] = 'innocent@localhost.net';
		$init['order_id'] = mt_rand();
		$session['Donor'] = $init;
		$this->setUpRequest( $init, $session );
		$gateway = $this->getFreshGatewayObject( [] );
		$this->hostedCheckoutProvider->expects( $this->once() )
			->method( 'getLatestPaymentStatus' )
			->willReturn( $this->hostedPaymentStatusResponse );
		$this->hostedCheckoutProvider->method( 'approvePayment' )
			->with( [ 'gateway_txn_id' => $this->hostedPaymentStatusResponse->getGatewayTxnId() ] )
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
		$this->assertSame( '112233445566', $message['initial_scheme_transaction_id'] );
	}

	public function testDonorReturnFailure() {
		$init = $this->getDonorTestData();
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['email'] = 'innocent@localhost.net';
		$init['order_id'] = mt_rand();
		$session['Donor'] = $init;
		$this->setUpRequest( $init, $session );
		$gateway = $this->getFreshGatewayObject( [] );
		$this->hostedPaymentStatusResponseBadCvv
			->setStatus( FinalStatus::FAILED )
			->setSuccessful( false );
		$this->hostedCheckoutProvider->expects( $this->once() )
			->method( 'getLatestPaymentStatus' )->willReturn(
				$this->hostedPaymentStatusResponseBadCvv
			);
		$result = $gateway->processDonorReturn( [
			'merchantReference' => $init['order_id'],
			'cvvResult' => 'N',
			'avsResult' => '0'
		] );
		$this->assertTrue( $result->isFailed() );
	}

	public function testDonorReturnFailureOptIn() {
		$this->setMwGlobals( [
			'wgDonationInterfaceSendOptInOnFailure' => true
		] );
		$init = $this->getDonorTestData();
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['email'] = 'innocent@localhost.net';
		$init['opt_in'] = '1';
		$init['full_name'] = null;
		$init['order_id'] = mt_rand();
		$session['Donor'] = $init;
		$this->setUpRequest( $init, $session );
		$gateway = $this->getFreshGatewayObject( [] );
		$this->hostedPaymentStatusResponseBadCvv
			->setStatus( FinalStatus::FAILED )
			->setSuccessful( false );
		$this->hostedCheckoutProvider->expects( $this->once() )
			->method( 'getLatestPaymentStatus' )->willReturn(
				$this->hostedPaymentStatusResponseBadCvv
			);
		$result = $gateway->processDonorReturn( [
			'merchantReference' => $init['order_id'],
			'cvvResult' => 'N',
			'avsResult' => '0'
		] );
		$this->assertTrue( $result->isFailed() );
		$queueMessage = QueueWrapper::getQueue( 'opt-in' )->pop();
		SourceFields::removeFromMessage( $queueMessage );
		$contactFields = array_fill_keys( DonationData::getContactFields(), '' );
		$this->assertEquals( array_intersect_key( $init, $contactFields ), $queueMessage );
	}

	public function testDonorReturnPaymentSubmethod() {
		$init = $this->getDonorTestData( 'FR' );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = '';
		$init['email'] = 'innocent@localhost.net';
		$init['order_id'] = mt_rand();
		$session['Donor'] = $init;
		$this->setUpRequest( $init, $session );

		$gateway = $this->getFreshGatewayObject( [] );
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
		$queueMessage = QueueWrapper::getQueue( 'payments-init' )->pop();
		SourceFields::removeFromMessage( $queueMessage );
		$this->assertEquals( 'visa', $queueMessage['payment_submethod'] );
	}

	public function testClearDataWhenDone() {
		$init = $this->getDonorTestData( 'FR' );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['email'] = 'innocent@localhost.net';
		$init['order_id'] = mt_rand();
		$session['Donor'] = $init;
		$firstRequest = $this->setUpRequest( $init, $session );

		$gateway = $this->getFreshGatewayObject( [] );
		$firstCt_id = $gateway->getData_Unstaged_Escaped( 'contribution_tracking_id' );
		$this->hostedCheckoutProvider->method( 'getLatestPaymentStatus' )
			->willReturn( $this->hostedPaymentStatusResponse );
		$this->hostedCheckoutProvider->method( 'approvePayment' )
			->willReturn( $this->approvePaymentResponse );

		$gateway->processDonorReturn( [
			'merchantReference' => $init['order_id'],
			'cvvResult' => 'M',
			'avsResult' => '0'
		] );

		$resultingSession = $firstRequest->getSessionArray();

		$this->setUpRequest( $init, $resultingSession );
		$anotherGateway = $this->getFreshGatewayObject( [] );
		$secondCt_id = $anotherGateway->getData_Unstaged_Escaped( 'contribution_tracking_id' );

		$this->assertNotEquals( $firstCt_id, $secondCt_id, 'ct_id not cleared.' );
	}

	public function testDoPayment() {
		$init = $this->getDonorTestData();
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$gateway = $this->getFreshGatewayObject( $init );
		$this->setUpIntegrationMocks();
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )->with(
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->callback( function ( $encoded ) {
					$actual = json_decode( $encoded, true );
					$hcsi = [
						'locale' => 'en_US',
						'paymentProductFilters' => [
							'restrictTo' => [
								'groups' => [
									'cards'
								]
							]
						],
						'showResultPage' => false
					];
					$this->assertArraySubmapSame( $hcsi, $actual['hostedCheckoutSpecificInput'] );
					$this->assertRegExp(
						'/Special:IngenicoGatewayResult/',
						$actual['hostedCheckoutSpecificInput']['returnUrl']
					);
					$order = [
						'amountOfMoney' => [
							'currencyCode' => 'USD',
							'amount' => '455'
						],
						'customer' => [
							'billingAddress' => [
								'countryCode' => 'US',
								'city' => 'San Francisco',
								'state' => 'CA',
								'zip' => '94105',
								'street' => '123 Fake Street'
							],
							'contactDetails' => [
								'emailAddress' => 'nobody@wikimedia.org'
							],
							'locale' => 'en_US',
						]
					];
					$this->assertArraySubmapSame( $order, $actual['order'] );
					$this->assertTrue( is_numeric( $actual['order']['references']['merchantReference'] ) );
					return true;
				} )
			)
			->willReturn( $this->getGoodHostedCheckoutCurlResponse() );

		$result = $gateway->doPayment();
		$this->assertEquals(
			'https://wmf-pay.' . $this->partialUrl, $result->getIframe()
		);
		$this->assertSame( [], $result->getErrors() );
	}

	public function testDoPaymentFailInitialFilters() {
		$this->setInitialFiltersToFail();
		$init = DonationInterfaceTestCase::getDonorTestData();
		$init['email'] = 'good@innocent.com';
		$init['postal_code'] = 'T3 5TA';
		$init['payment_method'] = 'cc';
		$gateway = $this->getFreshGatewayObject( $init );

		// Should not make any API calls
		$this->hostedCheckoutProvider->expects( $this->never() )
			->method( $this->anything() );

		$result = $gateway->doPayment();

		$this->assertNotEmpty( $result->getErrors(), 'Should have returned an error' );
	}

	protected function getCancelPaymentResponse(): CancelPaymentResponse {
		$response = new CancelPaymentResponse();
		$rawResponse = [
			"payment" => [
				"id" => "000000850010000188180000200001",
				"paymentOutput" => [
					"amountOfMoney" => [
						"amount" => 2890,
						"currencyCode" => "EUR"
					],
					"references" => [
						"merchantReference" => "merchantReference",
						"paymentReference" => "0"
					],
					"paymentMethod" => "card",
					"cardPaymentMethodSpecificOutput" => [
						"paymentProductId" => 1,
						"authorisationCode" => "726747",
						"card" => [
							"cardNumber" => "************7977",
							"expiryDate" => "1220"
						],
						"fraudResults" => [
							"avsResult" => "0",
							"cvvResult" => "0",
							"fraudServiceResult" => "no-advice"
						]
					]
				],
				"status" => "CANCELLED",
				"statusOutput" => [
					"isCancellable" => false,
					"statusCode" => 99999,
					"statusCodeChangeDateTime" => "20150223153431"
				]
			],
			"cardPaymentMethodSpecificOutput" => [
				"voidResponseId" => "0"
			]
		];
		$response->setRawResponse( $rawResponse );
		$response->setGatewayTxnId( $rawResponse['payment']['id'] );
		$status = ( new PaymentStatus() )->normalizeStatus( $rawResponse['payment']['status'] );
		$response->setStatus( $status );
		$response->setSuccessful( true );
		return $response;
	}

}
