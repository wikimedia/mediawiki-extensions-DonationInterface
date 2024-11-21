<?php
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Adyen\ApplePayPaymentProvider;
use SmashPig\PaymentProviders\Responses\PaymentMethodResponse;
use SmashPig\Tests\TestingContext;
use SmashPig\Tests\TestingProviderConfiguration;

/**
 * @group Fundraising
 * @group DonationInterface
 * @group Adyen
 * @group DonationInterfaceApi
 */
class AdyenGetPaymentMethodsApiTest extends DonationInterfaceApiTestCase {

	/**
	 * Mocked SmashPig-layer PaymentProvider object
	 * @var \PHPUnit\Framework\MockObject\MockObject|PaymentProvider
	 */
	private $applepayPaymentProvider;

	protected function setUp(): void {
		parent::setUp();
		$ctx = TestingContext::get();
		$globalConfig = $ctx->getGlobalConfiguration();
		$providerConfig = TestingProviderConfiguration::createForProvider( 'adyen', $globalConfig );
		$ctx->providerConfigurationOverride = $providerConfig;
		$this->applepayPaymentProvider = $this->createMock( ApplePayPaymentProvider::class );

		$providerConfig->overrideObjectInstance( 'payment-provider/apple', $this->applepayPaymentProvider );
	}

	public function testGetPaymentMethod() {
		$init = [
			'country' => 'US',
		];
		$init['gateway'] = 'adyen';
		$init['action'] = 'getPaymentMethods';
		$init['format'] = 'json';
		$this->applepayPaymentProvider->expects( $this->once() )
			->method( 'getPaymentMethods' )
			->with( $this->callback( function ( $params ) {
				$this->assertEquals( 'US', $params['country'], 'Country mismatch' );
				$this->assertEquals( 'iOS', $params['channel'], 'Channel mismatch' );
				return true;
			} ) )
			->willReturn(
				( new PaymentMethodResponse() )
					->setRawResponse( [
						"paymentMethods" => []
					] )
					->setSuccessful( true )
					->setStatus( FinalStatus::COMPLETE )
			);

		$apiResult = $this->doApiRequest( $init );
		$result = $apiResult[0]['response'];
		$this->assertNotEmpty( $result );
	}
}
