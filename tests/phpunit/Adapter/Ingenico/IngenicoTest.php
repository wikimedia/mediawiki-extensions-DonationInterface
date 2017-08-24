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
use Wikimedia\TestingAccessWrapper;

/**
 *
 * @group Fundraising
 * @group DonationInterface
 * @group Ingenico
 */
class DonationInterface_Adapter_Ingenico_IngenicoTest extends BaseIngenicoTestCase {

	protected $partialUrl;

	protected $hostedCheckoutCreateResponse;

	public function setUp() {
		parent::setUp();

		$this->partialUrl = 'poweredbyglobalcollect.com/pay8915-53ebca407e6b4a1dbd086aad4f10354d:' .
			'8915-28e5b79c889641c8ba770f1ba576c1fe:9798f4c44ac6406e8288494332d1daa0';

		$this->hostedCheckoutCreateResponse = array(
			'partialRedirectUrl' => $this->partialUrl,
			'hostedCheckoutId' => '8915-28e5b79c889641c8ba770f1ba576c1fe',
			'RETURNMAC' => 'f5b66cf9-c64c-4c8d-8171-b47205c89a56'
		);
	}

	/**
	 * Non-exhaustive integration tests to verify that order_id, when in
	 * self-generation mode, won't regenerate until it is told to.
	 * @covers GatewayAdapter::normalizeOrderID
	 * @covers GatewayAdapter::regenerateOrderID
	 */
	function testStickyGeneratedOrderID() {
		$init = self::$initial_vars;
		unset( $init['order_id'] );

		//no order_id from anywhere, explicit generate
		$gateway = $this->getFreshGatewayObject( $init, array ( 'order_id_meta' => array ( 'generate' => TRUE ) ) );
		$this->assertNotNull( $gateway->getData_Unstaged_Escaped( 'order_id' ), 'Generated order_id is null. The rest of this test is broken.' );
		$original_order_id = $gateway->getData_Unstaged_Escaped( 'order_id' );

		$gateway->normalizeOrderID();
		$this->assertEquals( $original_order_id, $gateway->getData_Unstaged_Escaped( 'order_id' ), 'Re-normalized order_id has changed without explicit regeneration.' );

		//this might look a bit strange, but we need to be able to generate valid order_ids without making them stick to anything.
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
		//no order_id from anywhere, explicit generate
		$gateway = $this->getFreshGatewayObject( $init );
		$gateway->do_transaction( 'createHostedCheckout' );

		$this->assertNotNull(
			$gateway->getData_Unstaged_Escaped( 'gateway_session_id' ),
			'No gateway_session_id was retrieved from createHostedCheckout'
		);
	}

	/**
	 * Just run the GET_ORDERSTATUS transaction and make sure we load the data
	 */
	function testGetOrderStatus() {
		$this->markTestSkipped( 'OrderStatus not implemented' );
		$init = $this->getDonorTestData();
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['email'] = 'innocent@safedomain.org';

		$gateway = $this->getFreshGatewayObject( $init );

		$gateway->do_transaction( 'GET_ORDERSTATUS' );

		$data = $gateway->getTransactionData();

		$this->assertEquals( 'N', $data['CVVRESULT'], 'CVV Result not loaded from XML response' );
	}

	/**
	 * Don't fraud-fail someone for bad CVV if GET_ORDERSTATUS
	 * comes back with STATUSID 25 and no CVVRESULT
	 * @group CvvResult
	 */
	function testConfirmCreditCardStatus25() {
		$this->markTestSkipped( 'OrderStatus not implemented' );
		$init = $this->getDonorTestData();
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['email'] = 'innocent@safedomain.org';

		$this->setUpRequest( array( 'CVVRESULT' => 'M' ) );

		$gateway = $this->getFreshGatewayObject( $init );
		self::setDummyGatewayResponseCode( '25' );

		$gateway->do_transaction( 'Confirm_CreditCard' );
		$action = $gateway->getValidationAction();
		$this->assertEquals( 'process', $action, 'Gateway should not fraud fail on STATUSID 25' );
	}

	/**
	 * Make sure we're incorporating GET_ORDERSTATUS AVS and CVV responses into
	 * fraud scores.
	 */
	function testGetOrderstatusPostProcessFraud() {
		$this->markTestSkipped( 'OrderStatus not implemented' );
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

		self::setDummyGatewayResponseCode( '600_badCvv' );

		$gateway->do_transaction( 'Confirm_CreditCard' );
		$action = $gateway->getValidationAction();
		$this->assertEquals( 'review', $action,
			'Orphan gateway should fraud fail on bad CVV and AVS' );

		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$this->assertEquals( 40, $exposed->risk_score,
			'Risk score was incremented correctly.' );
	}

	/**
	 * Ensure the Confirm_CreditCard transaction prefers CVVRESULT from the XML
	 * over any value from the querystring
	 */
	function testConfirmCreditCardPrefersApiCvv() {
		$this->markTestSkipped( 'OrderStatus not implemented' );
		$init = $this->getDonorTestData();
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['email'] = 'innocent@safedomain.org';

		$this->setUpRequest( array( 'CVVRESULT' => 'M' ) );

		$gateway = $this->getFreshGatewayObject( $init );

		$gateway->do_transaction( 'Confirm_CreditCard' );

		$this->assertEquals( 'N', $gateway->getData_Unstaged_Escaped('cvv_result'), 'CVV Result not taken from XML response' );
	}

	/**
	 * Make sure we record the actual amount charged, even if the donor has
	 * opened a new window and screwed up their session data.
	 */
	function testConfirmCreditCardUpdatesAmount() {
		$this->markTestSkipped( 'OrderStatus not implemented' );
		$init = $this->getDonorTestData();
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['email'] = 'innocent@safedomain.org';
		// The values in session are not the values we originally used
		// for createHostedCheckout
		$init['amount'] = '12.50';
		$init['currency'] = 'USD';

		$gateway = $this->getFreshGatewayObject( $init );

		$amount = $gateway->getData_Unstaged_Escaped( 'amount' );
		$currency = $gateway->getData_Unstaged_Escaped( 'currency' );
		$this->assertEquals( '12.50', $amount );
		$this->assertEquals( 'USD', $currency );

		$gateway->do_transaction( 'Confirm_CreditCard' );

		$amount = $gateway->getData_Unstaged_Escaped( 'amount' );
		$currency = $gateway->getData_Unstaged_Escaped( 'currency' );
		$this->assertEquals( '23.45', $amount, 'Not recording correct amount' );
		$this->assertEquals( 'EUR', $currency, 'Not recording correct currency'  );
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

	public function testLanguageFallbackStaging() {
		$this->markTestSkipped( 'Do we have to fall back with Connect?' );
		$options = $this->getDonorTestData( 'Catalonia' );
		$options['payment_method'] = 'cc';
		$options['payment_submethod'] = 'visa';
		$gateway = $this->getFreshGatewayObject( $options );

		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$exposed->stageData();

		// Requesting the fallback language from the gateway.
		$this->assertEquals( 'en', $exposed->getData_Staged( 'language' ) );
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
	 * Tests to make sure that certain error codes returned from GC will or
	 * will not create payments error loglines.
	 */
	function testCCLogsOnGatewayError() {
		$this->markTestSkipped( 'order status not implemented' );
		$init = $this->getDonorTestData( 'US' );
		unset( $init['order_id'] );
		$init['ffname'] = 'cc-vmad';

		//this should not throw any payments errors: Just an invalid card.
		$gateway = $this->getFreshGatewayObject( $init );
		self::setDummyGatewayResponseCode( '430285' );
		$gateway->do_transaction( 'GET_ORDERSTATUS' );
		$this->verifyNoLogErrors();

		//Now test one we want to throw a payments error
		$gateway = $this->getFreshGatewayObject( $init );
		self::setDummyGatewayResponseCode( '21000050' );
		$gateway->do_transaction( 'GET_ORDERSTATUS' );
		$loglines = $this->getLogMatches( LogLevel::ERROR, '/Investigation required!/' );
		$this->assertNotEmpty( $loglines, 'GC Error 21000050 is not generating the expected payments log error' );

		//Reset logs
		$this->testLogger->messages = array();

		//Most irritating version of 20001000 - They failed to enter an expiration date on GC's form. This should log some specific info, but not an error.
		$gateway = $this->getFreshGatewayObject( $init );
		self::setDummyGatewayResponseCode( '20001000-expiry' );
		$gateway->do_transaction( 'GET_ORDERSTATUS' );
		$this->verifyNoLogErrors();
		$loglines = $this->getLogMatches( LogLevel::INFO, '/processResponse:.*EXPIRYDATE/' );
		$this->assertNotEmpty( $loglines, 'GC Error 20001000-expiry is not generating the expected payments log line' );
	}

	/**
	 * Tests to make sure that certain error codes returned from GC will
	 * trigger order cancellation, even if retryable errors also exist.
	 * @dataProvider mcNoRetryCodeProvider
	 */
	public function testNoMastercardFinesForRepeatOnBadCodes( $code ) {
		$this->markTestSkipped( 'OrderStatus not implemented' );
		$init = $this->getDonorTestData( 'US' );
		unset( $init['order_id'] );
		$init['ffname'] = 'cc-vmad';
		//Make it not look like an orphan
		$this->setUpRequest( array(
			'CVVRESULT' => 'M',
			'AVSRESULT' => '0'
		) );

		//Toxic card should not retry, even if there's an order id collision
		$gateway = $this->getFreshGatewayObject( $init );
		self::setDummyGatewayResponseCode( $code );
		$gateway->do_transaction( 'Confirm_CreditCard' );
		$this->assertEquals( 1, count( $gateway->curled ), "Gateway kept trying even with response code $code!  Mastercard could fine us a thousand bucks for that!" );
	}

	/**
	 * Tests that two API requests don't send the same order ID and merchant
	 * reference.  This was the case when users doubleclicked and we were
	 * using the last 5 digits of time in seconds as a suffix.  We want to see
	 * what happens when a 2nd request comes in while the 1st is still waiting
	 * for a CURL response, so here we fake that situation by having CURL throw
	 * an exception during the 1st response.
	 */
	public function testNoDupeOrderId( ) {
		$this->setUpRequest( array(
			'action'=>'donate',
			'amount'=>'3.00',
			'card_type'=>'amex',
			'city'=>'Hollywood',
			'contribution_tracking_id'=>'22901382',
			'country'=>'US',
			'currency'=>'USD',
			'email'=>'FaketyFake@gmail.com',
			'first_name'=>'Fakety',
			'format'=>'json',
			'gateway'=>'ingenico',
			'language'=>'en',
			'last_name'=>'Fake',
			'payment_method'=>'cc',
			'referrer'=>'http://en.wikipedia.org/wiki/Main_Page',
			'state_province'=>'MA',
			'street_address'=>'99 Fake St',
			'utm_campaign'=>'C14_en5C_dec_dsk_FR',
			'utm_medium'=>'sitenotice',
			'utm_source'=>'B14_120921_5C_lg_fnt_sans.no-LP.cc',
			'postal_code'=>'90210'
		) );

		$gateway = new IngenicoAdapter();
		$calls = [];
		$this->hostedCheckoutProvider->expects( $this->exactly( 2 ) )
			->method( 'createHostedPayment' )
			->with( $this->callback( function( $arg ) use ( &$calls ) {
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
		}
		catch ( Exception $e ) {
			// totally expected this
		}

		//simulate another request coming in before we get anything back from GC
		$anotherGateway = new IngenicoAdapter();
		$anotherGateway->do_transaction( 'createHostedCheckout' );

	}

	/**
	 * Tests to see that we don't claim we're going to retry when we aren't
	 * going to. For GC, we really only want to retry on code 300620
	 * @dataProvider benignNoRetryCodeProvider
	 */
	public function testNoClaimRetryOnBoringCodes( $code ) {
		$this->markTestSkipped( 'OrderStatus not implemented' );
		$init = $this->getDonorTestData( 'US' );
		unset( $init['order_id'] );
		$init['ffname'] = 'cc-vmad';
		//Make it not look like an orphan
		$this->setUpRequest( array(
			'CVVRESULT' => 'M',
			'AVSRESULT' => '0'
		) );

		$gateway = $this->getFreshGatewayObject( $init );
		self::setDummyGatewayResponseCode( $code );
		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$start_id = $exposed->getData_Staged( 'order_id' );
		$gateway->do_transaction( 'Confirm_CreditCard' );
		$finish_id = $exposed->getData_Staged( 'order_id' );
		$loglines = $this->getLogMatches( LogLevel::INFO, '/Repeating transaction on request for vars:/' );
		$this->assertEmpty( $loglines, "Log says we are going to repeat the transaction for code $code, but that is not true" );
		$this->assertEquals( $start_id, $finish_id, "Needlessly regenerated order id for code $code ");
	}

	/**
	 * doPayment should recover from Ingenico-side timeouts.
	 */
	function testTimeoutRecover() {
		$this->markTestSkipped( 'SetPayment not implemented' );
		$init = $this->getDonorTestData();
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['email'] = 'innocent@localhost.net';
		$init['ffname'] = 'cc-vmad';

		$gateway = $this->getFreshGatewayObject( $init );

		$gateway->do_transaction( 'SET_PAYMENT' );
		$loglines = $this->getLogMatches( LogLevel::INFO, '/Repeating transaction for timeout/' );
		$this->assertNotEmpty( $loglines, "Log does not say we retried for timeout." );
	}

	public function testDonorReturnSuccess() {
		$this->markTestSkipped( 'SetPayment not implemented' );
		$init = $this->getDonorTestData( 'FR' );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['email'] = 'innocent@localhost.net';
		$init['order_id'] = mt_rand();
		$session['Donor'] = $init;
		$this->setUpRequest( $init, $session );
		$gateway = $this->getFreshGatewayObject( array() );
		$result = $gateway->processDonorReturn( array(
			'REF' => $init['order_id'],
			'CVVRESULT' => 'M',
			'AVSRESULT' => '0'
		) );
		$this->assertFalse( $result->isFailed() );
		$this->assertEmpty( $result->getErrors() );
		// TODO inspect the queue message
	}

	public function testDonorReturnFailure() {
		$this->markTestSkipped( 'OrderStatus not implemented' );
		$init = $this->getDonorTestData();
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['email'] = 'innocent@localhost.net';
		$init['order_id'] = mt_rand();
		$session['Donor'] = $init;
		$this->setUpRequest( $init, $session );
		$gateway = $this->getFreshGatewayObject( array() );
		self::setDummyGatewayResponseCode( '430285' ); // invalid card
		$result = $gateway->processDonorReturn( array(
			'REF' => $init['order_id'],
			'CVVRESULT' => 'M',
			'AVSRESULT' => '0'
		) );
		$this->assertTrue( $result->isFailed() );
	}
}
