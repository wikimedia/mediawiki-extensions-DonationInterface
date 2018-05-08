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

use Psr\Log\LogLevel;
use SmashPig\CrmLink\ValidationAction;
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
	function testStickyGeneratedOrderID() {
		$init = self::$initial_vars;
		unset( $init['order_id'] );

		// no order_id from anywhere, explicit generate
		$gateway = $this->getFreshGatewayObject( $init, array( 'order_id_meta' => array( 'generate' => true ) ) );
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
	function testGatewaySessionRetrieval() {
		$init = $this->getDonorTestData();
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$this->hostedCheckoutProvider->expects( $this->once() )
			->method( 'createHostedPayment' )
			->willReturn(
				$this->hostedCheckoutCreateResponse
			);
		// no order_id from anywhere, explicit generate
		$gateway = $this->getFreshGatewayObject( $init );
		$gateway->do_transaction( 'createHostedCheckout' );

		$this->assertNotNull(
			$gateway->getData_Unstaged_Escaped( 'gateway_session_id' ),
			'No gateway_session_id was retrieved from createHostedCheckout'
		);
	}

	/**
	 * Just run the getHostedCheckoutStatus transaction and make sure we load the data
	 */
	function testGetHostedPaymentStatus() {
		$init = $this->getDonorTestData();
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['email'] = 'innocent@safedomain.org';

		$gateway = $this->getFreshGatewayObject( $init );

		$this->hostedCheckoutProvider->expects( $this->once() )
			->method( 'getHostedPaymentStatus' )
			->willReturn(
				$this->hostedPaymentStatusResponse
			);

		$gateway->do_transaction( 'getHostedPaymentStatus' );

		$data = $gateway->getTransactionData();

		$this->assertEquals( 'M', $data['cvvResult'], 'CVV Result not loaded from JSON response' );
	}

	/**
	 * Don't fraud-fail someone for bad CVV if GET_ORDERSTATUS
	 * comes back with STATUSID 25 and no CVVRESULT
	 * @group CvvResult
	 */
	function testGetHostedPaymentStatus25() {
		$init = $this->getDonorTestData();
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['email'] = 'innocent@safedomain.org';

		$this->setUpRequest( array( 'cvvResult' => 'M' ) );

		$gateway = $this->getFreshGatewayObject( $init );
		$this->hostedCheckoutProvider->expects( $this->once() )
			->method( 'getHostedPaymentStatus' )
			->willReturn(
				array(
					"createdPaymentOutput" => array(
						"payment" => array(
							"id" => "000000891566072501680000200001",
							"paymentOutput" => array(
								"amountOfMoney" => array(
									"amount" => 2345,
									"currencyCode" => "USD"
								),
								"references" => array(
									"paymentReference" => "0"
								),
								"paymentMethod" => "card",
								"cardPaymentMethodSpecificOutput" => array(
									"paymentProductId" => 1,
									"authorisationCode" => "123456",
									"card" => array(
										"cardNumber" => "************7977",
										"expiryDate" => "1220"
									),
									"fraudResults" => array(
										"fraudServiceResult" => "no-advice"
									)
								)
							),
							"status" => "APPROVED",
							"statusOutput" => array(
								"isCancellable" => true,
								"statusCode" => 25,
								"statusCodeChangeDateTime" => "20140717145840",
								"isAuthorized" => true
							)
						),
						"paymentCreationReferences" => array(
							"additionalReference" => "00000089156607250168",
							"externalReference" => "000000891566072501680000200001"
						),
						"tokens" => ""
					),
					"status" => "PAYMENT_APPROVED"
				)

			);

		$gateway->do_transaction( 'getHostedPaymentStatus' );
		$action = $gateway->getValidationAction();
		$this->assertEquals( ValidationAction::PROCESS, $action, 'Gateway should not fraud fail on statusCode 25' );
	}

	/**
	 * Make sure we're incorporating getHostedPaymentStatus AVS and CVV responses into
	 * fraud scores.
	 */
	function testGetHostedPaymentStatusPostProcessFraud() {
		$this->setMwGlobals( array(
			'wgDonationInterfaceEnableCustomFilters' => true,
			'wgIngenicoGatewayCustomFiltersFunctions' => array(
				'getCVVResult' => 10,
				'getAVSResult' => 30,
			),
		) );

		$init = $this->getDonorTestData();
		$init['ffname'] = 'cc-vmad';
		$init['order_id'] = '55555';
		$init['email'] = 'innocent@manichean.com';
		$init['contribution_tracking_id'] = mt_rand();
		$init['payment_method'] = 'cc';
		$gateway = $this->getFreshGatewayObject( $init );

		$this->hostedCheckoutProvider->expects( $this->once() )
			->method( 'getHostedPaymentStatus' )
			->willReturn(
				array(
					"createdPaymentOutput" => array(
						"payment" => array(
							"id" => "000000891566072501680000200001",
							"paymentOutput" => array(
								"amountOfMoney" => array(
									"amount" => 2345,
									"currencyCode" => "USD"
								),
								"references" => array(
									"paymentReference" => "0"
								),
								"paymentMethod" => "card",
								"cardPaymentMethodSpecificOutput" => array(
									"paymentProductId" => 1,
									"authorisationCode" => "123456",
									"card" => array(
										"cardNumber" => "************7977",
										"expiryDate" => "1220"
									),
									"fraudResults" => array(
										"avsResult" => "E",
										"cvvResult" => "N",
										"fraudServiceResult" => "no-advice"
									)
								)
							),
							"status" => "PENDING_APPROVAL",
							"statusOutput" => array(
								"isCancellable" => true,
								"statusCode" => 600,
								"statusCodeChangeDateTime" => "20140717145840",
								"isAuthorized" => true
							)
						),
						"paymentCreationReferences" => array(
							"additionalReference" => "00000089156607250168",
							"externalReference" => "000000891566072501680000200001"
						),
						"tokens" => ""
					),
					"status" => "PAYMENT_CREATED"
				)

			);

		$gateway->do_transaction( 'getHostedPaymentStatus' );
		$action = $gateway->getValidationAction();
		$this->assertEquals( ValidationAction::REVIEW, $action,
			'Orphan gateway should fraud fail on bad CVV and AVS' );

		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$this->assertEquals( 40, $exposed->risk_score,
			'Risk score was incremented correctly.' );
	}

	public function testApprovePayment() {
		$init = $this->getDonorTestData();
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['email'] = 'innocent@safedomain.org';
		$init['gateway_txn_id'] = 'ingenico' . $init['gateway_session_id'];
		$gateway = $this->getFreshGatewayObject( $init );
		$this->hostedCheckoutProvider->expects( $this->once() )
			->method( 'approvePayment' )
			->willReturn(
				array(
					"payment" => array(
						"id" => "000000850010000188180000200001",
						"paymentOutput" => array(
							"amountOfMoney" => array(
								"amount" => 2890,
								"currencyCode" => "EUR"
							),
							"references" => array(
								"paymentReference" => "0"
							),
							"paymentMethod" => "card",
							"cardPaymentMethodSpecificOutput" => array(
								"paymentProductId" => 1,
								"authorisationCode" => "123456",
								"card" => array(
									"cardNumber" => "************7977",
									"expiryDate" => "1220"
								),
								"fraudResults" => array(
									"avsResult" => "0",
									"cvvResult" => "M",
									"fraudServiceResult" => "no-advice"
								)
							)
						),
						"status" => "CAPTURE_REQUESTED",
						"statusOutput" => array(
							"isCancellable" => false,
							"statusCode" => 800,
							"statusCodeChangeDateTime" => "20140627140735",
							"isAuthorized" => true
						)
					)
				)
			);
		$gateway->do_transaction( 'approvePayment' );
		$data = $gateway->getTransactionData();
		$this->assertEquals( "CAPTURE_REQUESTED", $data['status'], "Should return status CAPTURE_REQUESTED" );
	}

	public function testLanguageStaging() {
		$options = $this->getDonorTestData( 'NO' );
		$options['payment_method'] = 'cc';
		$options['payment_submethod'] = 'visa';
		$gateway = $this->getFreshGatewayObject( $options );

		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$exposed->stageData();

		$this->assertEquals( $exposed->getData_Staged( 'language' ), 'no_NO', "'NO' donor's language was improperly set. Should be 'no_NO'" );
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

		$ctid = mt_rand();

		$gateway->addResponseData( array(
			'contribution_tracking_id' => $ctid . '.1',
		) );

		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		// Desired vars were written into normalized data.
		$this->assertEquals( $ctid, $exposed->dataObj->getVal( 'contribution_tracking_id' ) );

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
		$this->setUpRequest( array(
			'action' => 'donate',
			'amount' => '3.00',
			'card_type' => 'amex',
			'city' => 'Hollywood',
			'contribution_tracking_id' => '22901382',
			'country' => 'US',
			'currency' => 'USD',
			'email' => 'FaketyFake@gmail.com',
			'first_name' => 'Fakety',
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
		) );

		$gateway = new IngenicoAdapter();
		$calls = [];
		$this->hostedCheckoutProvider->expects( $this->exactly( 2 ) )
			->method( 'createHostedPayment' )
			->with( $this->callback( function ( $arg ) use ( &$calls ) {
				$calls[] = $arg;
				if ( count( $calls ) === 2 ) {
					$this->assertFalse( $calls[0] === $calls[1], 'Two calls to the api did the same thing' );
				}
				return true;
			} ) )
			->will( $this->onConsecutiveCalls(
				$this->throwException( new Exception( 'test' ) ),
				$this->returnValue( $this->hostedCheckoutCreateResponse )
			) );
		try {
			$gateway->do_transaction( 'createHostedCheckout' );
		} catch ( Exception $e ) {
			// totally expected this
		}

		// simulate another request coming in before we get anything back from GC
		$anotherGateway = new IngenicoAdapter();
		$anotherGateway->do_transaction( 'createHostedCheckout' );
	}

	public function testDonorReturnSuccess() {
		$init = $this->getDonorTestData( 'FR' );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['email'] = 'innocent@localhost.net';
		$init['order_id'] = mt_rand();
		$session['Donor'] = $init;
		$this->setUpRequest( $init, $session );
		$gateway = $this->getFreshGatewayObject( array() );
		$this->hostedCheckoutProvider->expects( $this->once() )
			->method( 'getHostedPaymentStatus' )
			->willReturn( $this->hostedPaymentStatusResponse );
		$this->hostedCheckoutProvider->method( 'approvePayment' )
			->willReturn( $this->approvePaymentResponse );
		$result = $gateway->processDonorReturn( array(
			'merchantReference' => $init['order_id'],
			'cvvResult' => 'M',
			'avsResult' => '0'
		) );
		$this->assertFalse( $result->isFailed() );
		$this->assertEmpty( $result->getErrors() );
		// TODO inspect the queue message
	}

	public function testDonorReturnFailure() {
		$init = $this->getDonorTestData();
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['email'] = 'innocent@localhost.net';
		$init['order_id'] = mt_rand();
		$session['Donor'] = $init;
		$this->setUpRequest( $init, $session );
		$gateway = $this->getFreshGatewayObject( array() );
		$this->hostedCheckoutProvider->expects( $this->once() )
			->method( 'getHostedPaymentStatus' )->willReturn(
				$this->hostedPaymentStatusResponseBadCvv
			);
		$result = $gateway->processDonorReturn( array(
			'merchantReference' => $init['order_id'],
			'cvvResult' => 'N',
			'avsResult' => '0'
		) );
		$this->assertTrue( $result->isFailed() );
	}

	public function testClearDataWhenDone() {

		$init = $this->getDonorTestData( 'FR' );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['email'] = 'innocent@localhost.net';
		$init['order_id'] = mt_rand();
		$session['Donor'] = $init;
		$firstRequest = $this->setUpRequest( $init, $session );

		$gateway = $this->getFreshGatewayObject( array() );
		$firstCt_id = $gateway->getData_Unstaged_Escaped( 'contribution_tracking_id' );
		$this->hostedCheckoutProvider->method( 'getHostedPaymentStatus' )
			->willReturn( $this->hostedPaymentStatusResponse );
		$this->hostedCheckoutProvider->method( 'approvePayment' )
			->willReturn( $this->approvePaymentResponse );

		$gateway->processDonorReturn( array(
			'merchantReference' => $init['order_id'],
			'cvvResult' => 'M',
			'avsResult' => '0'
		) );

		$resultingSession = $firstRequest->getSessionArray();

		$this->setUpRequest( $init, $resultingSession );
		$anotherGateway = $this->getFreshGatewayObject( array() );
		$secondCt_id = $anotherGateway->getData_Unstaged_Escaped( 'contribution_tracking_id' );

		$this->assertNotEquals( $firstCt_id, $secondCt_id, 'ct_id not cleared.');
	}
}
