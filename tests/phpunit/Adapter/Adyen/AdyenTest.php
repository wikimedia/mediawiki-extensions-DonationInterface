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
 */

use SmashPig\PaymentProviders\Adyen\Tests\AdyenTestConfiguration;
use SmashPig\Tests\TestingContext;
use Wikimedia\TestingAccessWrapper;

/**
 *
 * @group Fundraising
 * @group DonationInterface
 * @group Adyen
 */
class DonationInterface_Adapter_Adyen_Test extends DonationInterfaceTestCase {

	/**
	 * @param string$name The name of the test case
	 * @param array $data Any parameters read from a dataProvider
	 * @param string|int $dataName The name or index of the data set
	 */
	public function __construct( $name = null, array $data = [], $dataName = '' ) {
		parent::__construct( $name, $data, $dataName );
		$this->testAdapterClass = TestingAdyenAdapter::class;
	}

	public function setUp() {
		parent::setUp();
		TestingContext::get()->providerConfigurationOverride =
			AdyenTestConfiguration::createWithSuccessfulApi(
				$this->smashPigGlobalConfig
			);

		$this->setMwGlobals( [
			'wgAdyenGatewayEnabled' => true,
		] );
	}

	/**
	 * Integration test to verify that the donate transaction works as expected when all necessary data is present.
	 */
	function testDoTransactionDonate() {
		$init = $this->getDonorTestData();
		$init['payment_submethod'] = 'visa';
		$gateway = $this->getFreshGatewayObject( $init );

		$gateway->do_transaction( 'donate' );
		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$ret = $exposed->buildRequestParams();

		$expected = [
			'allowedMethods' => 'card',
			'billingAddress.street' => $init['street_address'],
			'billingAddress.city' => $init['city'],
			'billingAddress.postalCode' => $init['postal_code'],
			'billingAddress.stateOrProvince' => $init['state_province'],
			'billingAddress.country' => $init['country'],
			'billingAddress.houseNumberOrName' => 'NA',
			'billingAddressType' => 2,
			'brandCode' => 'visa',
			'card.cardHolderName' => $init['first_name'] . ' ' . $init['last_name'],
			'currencyCode' => $init['currency'],
			'merchantAccount' => 'wikitest',
			'merchantReference' => $exposed->getData_Staged( 'order_id' ),
			'merchantSig' => $exposed->getData_Staged( 'hpp_signature' ),
			'paymentAmount' => ( $init['amount'] ) * 100,
// 'sessionValidity' => '2014-03-09T19:41:50+00:00',	//commenting out, because this is a problem.
// 'shipBeforeDate' => $exposed->getData_Staged( 'expiration' ),	//this too.
			'skinCode' => 'testskin',
			'shopperLocale' => 'en_US',
			'shopperEmail' => 'nobody@wikimedia.org',
			'offset' => '52', // once we construct the FraudFiltersTestCase, it should land here.
		];

		// deal with problem keys.
		// @TODO: Refactor gateway so these are more testable
		$problems = [
			'sessionValidity',
			'shipBeforeDate',
		];

		foreach ( $problems as $oneproblem ) {
			if ( isset( $ret[$oneproblem] ) ) {
				unset( $ret[$oneproblem] );
			}
		}

		$this->assertEquals( $expected, $ret, 'Adyen "donate" transaction not constructing the expected redirect URL' );
		$this->assertNotNull( $gateway->getData_Unstaged_Escaped( 'order_id' ), "Adyen order_id is null, and we need one for 'merchantReference'" );
	}

	/**
	 * Integration test to verify that the donate transaction works as expected when all necessary data is present.
	 */
	function testDoPayment() {
		$init = $this->getDonorTestData();
		$init['payment_submethod'] = 'visa';
		$gateway = $this->getFreshGatewayObject( $init );

		$result = $gateway->doPayment();
		$actualUrl = $result->getIframe();
		$actualData = $result->getFormData();
		$this->assertEquals( 'https://test.adyen.com/hpp/pay.shtml', $actualUrl );
		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$orderId = $exposed->getData_Staged( 'order_id' );
		$this->assertNotNull( $orderId, 'No order ID generated' );
		$expected = [
			'allowedMethods' => 'card',
			'billingAddress.street' => $init['street_address'],
			'billingAddress.city' => $init['city'],
			'billingAddress.postalCode' => $init['postal_code'],
			'billingAddress.stateOrProvince' => $init['state_province'],
			'billingAddress.country' => $init['country'],
			'billingAddress.houseNumberOrName' => 'NA',
			'billingAddressType' => 2,
			'brandCode' => 'visa',
			'card.cardHolderName' => $init['first_name'] . ' ' . $init['last_name'],
			'currencyCode' => $init['currency'],
			'merchantAccount' => 'wikitest',
			'merchantReference' => $orderId,
			'merchantSig' => $exposed->getData_Staged( 'hpp_signature' ),
			'paymentAmount' => ( $init['amount'] ) * 100,
			// 'sessionValidity' => '2014-03-09T19:41:50+00:00',	//commenting out, because this is a problem.
			// 'shipBeforeDate' => $exposed->getData_Staged( 'expiration' ),	//this too.
			'skinCode' => 'testskin',
			'shopperLocale' => 'en_US',
			'shopperEmail' => 'nobody@wikimedia.org',
			'offset' => '52', // once we construct the FraudFiltersTestCase, it should land here.
		];
		$this->assertArraySubset( $expected, $actualData );
	}

	public function testdoPaymentError() {
		$init = $this->getDonorTestData();
		unset( $init['postal_code'] );

		$gateway = $this->getFreshGatewayObject( $init );
		$result = $gateway->doPayment();
		$errors = $result->getErrors();
		$this->assertNotEmpty( $errors, 'Should have returned an error' );
		$foundPostalCodeError = false;
		foreach ( $errors as $error ) {
			if ( $error->getField() === 'postal_code' ) {
				$foundPostalCodeError = true;
			}
		}
		$this->assertTrue( $foundPostalCodeError, 'postal_code should be in error' );
	}

	function testRiskScoreAddedToQueueMessage() {
		$init = $this->getDonorTestData();
		$init['payment_submethod'] = 'visa';
		$gateway = $this->getFreshGatewayObject( $init );

		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$exposed->risk_score = 57;
		$message = $exposed->getQueueDonationMessage();
		$this->assertEquals( 57, $message['risk_score'], 'Risk score was not correctly added to queue message.' );
	}

	/**
	 * Make sure language is staged correctly when qs param is uppercase
	 */
	function testLanguageCaseSensitivity() {
		$init = $this->getDonorTestData();
		$init['payment_submethod'] = 'visa';
		$init['language'] = 'FR';
		$gateway = $this->getFreshGatewayObject( $init );

		$gateway->do_transaction( 'donate' );
		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$ret = $exposed->buildRequestParams();

		$expected = [
			'allowedMethods' => 'card',
			'billingAddress.street' => $init['street_address'],
			'billingAddress.city' => $init['city'],
			'billingAddress.postalCode' => $init['postal_code'],
			'billingAddress.stateOrProvince' => $init['state_province'],
			'billingAddress.country' => $init['country'],
			'billingAddress.houseNumberOrName' => 'NA',
			'billingAddressType' => 2,
			'brandCode' => 'visa',
			'card.cardHolderName' => $init['first_name'] . ' ' . $init['last_name'],
			'currencyCode' => $init['currency'],
			'merchantAccount' => 'wikitest',
			'merchantReference' => $exposed->getData_Staged( 'order_id' ),
			'merchantSig' => $exposed->getData_Staged( 'hpp_signature' ),
			'paymentAmount' => ( $init['amount'] ) * 100,
			'skinCode' => 'testskin',
			'shopperLocale' => 'fr_US',
			'shopperEmail' => 'nobody@wikimedia.org',
			'offset' => '52',
		];

		// deal with problem keys.
		// @TODO: Refactor gateway so these are more testable
		$problems = [
			'sessionValidity',
			'shipBeforeDate',
		];

		foreach ( $problems as $oneproblem ) {
			if ( isset( $ret[$oneproblem] ) ) {
				unset( $ret[$oneproblem] );
			}
		}

		$this->assertEquals( $expected, $ret, 'Adyen "donate" transaction not constructing the expected redirect URL' );
		$this->assertNotNull( $gateway->getData_Unstaged_Escaped( 'order_id' ), "Adyen order_id is null, and we need one for 'merchantReference'" );
	}

	public function testDonorReturnSuccess() {
		$init = $this->getDonorTestData();
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['language'] = 'FR';
		$init['order_id'] = '55555';
		$session['Donor'] = $init;
		$this->setUpRequest( $init, $session );
		$gateway = $this->getFreshGatewayObject( [] );
		$result = $gateway->processDonorReturn( [
			'authResult' => 'AUTHORISED',
			'merchantReference' => '55555.1',
			'merchantSig' => 'NPG6j/g5LVORxSXb8WLegoG6e2Fd7D4986p736yozbI=',
			'paymentMethod' => 'visa',
			'pspReference' => '123987612346789',
			'shopperLocale' => 'fr_FR',
			'skinCode' => 'testskin',
			'title' => 'Special:AdyenGatewayResult'
		] );
		$this->assertFalse( $result->isFailed() );
		$this->assertEmpty( $result->getErrors() );
		// TODO inspect the queue message
	}

	/**
	 * Test that we verify the signature with the alternate skin code's HMAC
	 */
	public function testDonorReturnSuccessAltSkin() {
		$init = $this->getDonorTestData();
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['language'] = 'FR';
		$init['order_id'] = '55555';
		$session['Donor'] = $init;
		$this->setUpRequest( $init, $session );
		$gateway = $this->getFreshGatewayObject( [] );
		$result = $gateway->processDonorReturn( [
			'authResult' => 'AUTHORISED',
			'merchantReference' => '55555.1',
			'merchantSig' => '/uzhDRZ3zSzFNLgBj4tI6pHYDynVQAqCeKcJWsXeWo0=',
			'paymentMethod' => 'visa',
			'pspReference' => '123987612346789',
			'shopperLocale' => 'fr_FR',
			'skinCode' => 'altskin',
			'title' => 'Special:AdyenGatewayResult'
		] );
		$this->assertFalse( $result->isFailed() );
		$this->assertEmpty( $result->getErrors() );
	}

	public function testDonorReturnFailure() {
		$init = $this->getDonorTestData();
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['language'] = 'FR';
		$init['order_id'] = '55555';
		$session['Donor'] = $init;
		$this->setUpRequest( $init, $session );
		$gateway = $this->getFreshGatewayObject( [] );
		$result = $gateway->processDonorReturn( [
			'authResult' => 'REFUSED',
			'merchantReference' => '55555.1',
			'merchantSig' => 's8t3037BPcWl8niWHsrnwOXh+EqdPHmKyaLHYLf1tz4=',
			'paymentMethod' => 'visa',
			'pspReference' => '123987612346789',
			'shopperLocale' => 'fr_FR',
			'skinCode' => 'testskin',
			'title' => 'Special:AdyenGatewayResult'
		] );
		$this->assertTrue( $result->isFailed() );
	}

	/**
	 * Test that we choose the correct HMAC based on skinCode
	 */
	public function testSignatureAltSkin() {
		$init = $this->getDonorTestData();
		$gateway = $this->getFreshGatewayObject( $init );
		$toSign = [
			'allowedMethods' => 'card',
			'billingAddress.street' => $init['street_address'],
			'billingAddress.city' => $init['city'],
			'billingAddress.postalCode' => $init['postal_code'],
			'billingAddress.stateOrProvince' => $init['state_province'],
			'billingAddress.country' => $init['country'],
			'billingAddress.houseNumberOrName' => 'NA',
			'billingAddressType' => 2,
			'card.cardHolderName' => $init['first_name'] . ' ' . $init['last_name'],
			'currencyCode' => $init['currency'],
			'merchantAccount' => 'wikitest',
			'merchantReference' => 123456,
			'paymentAmount' => ( $init['amount'] ) * 100,
			'skinCode' => 'testskin',
			'shopperLocale' => 'fr_US',
			'shopperEmail' => 'nobody@wikimedia.org',
			'offset' => '52',
		];
		$defaultSig = AdyenHostedSignature::calculateSignature( $gateway, $toSign );
		$toSign['skinCode'] = 'altskin';
		$altSig = AdyenHostedSignature::calculateSignature( $gateway, $toSign );
		$this->assertEquals( 'xoI76zyUFjjBzubzSPEopAgoA9Bt7PjwQAi5QHk/GKo=', $defaultSig );
		$this->assertEquals( 'UKMVUkWR5GqsgfUEtqZalzh+kTa7kXyrDw9nbj4D/0Q=', $altSig );
	}

	public function testGetSkinCodes() {
		$init = $this->getDonorTestData();
		unset( $init['processor_form'] );
		$gateway = $this->getFreshGatewayObject( $init );
		$skinCodes = $gateway->getSkinCodes();
		$this->assertEquals( $skinCodes['base'], 'testskin' );
	}
}
