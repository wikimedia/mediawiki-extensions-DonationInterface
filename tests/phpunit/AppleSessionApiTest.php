<?php

use SmashPig\PaymentProviders\Adyen\ApplePayPaymentProvider as AdyenApplePayPaymentProvider;
use SmashPig\PaymentProviders\Gravy\ApplePayPaymentProvider as GravyApplePayPaymentProvider;
use SmashPig\PaymentProviders\Responses\CreatePaymentSessionResponse;

/**
 * @group Fundraising
 * @group DonationInterface
 * @group DonationInterfaceApi
 * @covers \MediaWiki\Extension\DonationInterface\Api\AppleSessionApi
 * @covers \MediaWiki\Extension\DonationInterface\Api\AdyenAppleSessionApi
 * @covers \MediaWiki\Extension\DonationInterface\Api\GravyAppleSessionApi
 */
class AppleSessionApiTest extends DonationInterfaceApiTestCase {

	private const VALIDATION_URL = 'https://test/session';

	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValues( [
			'AdyenCheckoutGatewayEnabled' => true,
			'GravyGatewayEnabled' => true,
			'DonationInterfaceGatewayAdapters' => [
				'adyen' => AdyenCheckoutAdapter::class,
				'gravy' => GravyAdapter::class,
			],
		] );
	}

	public function testAdyenCreatesApplePaySession() {
		$sessionData = [
			'epochTimestamp' => 1700000000000,
			'merchantSessionIdentifier' => 'SSH_ADYEN_SESSION',
			'signature' => 'adyen_signature',
		];
		$provider = $this->createMock( AdyenApplePayPaymentProvider::class );
		$provider->expects( $this->once() )
			->method( 'createPaymentSession' )
			->with( $this->callback( function ( $params ) {
				$this->assertSame( self::VALIDATION_URL, $params['validation_url'] );
				$this->assertNotEmpty( $params['domain_name'] );
				$this->assertStringNotContainsString( '://', $params['domain_name'] );
				return true;
			} ) )
			->willReturn( $sessionData );
		$this->setSmashPigProvider( 'adyen' )
			->overrideObjectInstance( 'payment-provider/apple', $provider );

		$apiResult = $this->doApiRequest(
			$this->getRequest( 'adyen' ),
			[ 'adyenEditToken' => $this->clearToken ]
		);

		$this->assertSame( $sessionData, $apiResult[0]['session'] );
	}

	public function testGravyCreatesApplePaySession() {
		$rawSession = [
			'epochTimestamp' => 1700000000000,
			'merchantSessionIdentifier' => 'SSH_GRAVY_SESSION',
			'signature' => 'gravy_signature',
		];
		$provider = $this->createMock( GravyApplePayPaymentProvider::class );
		$provider->expects( $this->once() )
			->method( 'createPaymentSession' )
			->with( $this->callback( function ( $params ) {
				$this->assertSame( self::VALIDATION_URL, $params['validation_url'] );
				$this->assertNotEmpty( $params['domain_name'] );
				$this->assertStringNotContainsString( '://', $params['domain_name'] );
				return true;
			} ) )
			->willReturn(
				( new CreatePaymentSessionResponse() )
					->setRawResponse( $rawSession )
					->setSuccessful( true )
			);
		$this->setSmashPigProvider( 'gravy' )
			->overrideObjectInstance( 'payment-provider/apple', $provider );

		$apiResult = $this->doApiRequest(
			$this->getRequest( 'gravy' ),
			[ 'gravyEditToken' => $this->clearToken ]
		);

		// Gravy returns a response object; the API should unwrap it to the raw session
		$this->assertSame( $rawSession, $apiResult[0]['session'] );
	}

	private function getRequest( string $gateway ): array {
		return [
			'action' => 'di_applesession_' . $gateway,
			'currency' => 'USD',
			'amount' => '5',
			'country' => 'US',
			'gateway' => $gateway,
			'validation_url' => self::VALIDATION_URL,
			'wmf_token' => $this->saltedToken,
			'format' => 'json',
		];
	}
}
