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

/**
 *
 * @group Fundraising
 * @group DonationInterface
 * @group Astropay
 */
class DonationInterface_Adapter_Astropay_AstropayTest extends DonationInterfaceTestCase {

	/**
	 * @param $name string The name of the test case
	 * @param $data array Any parameters read from a dataProvider
	 * @param $dataName string|int The name or index of the data set
	 */
	function __construct( $name = null, array $data = array(), $dataName = '' ) {
		parent::__construct( $name, $data, $dataName );
		$this->testAdapterClass = 'TestingAstropayAdapter';
	}

	function tearDown() {
		TestingAstropayAdapter::clearGlobalsCache();
		parent::tearDown();
	}

	/**
	 * Ensure we're setting the right url for each transaction
	 * @covers AstropayAdapter::getCurlBaseOpts
	 */
	function testCurlUrl() {
		$init = $this->getDonorTestData( 'BR' );
		$gateway = $this->getFreshGatewayObject( $init );
		$gateway->setCurrentTransaction( 'NewInvoice' );

		$result = $gateway->getCurlBaseOpts();

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
	function testNewInvoiceRequest() {
		$init = $this->getDonorTestData( 'BR' );
		$this->setLanguage( $init['language'] );
		$_SESSION['Donor']['order_id'] = '123456789';
		$gateway = $this->getFreshGatewayObject( $init );

		$gateway->do_transaction( 'NewInvoice' );
		parse_str( $gateway->curled[0], $actual );

		$expected = array(
			'x_login' => 'createlogin',
			'x_trans_key' => 'createpass',
			'x_invoice' => '123456789',
			'x_amount' => '100.00',
			'x_currency' => 'BRL',
			'x_bank' => 'TE',
			'x_country' => 'BR',
			'x_description' => wfMessage( 'donate_interface-donation-description' )->inLanguage( $init['language'] )->text(),
			'x_iduser' => '08feb2d12771bbcfeb86',
			'x_cpf' => '00003456789',
			'x_name' => 'Nome Apelido',
			'x_email' => 'nobody@wikimedia.org',
			'x_address' => 'Rua Falso 123',
			'x_zip' => '01110-111',
			'x_city' => 'São Paulo',
			'x_state' => 'SP',
			'control' => '5853FD808AA10839CB268ED2D1D6D4E8D8FECA88E4A8D66477369C0CA8AA4B42',
			'type' => 'json',
		);
		$this->assertEquals( $expected, $actual, 'NewInvoice is not including the right parameters' );
	}

	/**
	 * When Astropay sends back valid JSON with status "0", we should set txn
	 * status to true and errors should be empty.
	 */
	function testStatusNoErrors() {
		$init = $this->getDonorTestData( 'BR' );
		$gateway = $this->getFreshGatewayObject( $init );

		$gateway->do_transaction( 'NewInvoice' );

		$this->assertEquals( true, $gateway->getTransactionStatus(),
			'Transaction status should be true for code "0"' );

		$this->assertEmpty( $gateway->getTransactionErrors(),
			'Transaction errors should be empty for code "0"' );
	}

	/**
	 * When Astropay sends back valid JSON with status "1", we should set txn
	 * status to false and error array to generic error and log a warning.
	 */
	function testStatusErrors() {
		$init = $this->getDonorTestData( 'BR' );
		$this->setLanguage( $init['language'] );
		$gateway = $this->getFreshGatewayObject( $init );
		$gateway->setDummyGatewayResponseCode( '1' );

		$gateway->do_transaction( 'NewInvoice' );

		$this->assertEquals( false, $gateway->getTransactionStatus(),
			'Transaction status should be false for code "1"' );

		$expected = array(
			wfMessage( 'donate_interface-processing-error')->inLanguage( $init['language'] )->text()
		);
		$this->assertEquals( $expected, $gateway->getTransactionErrors(),
			'Wrong error for code "1"' );
		$logged = $this->getLogMatches( LogLevel::WARNING, '/This error message should appear in the log.$/' );
		$this->assertNotEmpty( $logged );
	}

	/**
	 * do_transaction should set redirect key when we get a valid response.
	 */
	function testRedirectOnSuccess() {
		$init = $this->getDonorTestData( 'BR' );
		$gateway = $this->getFreshGatewayObject( $init );

		$gateway->do_transaction( 'NewInvoice' );

		// from the test response
		$expected = 'https://sandbox.astropaycard.com/go_to_bank?id=A5jvKfK1iHIRUTPXXt8lDFGaRRLzPgBg';
		$results = $gateway->getTransactionAllResults();
		$this->assertEquals( $expected, $results['redirect'],
			'do_transaction is not setting the right redirect' );
	}

	/**
	 * PaymentStatus transaction should interpret the delimited response
	 */
	function testPaymentStatus() {
		$init = $this->getDonorTestData( 'BR' );
		$_SESSION['Donor']['order_id'] = '123456789';
		$gateway = $this->getFreshGatewayObject( $init );

		$gateway->do_transaction( 'PaymentStatus' );

		// from the test response
		$expected = array(
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
		);
		$results = $gateway->getTransactionData();
		$this->assertEquals( $expected, $results,
			'PaymentStatus response not interpreted correctly' );
		$valid = $gateway->verifyStatusSignature( $results );
		$this->assertTrue( $valid, 'Signature should be interpreted as valid' );
	}

	/**
	 * Invalid signature should be recognized as such.
	 */
	function testInvalidSignature() {
		$init = $this->getDonorTestData( 'BR' );
		$_SESSION['Donor']['order_id'] = '123456789';
		$gateway = $this->getFreshGatewayObject( $init );

		$gateway->setDummyGatewayResponseCode( 'badsig' );
		$gateway->do_transaction( 'PaymentStatus' );

		$results = $gateway->getTransactionData();
		$valid = $gateway->verifyStatusSignature( $results );
		$this->assertFalse( $valid, 'Signature should not be interpreted as valid' );
	}
}
