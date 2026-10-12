<?php
use MediaWiki\Extension\DonationInterface\Api\GravyGetPaymentMethodsApi;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Gravy\CardPaymentProvider;
use SmashPig\PaymentProviders\Gravy\PaymentProvider;
use SmashPig\PaymentProviders\Responses\PaymentMethodResponse;
use SmashPig\Tests\TestingContext;
use SmashPig\Tests\TestingProviderConfiguration;

/**
 * @group Fundraising
 * @group DonationInterface
 * @group Gravy
 * @group DonationInterfaceApi
 * @coversNothing
 */
class GravyGetPaymentMethodsApiTest extends DonationInterfaceApiTestCase {

	/**
	 * Mocked SmashPig-layer PaymentProvider object
	 * @var \PHPUnit\Framework\MockObject\MockObject|PaymentProvider
	 */
	private $paymentProvider;

	protected function setUp(): void {
		parent::setUp();
		$ctx = TestingContext::get();
		$globalConfig = $ctx->getGlobalConfiguration();
		$providerConfig = TestingProviderConfiguration::createForProvider( 'gravy', $globalConfig );
		$ctx->providerConfigurationOverride = $providerConfig;
		$this->paymentProvider = $this->createMock( CardPaymentProvider::class );

		$providerConfig->overrideObjectInstance( 'payment-provider/cc', $this->paymentProvider );
		$this->mergeMwGlobalArrayValue( 'wgAPIModules', [ 'getPaymentMethods' => GravyGetPaymentMethodsApi::class ] );
	}

	public function testGetPaymentMethod() {
		$init = [
			'country' => 'US',
		];
		$init['gateway'] = 'gravy';
		$init['action'] = 'getPaymentMethods';
		$init['format'] = 'json';
		$paymentMethods = [
			[
				'brands' => [ 'visa', 'mastercard' ],
				'configuration' => [
					'merchantName' => 'Wikimedia Foundation'
				],
				'name' => 'Apple Pay',
				'type' => 'applepay'
			]
		];
		$paymentMethodResponse = ( new PaymentMethodResponse() )
			->setRawResponse( [ 'items' => [] ] )
			->setSuccessful( true )
			->setStatus( FinalStatus::COMPLETE );
		$paymentMethodResponse->setPaymentMethods( $paymentMethods );

		$this->paymentProvider->expects( $this->once() )
			->method( 'getPaymentMethods' )
			->with( $this->callback( function ( $params ) {
				$this->assertEquals( 'US', $params['country'], 'Country mismatch' );
				return true;
			} ) )
			->willReturn( $paymentMethodResponse );

		$apiResult = $this->doApiRequest( $init );
		$result = $apiResult[0]['response'];
		$this->assertNotEmpty( $result );
		$this->assertEquals( $paymentMethods, $result['paymentMethods'] );
	}
}
