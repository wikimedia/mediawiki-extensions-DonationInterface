<?php

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\RecurringModel;
use SmashPig\PaymentProviders\Adyen\CardPaymentProvider;
use SmashPig\PaymentProviders\Responses\ApprovePaymentResponse;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;
use SmashPig\PaymentProviders\Responses\PaymentDetailResponse;

/**
 * @group Fundraising
 * @group DonationInterface
 * @group Adyen
 * @group AdyenCheckout
 */
class CheckoutCardTest extends BaseAdyenCheckoutTestCase {

	/**
	 * @var \PHPUnit\Framework\MockObject\MockObject|CardPaymentProvider
	 */
	protected $cardPaymentProvider;

	protected $encryptedCardData = [
		// phpcs:disable Generic.Files.LineLength
		'encrypted_card_number' => 'adyenjs_0_1_25$pls1kpIjU/Kvojg2qpY4n/dGnJh+6aeSP6I3jTjgowkF7oPWm91o6nbb1DhQ1lsBWiHSL+WoLq28tI+I/yS1+pv/7U6trOQk67XFNkVuDAOIH2uImKDDLtVyiTnMGccirWEvpVRv5a7XKDm4MD2/GMPnoQ2De/AatSeVE/lwwBnl05r5D3YFtrYOoBobKueXILXrGvgAHm0otvmT2BqmNhdwAJy1t/gjHKS+8ozVYKcvf277gitGYi8fjFaJXfvmAmObX3B837vChw28E2M/2bSI5dXneIapgMg9Ra5jII/iHuhyUDCoyNt8d6/+yRAJuB7MoOdY78HNBwDIxlSXiw==$R6dgq5eQoQ2EBnUL4FR9FuIIzESqt/l9D2m/ZzsLYkCntqSxOqLNaF/Dt6omYfoevzKO8yhkTWQSRBL+df1QQ+CUFAsnNLqPmVvO+oxDrk7pMzECvyZPE8mM+2DNZCdngLugFDchPWKG9F2/d0eYcS0…93uxUvyIyP7VyDP/8o8Fi+CBSNb7neuH7lyTG12Hx3xVfB58eu4JsNO9a9rmLPzKzQj9JAYN2Kact04M3ikfHrOXMCpFL1fXHCD/FPbOoGaIotJWwYXfStNd76skEEMQn6kHBu6agsBfNdVxkV9f93l5Lj+qY8y8jb0LX+4Lqf3XJrzxKcmAB5PYzCqWMPr2HBZ9bucEq232OQn4PWnPf9SIOj59wPFVnOKaOPY6NHagqCPJqD/DPiB51U7y+fOjPsnXisjejMjj8BypFEiE7qgQsoAHREi3fcs/VnBAhz8q/k3iZDTiNC9NnjJpI4rS0gIqaHAFy2aIkhjyN9eSQz5vKggzR9UxtoPoBLKY5UzBIneLeiM08ajaOkOlzIF+hi9+5X3Rqq3wae9HuXRsXBhI0Fs4vSe3zr5nXy8L8iy8PZcanCWCKdLF6i65q3iL4UdfKx4FiWU5XzQNlQVl6NeCZdYzsyZlm5eGM5nJMD3Du3UtUDsrN7Rf68lDjU',
		'encrypted_expiry_month' => 'adyenjs_0_1_25$K+ld2ZyfK1OAJaVeuYI/GIcDzB7cya6jIdfUg7l1gVGgumtjNM3F6rlZp4YQe1B3NXJYePF59uQkS1H23iQAwQ2plFyQ/fKoF0dOcb0STdejaMFj4Ayc0NMZ5BtS3aZJV7sXnj+F6M9Ywae9igqFyrKn+YsNXshs4/Q5gNvL6xJ/QqcccJYk3q2krhFqVM/6II1z+E9r1BTD0SNUJzA33yxDN7S7BiUw1gdAZrC87d5u1o+AaKhz6duc8oZ2gttkwiyBLdgcF9omd/GHmFoyHPvppYBqjnQVPNHq06nggzNcRjJV/I0nZQ9DkCUmCCyaUz/4BsKekxQ7Cfqs4FMlYA==$hVk9SA5jaMdPjX4R1FJUVAA9R/IJD5yIaWvo/1/CNYD+pbWvir6UyDNJNWstTVSpRGet1AvkmQcZNebq+nx3s2x9tWF6QVDwnM1K9ma4sME5nHjxKwTH4sgzxzt7d33QKSlRl1ydjNYoQMmueko01P0ARsholLYUKcUTyFEhv0Phx4PAAAHxADPNnsRJFbMztY0u5hsmRMpHv1ssL99EqfIt6hNw1VtESori7Nm0+aaS/kxP0EI/5gep0MVpw8+1/CdCuowmjr5AaY1vvP2iQvMOLk5e6724kwcHqbXjCb2MfRds8UJK/a1ZBDRy1qyVkAR7ITJHE9kcR0UDx9EwvCZqiS0/+Gz5dwC/eluyMsrxoe+7lTHxMCZ5Oh2ISAMbhmce2kHklCL62qInIvrP/T1nZIAwFDnv0Bo2dQG916NHiB+i1yBFlPAIEccwRDor6bXeRA==',
		'encrypted_expiry_year' => 'adyenjs_0_1_25$LGdROHW/sANSCwNX8eOfkN3hebQ+iJ/XYXWwgoXA1T9xquJbe00HVyMI3IFNsTUWRSigH45DtwpwN8WYkbLXp4s3dn5I0bw5byHXBZRCzz2QtMZO8urIM0NvbVVyUbYMJSCMVcy2Q/dx9ELi+2gkVADhc2DTDBUX9O1FAvsQc56t0CnfvqhQYa+xs4JU5Qo1eWRxLi9R3G8oXaxB18c3nwKFvBo7tuGDz1WQsDLFswhMPtyWr8+qSZqR9KBqrCqtUAosJ8FvZKYC+jppy5YR/AMEXa3XC4rYEKOdoAyUm9uxx46BgWjCGIRs+97lUYS0owzpsfVTwMa4kHTBqCgkTA==$cj9Q+ENsL8X9zCXS5K9b2s1ue+2Z0X7AVtL/wIw6qnTmpxF5yiqIcKMx2YlpKBCnICsykXlBHA5exxRuckHZdwD6wAuqb1KBCQZUQMl63VPWDXlw7fEmJ86+iROGkKmATEi0d3QjEWxlXoBPsn5/+8e+EHcJYt9BtLP9XcxOcnuFxuA7X/6QMe5g8P4Siln74uDeTbqI16XwZTskTPPXucMnnd8zl3Z4eYS6ZSkFuu+O3rBUkmEGkwsiZ8nqQpajZQ0eJbfvSLBk0aUsrpaF7NhcjbKcUcqkhmYw0WDS8FlsOGweapIijmAxUiki+CaipzHP18m+lfjTSXBjb66zsJSeveYrDDbU5CPlDQyHZQ9Oboq8+pThXMN2ntKgvsuZnKLRKefM74zAndVy7K2JISMNmetojuEUFC+zJ+1Ofqp3HnqgXorX/UeRyhvgNsKm3WxvwUg=',
		'encrypted_security_code' => 'adyenjs_0_1_25$m5hhc+DT88QpaoLqG+HvIsmm0G0BPVDldkAfIHsWU/sRwIKJc6uATR8lyayej3eGIoUgw9O+GTiP7T/3cKb4gclIqvZHl0g+XUOcaneNlnS7M6Ja+59e+X4t3vVFiL9Wyxa1PNAkP4nFOFJfRVkwyaoNcZB9R1NKpxUyGBtiG5UYYkupXaLaruImr4nUjYATX2egkEnKUihcZDMjuiZVwOoEQcq/nxF7kBFog+4eti6vqWM5c/8+iDX/ivOrFZHnq24eVIhM4n9I3CkA8wP0Vvnp5d7RYqbJN+IdKNHIRdw5iJSfsUsdaua2ZgF1X7Y2xcAIVksCotQaegwACejelPg==$UfQCerKptxI5QSx9',
		// phpcs:enable
	];

	protected function setUp(): void {
		parent::setUp();

		$this->cardPaymentProvider = $this->createMock( CardPaymentProvider::class );

		$this->providerConfig->overrideObjectInstance(
			'payment-provider/cc',
			$this->cardPaymentProvider
		);
	}

	/**
	 * Integration test to verify that the authorize and capture transactions
	 * send the expected parameters to the SmashPig library objects and that
	 * they return the expected result when the API calls are successful.
	 */
	public function testDoPaymentCard() {
		$init = $this->getTestDonorCardData();
		$init += $this->encryptedCardData;
		$init['amount'] = '1.55';
		$gateway = $this->getFreshGatewayObject( $init );
		$expectedEncryptedParams = [
			'encryptedCardNumber' => $this->encryptedCardData['encrypted_card_number'],
			'encryptedExpiryMonth' => $this->encryptedCardData['encrypted_expiry_month'],
			'encryptedExpiryYear' => $this->encryptedCardData['encrypted_expiry_year'],
			'encryptedSecurityCode' => $this->encryptedCardData['encrypted_security_code'],
		];
		$pspReferenceAuth = 'ASD' . mt_rand( 100000, 1000000 );
		$pspReferenceCapture = 'BLA' . mt_rand( 100000000, 1000000000 );
		$expectedMerchantRef = $init['contribution_tracking_id'] . '.1';
		$expectedReturnUrl = Title::newFromText(
			'Special:AdyenCheckoutGatewayResult'
		)->getFullURL( [
			'order_id' => $expectedMerchantRef,
			'wmf_token' => $gateway->token_getSaltedSessionToken(),
		] );

		$this->cardPaymentProvider->expects( $this->once() )
			->method( 'createPayment' )
			->with( [
				'amount' => '1.55',
				'city' => 'NA',
				'country' => 'US',
				'currency' => 'USD',
				'description' => 'Wikimedia 877 600 9454',
				'email' => 'nobody@wikimedia.org',
				'first_name' => 'Firstname',
				'encrypted_payment_data' => $expectedEncryptedParams,
				'last_name' => 'Surname',
				'order_id' => $expectedMerchantRef,
				'postal_code' => '94105',
				'return_url' => $expectedReturnUrl,
				'state_province' => 'NA',
				'street_address' => '123 Fake Street',
				'user_ip' => '127.0.0.1'
			] )
			->willReturn(
				( new CreatePaymentResponse() )
					->setRawStatus( 'Authorized' )
					->setStatus( FinalStatus::PENDING_POKE )
					->setSuccessful( true )
					->setRiskScores( [ 'avs' => 10, 'cvv' => 20 ] )
					->setGatewayTxnId( $pspReferenceAuth )
			);
		$this->cardPaymentProvider->expects( $this->once() )
			->method( 'approvePayment' )
			->with( [
				'amount' => $init[ 'amount' ],
				'currency' => $init[ 'currency' ],
				'gateway_txn_id' => $pspReferenceAuth
			] )
			->willReturn(
				( new ApprovePaymentResponse() )
					->setRawStatus( '[capture-received]' )
					->setStatus( FinalStatus::COMPLETE )
					->setSuccessful( true )
					->setGatewayTxnId( $pspReferenceCapture )
			);

		$result = $gateway->doPayment();

		$this->assertFalse( $result->isFailed() );
		$this->assertSame( [], $result->getErrors() );

		$messages = self::getAllQueueMessages();
		$this->assertCount( 1, $messages['donations'] );
		$expectedQueueMessage = [
			'contribution_tracking_id' => $init['contribution_tracking_id'],
			'country' => 'US',
			'currency' => 'USD',
			'email' => 'nobody@wikimedia.org',
			'first_name' => 'Firstname',
			'gateway' => 'adyen',
			'gateway_txn_id' => $pspReferenceAuth,
			'gross' => '1.55',
			'language' => 'en',
			'last_name' => 'Surname',
			'order_id' => $expectedMerchantRef,
			'payment_method' => 'cc',
			'payment_submethod' => 'visa',
			'postal_code' => '94105',
			'user_ip' => '127.0.0.1'
		];
		$this->assertArraySubmapSame(
			$expectedQueueMessage,
			$messages['donations'][0]
		);
		// No pending message when we immediately capture the donation
		$this->assertCount( 0, $messages['pending'] );
		$this->assertCount( 2, $messages['payments-antifraud'] );
		$expectedAntifraudInitial = [
			'validation_action' => 'process',
			'risk_score' => 0,
			'score_breakdown' => [ 'initial' => 0 ],
			'user_ip' => '127.0.0.1',
			'gateway' => 'adyen',
			'contribution_tracking_id' => $init['contribution_tracking_id'],
			'order_id' => $expectedMerchantRef,
			'payment_method' => 'cc',
		];
		$this->assertArraySubmapSame(
			$expectedAntifraudInitial,
			$messages['payments-antifraud'][0]
		);
		$expectedAntiFraudProcess = [
				'gateway_txn_id' => $pspReferenceAuth,
				'risk_score' => 7,
				'score_breakdown' => [
					'getAVSResult' => 5,
					'getCVVResult' => 2,
					'initial' => 0
				]
			] + $expectedAntifraudInitial;
		$this->assertArraySubmapSame(
			$expectedAntiFraudProcess,
			$messages['payments-antifraud'][1]
		);
		$this->assertCount( 1, $messages['payments-init'] );
		$this->assertArraySubmapSame(
			[
				'validation_action' => 'process',
				'payments_final_status' => 'complete',
				'payment_submethod' => 'visa',
				'country' => 'US',
				'amount' => '1.55',
				'currency' => 'USD',
				'gateway' => 'adyen',
				'gateway_txn_id' => $pspReferenceAuth,
				'contribution_tracking_id' => $init['contribution_tracking_id'],
				'order_id' => $expectedMerchantRef,
				'payment_method' => 'cc',
			],
			$messages['payments-init'][0]
		);
	}

	/**
	 * Integration test to verify that the authorize and capture transactions
	 * send the expected parameters to the SmashPig library objects and that
	 * they return the expected result when the API calls are successful.
	 */
	public function testDoPaymentCardMonthlyConvert() {
		$init = $this->getTestDonorCardData();
		$this->setMwGlobals( [
			'wgDonationInterfaceMonthlyConvertCountries' => [ 'US' ]
		] );
		$init += $this->encryptedCardData;
		$gateway = $this->getFreshGatewayObject( $init );
		$expectedEncryptedParams = [
			'encryptedCardNumber' => $this->encryptedCardData['encrypted_card_number'],
			'encryptedExpiryMonth' => $this->encryptedCardData['encrypted_expiry_month'],
			'encryptedExpiryYear' => $this->encryptedCardData['encrypted_expiry_year'],
			'encryptedSecurityCode' => $this->encryptedCardData['encrypted_security_code'],
		];
		$pspReferenceAuth = 'ASD' . mt_rand( 100000, 1000000 );
		$pspReferenceCapture = 'BLA' . mt_rand( 100000000, 1000000000 );
		$pspToken = 'FOO' . mt_rand( 100000000, 1000000000 );
		$expectedMerchantRef = $init['contribution_tracking_id'] . '.1';
		$expectedReturnUrl = Title::newFromText(
			'Special:AdyenCheckoutGatewayResult'
		)->getFullURL( [
			'order_id' => $expectedMerchantRef,
			'wmf_token' => $gateway->token_getSaltedSessionToken(),
		] );

		$this->cardPaymentProvider->expects( $this->once() )
			->method( 'createPayment' )
			->with( [
				'amount' => '4.55',
				'city' => 'NA',
				'country' => 'US',
				'currency' => 'USD',
				'description' => 'Wikimedia 877 600 9454',
				'email' => 'nobody@wikimedia.org',
				'first_name' => 'Firstname',
				'encrypted_payment_data' => $expectedEncryptedParams,
				'last_name' => 'Surname',
				'order_id' => $expectedMerchantRef,
				'postal_code' => '94105',
				'recurring' => 1,
				'recurring_model' => RecurringModel::CARD_ON_FILE,
				'return_url' => $expectedReturnUrl,
				'state_province' => 'NA',
				'street_address' => '123 Fake Street',
				'user_ip' => '127.0.0.1'
			] )
			->willReturn(
				( new CreatePaymentResponse() )
					->setRawStatus( 'Authorized' )
					->setStatus( FinalStatus::PENDING_POKE )
					->setSuccessful( true )
					->setRiskScores( [ 'avs' => 10, 'cvv' => 20 ] )
					->setGatewayTxnId( $pspReferenceAuth )
					->setRecurringPaymentToken( $pspToken )
			);
		$this->cardPaymentProvider->expects( $this->once() )
			->method( 'approvePayment' )
			->with( [
				'amount' => $init['amount'],
				'currency' => $init['currency'],
				'gateway_txn_id' => $pspReferenceAuth
			] )
			->willReturn(
				( new ApprovePaymentResponse() )
					->setRawStatus( '[capture-received]' )
					->setStatus( FinalStatus::COMPLETE )
					->setSuccessful( true )
					->setGatewayTxnId( $pspReferenceCapture )
			);

		$result = $gateway->doPayment();

		$this->assertFalse( $result->isFailed() );
		$this->assertSame( [], $result->getErrors() );
		// There should be a token in the stored data
		$actual = $gateway->getData_Unstaged_Escaped( 'recurring_payment_token' );
		$this->assertEquals( $pspToken, $actual );
	}

	/**
	 * Test to verify that donations are not tokenized when value is less than
	 * Monthly Convert minimum amount for the specified currency
	 */
	public function testDoPaymentCardMonthlyConvertMinimumAmount() {
		$init = $this->getTestDonorCardData();
		$this->setMwGlobals( [
		'wgDonationInterfaceMonthlyConvertCountries' => [ 'US' ]
		] );
		$init += $this->encryptedCardData;
		$init['amount'] = '1.55';
		$gateway = $this->getFreshGatewayObject( $init );
		$expectedEncryptedParams = [
		'encryptedCardNumber' => $this->encryptedCardData['encrypted_card_number'],
		'encryptedExpiryMonth' => $this->encryptedCardData['encrypted_expiry_month'],
		'encryptedExpiryYear' => $this->encryptedCardData['encrypted_expiry_year'],
		'encryptedSecurityCode' => $this->encryptedCardData['encrypted_security_code'],
		];
		$pspReferenceAuth = 'ASD' . mt_rand( 100000, 1000000 );
		$pspReferenceCapture = 'BLA' . mt_rand( 100000000, 1000000000 );
		$expectedMerchantRef = $init['contribution_tracking_id'] . '.1';
		$expectedReturnUrl = Title::newFromText(
		'Special:AdyenCheckoutGatewayResult'
		)->getFullURL( [
		'order_id' => $expectedMerchantRef,
		'wmf_token' => $gateway->token_getSaltedSessionToken(),
		] );

		$this->cardPaymentProvider->expects( $this->once() )
		->method( 'createPayment' )
		->with( [
			'amount' => '1.55',
			'city' => 'NA',
			'country' => 'US',
			'currency' => 'USD',
			'description' => 'Wikimedia 877 600 9454',
			'email' => 'nobody@wikimedia.org',
			'first_name' => 'Firstname',
			'encrypted_payment_data' => $expectedEncryptedParams,
			'last_name' => 'Surname',
			'order_id' => $expectedMerchantRef,
			'postal_code' => '94105',
			'return_url' => $expectedReturnUrl,
			'state_province' => 'NA',
			'street_address' => '123 Fake Street',
			'user_ip' => '127.0.0.1'
		] )
		->willReturn(
			( new CreatePaymentResponse() )
			->setRawStatus( 'Authorized' )
			->setStatus( FinalStatus::PENDING_POKE )
			->setSuccessful( true )
			->setRiskScores( [ 'avs' => 10, 'cvv' => 20 ] )
			->setGatewayTxnId( $pspReferenceAuth )
		);
		$this->cardPaymentProvider->expects( $this->once() )
		->method( 'approvePayment' )
		->with( [
			'amount' => $init['amount'],
			'currency' => $init['currency'],
			'gateway_txn_id' => $pspReferenceAuth
		] )
		->willReturn(
			( new ApprovePaymentResponse() )
			->setRawStatus( '[capture-received]' )
			->setStatus( FinalStatus::COMPLETE )
			->setSuccessful( true )
			->setGatewayTxnId( $pspReferenceCapture )
		);

		$result = $gateway->doPayment();

		$this->assertFalse( $result->isFailed() );
		$this->assertSame( [], $result->getErrors() );
		// There should be a token in the stored data
		$actual_recurring = $gateway->getData_Unstaged_Escaped( 'recurring' );
		$this->assertSame( '', $actual_recurring );
		$actual_recurring_model = $gateway->getData_Unstaged_Escaped( 'recurring_model' );
		$this->assertSame( null, $actual_recurring_model );
	}

	public function testDoPaymentCardAuthorizationDeclined() {
		$init = $this->getTestDonorCardData();
		$init += $this->encryptedCardData;
		$init['amount'] = '1.55';
		$gateway = $this->getFreshGatewayObject( $init );
		$expectedEncryptedParams = [
			'encryptedCardNumber' => $this->encryptedCardData['encrypted_card_number'],
			'encryptedExpiryMonth' => $this->encryptedCardData['encrypted_expiry_month'],
			'encryptedExpiryYear' => $this->encryptedCardData['encrypted_expiry_year'],
			'encryptedSecurityCode' => $this->encryptedCardData['encrypted_security_code'],
		];
		$pspReferenceAuth = 'ASD' . mt_rand( 100000, 1000000 );
		$expectedMerchantRef = $init['contribution_tracking_id'] . '.1';
		$expectedReturnUrl = Title::newFromText(
			'Special:AdyenCheckoutGatewayResult'
		)->getFullURL( [
			'order_id' => $expectedMerchantRef,
			'wmf_token' => $gateway->token_getSaltedSessionToken(),
		] );

		$this->cardPaymentProvider->expects( $this->once() )
			->method( 'createPayment' )
			->with( [
				'amount' => '1.55',
				'city' => 'NA',
				'country' => 'US',
				'currency' => 'USD',
				'description' => 'Wikimedia 877 600 9454',
				'email' => 'nobody@wikimedia.org',
				'first_name' => 'Firstname',
				'encrypted_payment_data' => $expectedEncryptedParams,
				'last_name' => 'Surname',
				'order_id' => $expectedMerchantRef,
				'postal_code' => '94105',
				'return_url' => $expectedReturnUrl,
				'state_province' => 'NA',
				'street_address' => '123 Fake Street',
				'user_ip' => '127.0.0.1'
			] )
			->willReturn(
				( new CreatePaymentResponse() )
					->setRawStatus( 'Refused' )
					->setStatus( FinalStatus::FAILED )
					->setSuccessful( false )
					->setRiskScores( [ 'avs' => 10, 'cvv' => 20 ] )
					->setGatewayTxnId( $pspReferenceAuth )
			);
		$this->cardPaymentProvider->expects( $this->never() )
			->method( 'approvePayment' );

		$result = $gateway->doPayment();

		$this->assertTrue( $result->isFailed() );

		$messages = self::getAllQueueMessages();
		// Failed donations should not go to the donations queue
		$this->assertCount( 0, $messages['donations'] );
		// No pending message is sent in doPayment - that happens in the API
		$this->assertCount( 0, $messages['pending'] );
		// When the auth fails, we should only have the antifraud message from
		// the 'initial' run - the 'process' run should be skipped
		$this->assertCount( 1, $messages['payments-antifraud'] );
		$expectedAntifraudInitial = [
			'validation_action' => 'process',
			'risk_score' => 0,
			'score_breakdown' => [ 'initial' => 0 ],
			'user_ip' => '127.0.0.1',
			'gateway' => 'adyen',
			'contribution_tracking_id' => $init['contribution_tracking_id'],
			'order_id' => $expectedMerchantRef,
			'payment_method' => 'cc',
		];
		$this->assertArraySubmapSame(
			$expectedAntifraudInitial,
			$messages['payments-antifraud'][0]
		);
		$this->assertArraySubmapSame(
			[
				'validation_action' => 'process',
				'payments_final_status' => 'failed',
				'payment_submethod' => 'visa',
				'country' => 'US',
				'amount' => '1.55',
				'currency' => 'USD',
				'gateway' => 'adyen',
				'gateway_txn_id' => $pspReferenceAuth,
				'contribution_tracking_id' => $init['contribution_tracking_id'],
				'order_id' => $expectedMerchantRef,
				'payment_method' => 'cc',
			],
			$messages['payments-init'][0]
		);
	}

	/**
	 * Test what happens when redirected back from a successful
	 * 3D Secure authorization.
	 */
	public function testDonorReturn3DSecureSuccess() {
		$init = $this->getTestDonorCardData();
		$init['order_id'] = $init['contribution_tracking_id'] . '.1';
		$session = [
			'Donor' => $init,
			'risk_scores' => [
				'getScoreUtmMedium' => 10,
			],
			'adyenEditToken' => $this->clearToken
		];
		$queryString = [
			'order_id' => $init['order_id'],
			'wmf_token' => $this->saltedToken,
			'redirectResult' => $this->redirectResult
		];
		$this->setUpRequest( $queryString, $session );
		$pspReferenceAuth = 'ASD' . mt_rand( 100000, 1000000 );
		$pspReferenceCapture = 'BLA' . mt_rand( 100000, 1000000 );

		$this->cardPaymentProvider->expects( $this->once() )
			->method( 'getHostedPaymentDetails' )
			->with( $this->redirectResult )
			->willReturn(
				( new PaymentDetailResponse() )
					->setRawStatus( 'Authorized' )
					->setStatus( FinalStatus::PENDING_POKE )
					->setSuccessful( true )
					->setRiskScores( [ 'avs' => 10, 'cvv' => 20 ] )
					->setGatewayTxnId( $pspReferenceAuth )
			);
		$this->cardPaymentProvider->expects( $this->once() )
			->method( 'approvePayment' )
			->with( [
				'amount' => $init[ 'amount' ],
				'currency' => $init[ 'currency' ],
				'gateway_txn_id' => $pspReferenceAuth
			] )
			->willReturn(
				( new ApprovePaymentResponse() )
					->setRawStatus( '[capture-received]' )
					->setStatus( FinalStatus::COMPLETE )
					->setSuccessful( true )
					->setGatewayTxnId( $pspReferenceCapture )
			);

		$gateway = $this->getFreshGatewayObject( [] );
		$result = $gateway->processDonorReturn( $queryString );

		$this->assertFalse( $result->isFailed() );
		$this->assertSame( [], $result->getErrors() );

		$messages = $this::getAllQueueMessages();
		$this->assertCount( 1, $messages['donations'] );
		$expectedQueueMessage = [
			'contribution_tracking_id' => $init['contribution_tracking_id'],
			'country' => 'US',
			'currency' => 'USD',
			'email' => 'nobody@wikimedia.org',
			'first_name' => 'Firstname',
			'gateway' => 'adyen',
			'gateway_txn_id' => $pspReferenceAuth,
			'gross' => '4.55',
			'language' => 'en',
			'last_name' => 'Surname',
			'order_id' => $init['order_id'],
			'payment_method' => 'cc',
			'payment_submethod' => 'visa',
			'postal_code' => '94105',
			'user_ip' => '127.0.0.1'
		];
		$this->assertArraySubmapSame(
			$expectedQueueMessage,
			$messages['donations'][0]
		);
		// No pending message when we immediately capture the donation
		$this->assertCount( 0, $messages['pending'] );
		$this->assertCount( 1, $messages['payments-antifraud'] );
		$expectedAntiFraudProcess = [
				'validation_action' => 'process',
				'user_ip' => '127.0.0.1',
				'gateway' => 'adyen',
				'gateway_txn_id' => $pspReferenceAuth,
				'contribution_tracking_id' => $init['contribution_tracking_id'],
				'order_id' => $init['order_id'],
				'payment_method' => 'cc',
				'risk_score' => 17,
				'score_breakdown' => [
					'getAVSResult' => 5,
					'getCVVResult' => 2,
					'getScoreUtmMedium' => 10
				]
			];
		$this->assertArraySubmapSame(
			$expectedAntiFraudProcess,
			$messages['payments-antifraud'][0]
		);
		$this->assertCount( 1, $messages['payments-init'] );
		$this->assertArraySubmapSame(
			[
				'validation_action' => 'process',
				'payments_final_status' => 'complete',
				'payment_submethod' => 'visa',
				'country' => 'US',
				'amount' => '4.55',
				'currency' => 'USD',
				'gateway' => 'adyen',
				'gateway_txn_id' => $pspReferenceAuth,
				'contribution_tracking_id' => $init['contribution_tracking_id'],
				'order_id' => $init['order_id'],
				'payment_method' => 'cc',
			],
			$messages['payments-init'][0]
		);
	}

	/**
	 * Test what happens when redirected back from a failed
	 * 3D Secure authorization.
	 */
	public function testDonorReturn3DSecureFailure() {
		$init = $this->getTestDonorCardData();
		$init['order_id'] = $init['contribution_tracking_id'] . '.1';
		$session = [
			'Donor' => $init,
			'risk_scores' => [
				'getScoreUtmMedium' => 10,
			],
			'adyenEditToken' => $this->clearToken
		];
		$queryString = [
			'order_id' => $init['order_id'],
			'wmf_token' => $this->saltedToken,
			'redirectResult' => $this->redirectResult
		];
		$this->setUpRequest( $queryString, $session );
		$pspReferenceAuth = 'ASD' . mt_rand( 100000, 1000000 );

		$this->cardPaymentProvider->expects( $this->once() )
			->method( 'getHostedPaymentDetails' )
			->with( $this->redirectResult )
			->willReturn(
				( new PaymentDetailResponse() )
					->setRawResponse( [
						'additionalData' =>
							[
								'threeds2.threeDS2Result.dsTransID' => '92929c02-1f7f-4b26-b891-63a0cf8e01c3',
								'threeds2.threeDS2Result.eci' => '00',
								'threeds2.threeDS2Result.threeDSServerTransID' => 'd52d76f3-4f65-4596-5c03-d6e3e49d914b',
								'threeds2.threeDS2Result.transStatusReason' => '01',
								'threeds2.threeDS2Result.messageVersion' => '2.1.0',
								'threeds2.threeDS2Result.authenticationValue' => 'QURZRU4gM0RRMiBFRVNUIENBVlY=',
								'threeds2.threeDS2Result.transStatus' => 'N',
							],
						'pspReference' => $pspReferenceAuth,
						'refusalReason' => '3D Not Authenticated',
						'resultCode' => 'Refused',
						'refusalReasonCode' => '11',
						'merchantReference' => $init['order_id'],
					] )
					->setGatewayTxnId( $pspReferenceAuth )
					->setStatus( FinalStatus::FAILED )
					->setSuccessful( false )
					->setRawStatus( 'Refused' )
		);

		$gateway = $this->getFreshGatewayObject( [] );
		$result = $gateway->processDonorReturn( $queryString );

		$this->assertTrue( $result->isFailed() );

		$messages = self::getAllQueueMessages();
		// Failed donations should not go to the donations queue
		$this->assertCount( 0, $messages['donations'] );
		// No pending message - we just came back from redirect
		$this->assertCount( 0, $messages['pending'] );
		// We should not run extra antifraud checks when 3DSecure fails
		$this->assertCount( 0, $messages['payments-antifraud'] );
		$this->assertArraySubmapSame(
			[
				'validation_action' => 'process',
				'payments_final_status' => 'failed',
				'payment_submethod' => 'visa',
				'country' => 'US',
				'amount' => '4.55',
				'currency' => 'USD',
				'gateway' => 'adyen',
				'gateway_txn_id' => $pspReferenceAuth,
				'contribution_tracking_id' => $init['contribution_tracking_id'],
				'order_id' => $init['order_id'],
				'payment_method' => 'cc',
			],
			$messages['payments-init'][0]
		);
	}

	public function testDoPaymentFailInitialFilters() {
		$this->setInitialFiltersToFail();
		$init = $this->getDonorTestData();
		$init['payment_method'] = 'cc';

		$gateway = $this->getFreshGatewayObject( $init );
		$result = $gateway->doPayment();

		$this->assertNotEmpty( $result->getErrors(), 'Should have returned an error' );
	}

	/**
	 * @return array
	 */
	protected function getTestDonorCardData(): array {
		$init = $this->getDonorTestData();
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['contribution_tracking_id'] = (string)mt_rand( 1000000, 10000000 );
		unset( $init['city'] );
		unset( $init['state_province'] );
		return $init;
	}
}
