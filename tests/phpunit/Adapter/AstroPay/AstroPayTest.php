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

use \Psr\Log\LogLevel;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\CrmLink\Messages\SourceFields;
use SmashPig\CrmLink\FinalStatus;
use SmashPig\CrmLink\ValidationAction;
use SmashPig\Tests\TestingContext;
use SmashPig\Tests\TestingProviderConfiguration;
use Wikimedia\TestingAccessWrapper;

/**
 *
 * @group Fundraising
 * @group DonationInterface
 * @group AstroPay
 */
class DonationInterface_Adapter_AstroPay_AstroPayTest extends DonationInterfaceTestCase {

	/**
	 * @param string $name The name of the test case
	 * @param array $data Any parameters read from a dataProvider
	 * @param string|int $dataName The name or index of the data set
	 */
	public function __construct( $name = null, array $data = [], $dataName = '' ) {
		parent::__construct( $name, $data, $dataName );
		$this->testAdapterClass = TestingAstroPayAdapter::class;
	}

	public function setUp() {
		parent::setUp();
		$this->setMwGlobals( [
			'wgAstroPayGatewayEnabled' => true,
		] );
		TestingContext::get()->providerConfigurationOverride =
			TestingProviderConfiguration::createForProvider(
				'astropay',
				$this->smashPigGlobalConfig
			);
	}

	/**
	 * Ensure we're setting the right url for each transaction
	 * @covers AstroPayAdapter::getCurlBaseOpts
	 */
	public function testCurlUrl() {
		$init = $this->getDonorTestData( 'BR' );
		$gateway = $this->getFreshGatewayObject( $init );
		$gateway->setCurrentTransaction( 'NewInvoice' );
		$accessible = TestingAccessWrapper::newFromObject( $gateway );

		$result = $accessible->getCurlBaseOpts();

		$this->assertEquals(
			'https://sandbox.astropay.example.com/api_curl/streamline/NewInvoice',
			$result[CURLOPT_URL],
			'Not setting URL to transaction-specific value.'
		);
	}

	/**
	 * Test the NewInvoice transaction is making a sane request and signing
	 * it correctly
	 */
	public function testNewInvoiceRequest() {
		$init = $this->getDonorTestData( 'BR' );
		$session['Donor']['order_id'] = '123456789';
		$this->setUpRequest( $init, $session );
		$this->setLanguage( $init['language'] );
		$gateway = new TestingAstroPayAdapter();

		$gateway->do_transaction( 'NewInvoice' );
		parse_str( $gateway->curled[0], $actual );

		$expected = [
			'x_login' => 'createlogin',
			'x_trans_key' => 'createpass',
			'x_invoice' => '123456789',
			'x_amount' => '100.00',
			'x_currency' => 'BRL',
			'x_bank' => 'TE',
			'x_country' => 'BR',
			'x_description' => wfMessage( 'donate_interface-donation-description' )->inLanguage( $init['language'] )->text(),
			'x_iduser' => 'nobody@example.org',
			'x_cpf' => '00003456789',
			'x_name' => 'Nome Apelido',
			'x_email' => 'nobody@example.org',
			'x_version' => '1.1',
			'x_address' => 'N0NE PROVIDED',
			// 'x_zip' => '01110-111',
			// 'x_city' => 'São Paulo',
			// 'x_state' => 'SP',
			'control' => 'D00BB4BF818EA9C3E944EF01FB470CBC34FE97E7F6346E6EE5A915EB957BB3FF',
			'type' => 'json',
		];
		$this->assertEquals( $expected, $actual, 'NewInvoice is not including the right parameters' );
	}

	/**
	 * Test the NewInvoice transaction is sending address and city correctly for India
	 */
	public function testNewInvoiceRequestAddressAndCity() {
		$init = $this->getDonorTestData( 'IN' );
		$session['Donor']['order_id'] = '123456789';
		$this->setUpRequest( $init, $session );
		$this->setLanguage( $init['language'] );
		$gateway = new TestingAstroPayAdapter();

		$gateway->do_transaction( 'NewInvoice' );
		parse_str( $gateway->curled[0], $actual );

		$expected = [
			'x_login' => 'createlogin',
			'x_trans_key' => 'createpass',
			'x_invoice' => '123456789',
			'x_amount' => '100.00',
			'x_currency' => 'INR',
			'x_bank' => 'TE',
			'x_country' => 'IN',
			'x_description' => wfMessage( 'donate_interface-donation-description' )->inLanguage( $init['language'] )->text(),
			'x_iduser' => 'testindia@test.com',
			'x_cpf' => '0000123456',
			'x_name' => 'Test India',
			'x_email' => 'testindia@test.com',
			'x_version' => '1.1',
			'x_address' => 'Test Street',
			// 'x_zip' => '01110-111',
			'x_city' => 'Chennai',
			// 'x_state' => 'SP',
			'control' => '1A3BA9E7AC831F3CC9A558D98BD5DF7C88A39B9FF245DA78974B273A5F659DCD',
			'type' => 'json',
		];
		$this->assertEquals( $expected, $actual, 'NewInvoice is not including the right parameters' );
	}

	/**
	 * When AstroPay sends back valid JSON with status "0", we should set txn
	 * status to true and errors should be empty.
	 */
	public function testStatusNoErrors() {
		$init = $this->getDonorTestData( 'BR' );
		$gateway = $this->getFreshGatewayObject( $init );

		$gateway->do_transaction( 'NewInvoice' );

		$this->assertEquals( true, $gateway->getTransactionStatus(),
			'Transaction status should be true for code "0"' );

		$this->assertFalse( $gateway->getErrorState()->hasErrors(),
			'Transaction errors should be empty for code "0"' );
	}

	/**
	 * If astropay sends back non-JSON, communication status should be false
	 */
	public function testGibberishResponse() {
		$init = $this->getDonorTestData( 'BR' );
		$this->setLanguage( $init['language'] );
		$gateway = $this->getFreshGatewayObject( $init );
		$gateway::setDummyGatewayResponseCode( 'notJson' );

		$gateway->do_transaction( 'NewInvoice' );

		$this->assertEquals( false, $gateway->getTransactionStatus(),
			'Transaction status should be false for bad format' );
	}

	/**
	 * When AstroPay sends back valid JSON with status "1", we should set
	 * error array to generic error and log a warning.
	 */
	public function testStatusErrors() {
		$init = $this->getDonorTestData( 'BR' );
		$this->setLanguage( $init['language'] );
		$gateway = $this->getFreshGatewayObject( $init );
		$gateway::setDummyGatewayResponseCode( '1' );

		$gateway->do_transaction( 'NewInvoice' );

		$errors = $gateway->getErrorState()->getErrors();

		$this->assertEquals(
			'internal-0000',
			$errors[0]->getErrorCode(),
			'Wrong error for code "1"'
		);
		$logged = self::getLogMatches( LogLevel::WARNING, '/This error message should appear in the log./' );
		$this->assertNotEmpty( $logged );
	}

	/**
	 * do_transaction should set redirect key when we get a valid response.
	 */
	public function testRedirectOnSuccess() {
		$init = $this->getDonorTestData( 'BR' );
		$gateway = $this->getFreshGatewayObject( $init );

		$gateway->do_transaction( 'NewInvoice' );

		// from the test response
		$expected = 'https://sandbox.dlocal.com/go_to_bank?id=A5jvKfK1iHIRUTPXXt8lDFGaRRLzPgBg';
		$response = $gateway->getTransactionResponse();
		$this->assertEquals( $expected, $response->getRedirect(),
			'do_transaction is not setting the right redirect' );
	}

	/**
	 * do_transaction should set redirect key when we get a valid response.
	 */
	public function testDoPaymentSuccess() {
		$init = $this->getDonorTestData( 'BR' );
		$init['payment_method'] = 'cc';
		$gateway = $this->getFreshGatewayObject( $init );

		$result = $gateway->doPayment();

		// from the test response
		$expected = 'https://sandbox.dlocal.com/go_to_bank?id=A5jvKfK1iHIRUTPXXt8lDFGaRRLzPgBg';
		$this->assertEquals( $expected, $result->getRedirect(),
			'doPayment is not setting the right redirect' );
	}

	/**
	 * When AstroPay sends back valid JSON with status "1", we should set
	 * error array to generic error and log a warning.
	 */
	public function testDoPaymentErrors() {
		$init = $this->getDonorTestData( 'BR' );
		$this->setLanguage( $init['language'] );
		$gateway = $this->getFreshGatewayObject( $init );
		$gateway::setDummyGatewayResponseCode( '1' );

		$result = $gateway->doPayment();

		$errors = $result->getErrors();
		$this->assertNotEmpty(
			$errors,
			'Should be an error in PaymentResult'
		);

		$logged = self::getLogMatches( LogLevel::WARNING, '/This error message should appear in the log./' );
		$this->assertNotEmpty( $logged );
		// TODO: Should this really be a refresh, or should we finalize to failed here?
		$this->assertTrue( $result->getRefresh(), 'PaymentResult should be a refresh' );
	}

	/**
	 * Should set a validation error on amount
	 */
	public function testDoPaymentLimitExceeded() {
		$init = $this->getDonorTestData( 'BR' );
		$this->setLanguage( $init['language'] );
		$init['payment_method'] = 'cc';
		$gateway = $this->getFreshGatewayObject( $init );
		$gateway::setDummyGatewayResponseCode( 'limit_exceeded' );

		$result = $gateway->doPayment();
		$this->assertTrue( $result->getRefresh(), 'PaymentResult should be a refresh' );

		$errors = $gateway->getTransactionResponse()->getErrors();
		$this->assertEquals( 'donate_interface-error-msg-limit', $errors[0]->getMessageKey() );
		$this->assertEquals( 'amount', $errors[0]->getField() );
	}

	/**
	 * Should set a validation error on fiscal_number
	 */
	public function testDoPaymentBadFiscalNumber() {
		$init = $this->getDonorTestData( 'BR' );
		$this->setLanguage( $init['language'] );
		$init['payment_method'] = 'cc';
		$gateway = $this->getFreshGatewayObject( $init );
		$gateway::setDummyGatewayResponseCode( 'fiscal_number' );

		$result = $gateway->doPayment();
		$this->assertTrue( $result->getRefresh(), 'PaymentResult should be a refresh' );

		$errors = $gateway->getTransactionResponse()->getErrors();
		$this->assertEquals( 'donate_interface-error-msg-fiscal_number', $errors[0]->getMessageKey() );
		$this->assertEquals( 'fiscal_number', $errors[0]->getField() );
	}

	/**
	 * Should finalize to failed
	 */
	public function testDoPaymentUserUnauthorized() {
		$init = $this->getDonorTestData( 'BR' );
		$this->setLanguage( $init['language'] );
		$init['payment_method'] = 'cc';
		$gateway = $this->getFreshGatewayObject( $init );
		$gateway::setDummyGatewayResponseCode( 'user_unauthorized' );

		$result = $gateway->doPayment();
		$this->assertTrue( $result->isFailed() );
	}

	/**
	 * Should tell the user to try again
	 */
	public function testDoPaymentCouldNotRegister() {
		$init = $this->getDonorTestData( 'BR' );
		$this->setLanguage( $init['language'] );
		$init['payment_method'] = 'cc';
		$gateway = $this->getFreshGatewayObject( $init );
		$gateway::setDummyGatewayResponseCode( 'could_not_register' );

		$result = $gateway->doPayment();
		$this->assertTrue( $result->getRefresh(), 'PaymentResult should be a refresh' );

		$errors = $gateway->getTransactionResponse()->getErrors();

		$this->assertEquals( 'internal-0001', $errors[0]->getErrorCode() );
	}

	/**
	 * Should tell the user to try again
	 */
	public function testDoPaymentCouldNotMakeDeposit() {
		$init = $this->getDonorTestData( 'BR' );
		$this->setLanguage( $init['language'] );
		$init['payment_method'] = 'cc';
		$gateway = $this->getFreshGatewayObject( $init );
		$gateway::setDummyGatewayResponseCode( 'could_not_make_deposit' );

		$result = $gateway->doPayment();
		$this->assertTrue( $result->getRefresh(), 'PaymentResult should be a refresh' );

		$errors = $gateway->getTransactionResponse()->getErrors();

		$this->assertEquals( 'internal-0001', $errors[0]->getErrorCode() );
	}

	/**
	 * PaymentStatus transaction should interpret the delimited response
	 */
	public function testPaymentStatus() {
		$init = $this->getDonorTestData( 'BR' );
		$session['Donor']['order_id'] = '123456789';
		$this->setUpRequest( $init, $session );
		$gateway = new TestingAstroPayAdapter();

		$gateway->do_transaction( 'PaymentStatus' );

		// from the test response
		$expected = [
			'result' => '9',
			'x_amount' => '100.00',
			'x_iduser' => '08feb2d12771bbcfeb86',
			'x_invoice' => '123456789',
			'PT' => '1',
			'x_control' => '0656B92DF44B814D48D84FED2F444CCA1E991A24A365FBEECCCA15B73CC08C2A',
			'x_document' => '987654321',
			'x_bank' => 'TE',
			'x_payment_type' => '03',
			'x_bank_name' => 'GNB',
			'x_currency' => 'BRL',
		];
		$results = $gateway->getTransactionData();
		$this->assertEquals( $expected, $results,
			'PaymentStatus response not interpreted correctly' );
		// Should not throw exception
		$accessible = TestingAccessWrapper::newFromObject( $gateway );
		$accessible->verifyStatusSignature( $results );
	}

	/**
	 * Invalid signature should be recognized as such.
	 */
	public function testInvalidSignature() {
		$init = $this->getDonorTestData( 'BR' );
		$session['Donor']['order_id'] = '123456789';
		$this->setUpRequest( $init, $session );
		$gateway = new TestingAstroPayAdapter();

		TestingAstroPayAdapter::setDummyGatewayResponseCode( 'badsig' );
		$gateway->do_transaction( 'PaymentStatus' );

		$results = $gateway->getTransactionData();
		$this->setExpectedException( 'ResponseProcessingException' );
		$accessible = TestingAccessWrapper::newFromObject( $gateway );
		$accessible->verifyStatusSignature( $results );
	}

	/**
	 * If status is paid and signature is correct, processDonorReturn should not
	 * throw exception and final status should be 'completed'
	 */
	public function testSuccessfulReturn() {
		$init = $this->getDonorTestData( 'BR' );
		$session['Donor']['order_id'] = '123456789';
		$this->setUpRequest( $init, $session );
		$gateway = new TestingAstroPayAdapter();

		$requestValues = [
			'result' => '9',
			'x_amount' => '100.00',
			'x_amount_usd' => '42.05',
			'x_control' => 'DDF89085AC70C0B0628150C51D64419D8592769F2439E3936570E26D24881730',
			'x_description' => 'Donation to the Wikimedia Foundation',
			'x_document' => '32869',
			'x_iduser' => '08feb2d12771bbcfeb86',
			'x_invoice' => '123456789',
		];

		$result = $gateway->processDonorReturn( $requestValues );
		$this->assertFalse( $result->isFailed() );
		$status = $gateway->getFinalStatus();
		$this->assertEquals( FinalStatus::COMPLETE, $status );
	}

	/**
	 * Make sure we record the actual amount charged, even if the donor has
	 * opened a new window and screwed up their session data.
	 */
	public function testReturnUpdatesAmount() {
		$init = $this->getDonorTestData( 'BR' );
		$init['amount'] = '22.55'; // junk session data from another banner click
		$session['Donor']['order_id'] = '123456789';
		$this->setUpRequest( $init, $session );
		$gateway = new TestingAstroPayAdapter();

		$amount = $gateway->getData_Unstaged_Escaped( 'amount' );
		$this->assertEquals( '22.55', $amount );

		$requestValues = [
			'result' => '9',
			'x_amount' => '100.00',
			'x_amount_usd' => '42.05',
			'x_control' => 'DDF89085AC70C0B0628150C51D64419D8592769F2439E3936570E26D24881730',
			'x_description' => 'Donation to the Wikimedia Foundation',
			'x_document' => '32869',
			'x_iduser' => '08feb2d12771bbcfeb86',
			'x_invoice' => '123456789',
		];

		$result = $gateway->processDonorReturn( $requestValues );
		$this->assertFalse( $result->isFailed() );
		$amount = $gateway->getData_Unstaged_Escaped( 'amount' );
		$this->assertEquals( '100.00', $amount, 'Not recording correct amount' );
	}

	/**
	 * If payment is rejected, final status should be 'failed'
	 */
	public function testRejectedReturn() {
		$init = $this->getDonorTestData( 'BR' );
		$session['Donor']['order_id'] = '123456789';
		$this->setUpRequest( $init, $session );
		$gateway = new TestingAstroPayAdapter();

		$requestValues = [
			'result' => '8', // rejected by bank
			'x_amount' => '100.00',
			'x_amount_usd' => '42.05',
			'x_control' => '706F57BC3E74906B14B1DEB946F027104513797CC62AC0F5107BC98F42D5DC95',
			'x_description' => 'Donation to the Wikimedia Foundation',
			'x_document' => '32869',
			'x_iduser' => '08feb2d12771bbcfeb86',
			'x_invoice' => '123456789',
		];

		$result = $gateway->processDonorReturn( $requestValues );
		$this->assertTrue( $result->isFailed() );
		$status = $gateway->getFinalStatus();
		$this->assertEquals( FinalStatus::FAILED, $status );
	}

	public function testStageBankCode() {
		$init = $this->getDonorTestData( 'BR' );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'elo';
		$gateway = $this->getFreshGatewayObject( $init );

		$gateway->doPayment();

		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$bank_code = $exposed->getData_Staged( 'bank_code' );
		$this->assertEquals( 'EL', $bank_code, 'Not setting bank_code in doPayment' );
	}

	/**
	 * Test that we run the AntiFraud filters before redirecting
	 */
	public function testAntiFraudFilters() {
		$init = $this->getDonorTestData( 'BR' );
		$init['payment_method'] = 'cc';
		$init['bank_code'] = 'VD';
		// following data should trip fraud alarms
		$init['utm_medium'] = 'somethingmedia';
		$init['utm_source'] = 'somethingmedia';
		$init['email'] = 'somebody@wikipedia.org';

		$gateway = $this->getFreshGatewayObject( $init );

		$result = $gateway->doPayment();

		$this->assertTrue( $result->isFailed(), 'Result should be failure if fraud filters say challenge' );
		$this->assertEquals( ValidationAction::CHALLENGE, $gateway->getValidationAction(), 'Validation action is not as expected' );
		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$this->assertEquals( 60, $exposed->risk_score, 'RiskScore is not as expected' );

		$initialMessage = QueueWrapper::getQueue( 'payments-antifraud' )->pop();
		$validateMessage = QueueWrapper::getQueue( 'payments-antifraud' )->pop();

		SourceFields::removeFromMessage( $initialMessage );
		SourceFields::removeFromMessage( $validateMessage );

		$expectedInitial = [
			'validation_action' => ValidationAction::PROCESS,
			'risk_score' => 0,
			'score_breakdown' => [
				'initial' => 0,
			],
			'user_ip' => '127.0.0.1',
			'gateway_txn_id' => false,
			'date' => $initialMessage['date'],
			'server' => gethostname(),
			'gateway' => 'astropay',
			'contribution_tracking_id' => $gateway->getData_Unstaged_Escaped( 'contribution_tracking_id' ),
			'order_id' => $gateway->getData_Unstaged_Escaped( 'order_id' ),
			'payment_method' => 'cc',
		];

		$expectedValidate = [
			'validation_action' => ValidationAction::CHALLENGE,
			'risk_score' => 60,
			'score_breakdown' => [
				'initial' => 0,
				'getScoreUtmCampaignMap' => 0,
				'getScoreCountryMap' => 0,
				'getScoreUtmSourceMap' => 10.5,
				'getScoreUtmMediumMap' => 12,
				'getScoreEmailDomainMap' => 37.5,
			],
			'user_ip' => '127.0.0.1',
			'gateway_txn_id' => false,
			'date' => $validateMessage['date'],
			'server' => gethostname(),
			'gateway' => 'astropay',
			'contribution_tracking_id' => $gateway->getData_Unstaged_Escaped( 'contribution_tracking_id' ),
			'order_id' => $gateway->getData_Unstaged_Escaped( 'order_id' ),
			'payment_method' => 'cc',
		];

		$this->assertEquals( $expectedInitial, $initialMessage );
		$this->assertEquals( $expectedValidate, $validateMessage );
	}

	public function testStageFiscalNumber() {
		$init = $this->getDonorTestData( 'BR' );
		$init['fiscal_number'] = '000.034.567-89';
		$gateway = $this->getFreshGatewayObject( $init );

		$gateway->doPayment();

		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$staged = $exposed->getData_Staged( 'fiscal_number' );
		$this->assertEquals( '00003456789', $staged, 'Not stripping fiscal_number punctuation in doPayment' );
	}

	/**
	 * We should increment the order ID with each NewInvoice call
	 */
	public function testNewInvoiceOrderId() {
		$init = $this->getDonorTestData( 'BR' );
		$firstRequest = $this->setUpRequest( $init );
		$firstAttempt = new TestingAstroPayAdapter();
		TestingAstroPayAdapter::setDummyGatewayResponseCode( '1' );

		$firstAttempt->doPayment();

		$this->setUpRequest( $init, $firstRequest->getSessionArray() );
		$secondAttempt = new TestingAstroPayAdapter();
		$secondAttempt->doPayment();

		parse_str( $firstAttempt->curled[0], $firstParams );
		parse_str( $secondAttempt->curled[0], $secondParams );

		$this->assertNotEquals( $firstParams['x_invoice'], $secondParams['x_invoice'],
			'Not generating new order id for NewInvoice call'
		);
	}

	/**
	 * We should increment the order ID with each NewInvoice call, even when
	 * retrying inside a single doPayment call
	 */
	public function testNewInvoiceOrderIdRetry() {
		$init = $this->getDonorTestData( 'BR' );
		$gateway = $this->getFreshGatewayObject( $init );
		$gateway::setDummyGatewayResponseCode( 'collision' );

		$gateway->doPayment();

		parse_str( $gateway->curled[0], $firstParams );
		parse_str( $gateway->curled[1], $secondParams );

		$this->assertNotEquals( $firstParams['x_invoice'], $secondParams['x_invoice'],
			'Not generating new order id for retried NewInvoice call'
		);
	}

	/**
	 * We should show an error for incompatible country / currency combinations
	 */
	public function testBadCurrencyForCountry() {
		$init = $this->getDonorTestData( 'BR' );
		$init['currency'] = 'CLP';
		$gateway = $this->getFreshGatewayObject( $init );

		$errorState = $gateway->getErrorState();

		$this->assertTrue(
			$errorState->hasValidationError( 'currency' ),
			'Should show a currency code error for trying to use CLP in BR'
		);
	}

	public function testDummyFiscalNumber() {
		$init = $this->getDonorTestData( 'MX' );
		$init['payment_submethod'] = 'visa';
		$gateway = $this->getFreshGatewayObject( $init );
		$gateway->doPayment();

		parse_str( $gateway->curled[0], $firstParams );
		$fiscalNumber = $firstParams['x_cpf'];
		$this->assertEquals(
			13, strlen( $fiscalNumber ),
			'Fake fiscal number should be 13 digits'
		);
	}
}
