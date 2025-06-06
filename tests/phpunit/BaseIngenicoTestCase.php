<?php

use PHPUnit\Framework\MockObject\MockObject;
use SmashPig\Core\Http\CurlWrapper;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Ingenico\HostedCheckoutProvider;
use SmashPig\PaymentProviders\Responses\ApprovePaymentResponse;
use SmashPig\PaymentProviders\Responses\PaymentProviderExtendedResponse;
use SmashPig\Tests\TestingContext;

class BaseIngenicoTestCase extends DonationInterfaceTestCase {

	/** @var string */
	protected $partialUrl;

	/** @var string */
	protected $hostedCheckoutCreateResponse;

	/**
	 * @var array good response from Ingenico for setup call
	 */
	public static $hostedPaymentStatusRawResponse = [
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
						"schemeTransactionId" => "112233445566",
						"fraudResults" => [
							"avsResult" => "0",
							"cvvResult" => "M",
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
	];

	/**
	 * @var PaymentProviderExtendedResponse
	 */
	protected $hostedPaymentStatusResponse;

	/**
	 * @var PaymentProviderExtendedResponse
	 */
	protected $hostedPaymentStatusResponseBadCvv;

	/** @var ApprovePaymentResponse */
	protected $approvePaymentResponse;

	/**
	 * @var HostedCheckoutProvider
	 */
	protected $hostedCheckoutProvider;

	/**
	 * @var MockObject|CurlWrapper
	 */
	protected $curlWrapper;

	/** @inheritDoc */
	protected $testAdapterClass = IngenicoAdapter::class;

	protected function setUp(): void {
		parent::setUp();
		$providerConfig = $this->setSmashPigProvider( 'ingenico' );

		$this->hostedCheckoutProvider = $this->createMock( HostedCheckoutProvider::class );

		$providerConfig->overrideObjectInstance(
			'payment-provider/cc',
			$this->hostedCheckoutProvider
		);

		$this->overrideConfigValue( 'IngenicoGatewayEnabled', true );

		$this->partialUrl = 'poweredbyglobalcollect.com/pay8915-53ebca407e6b4a1dbd086aad4f10354d:' .
			'8915-28e5b79c889641c8ba770f1ba576c1fe:9798f4c44ac6406e8288494332d1daa0';

		$this->hostedPaymentStatusResponse = self::getHostedPaymentStatusResponse();

		$hostedPaymentStatusRawResponseBadCvv = self::$hostedPaymentStatusRawResponse;
		$hostedPaymentStatusRawResponseBadCvv['createdPaymentOutput']['payment']
			['paymentOutput']['cardPaymentMethodSpecificOutput']['fraudResults']
			['cvvResult'] = 'N';

		$this->hostedPaymentStatusResponseBadCvv = ( new PaymentProviderExtendedResponse() )
			->setPaymentSubmethod( 'visa' )
			->setRiskScores( [
				'avs' => 0,
				'cvv' => 100,
			] )
			->setRawResponse( $hostedPaymentStatusRawResponseBadCvv )
			->setRawStatus( '600' )
			->setStatus( FinalStatus::PENDING_POKE )
			->setSuccessful( true )
			->setInitialSchemeTransactionId( "112233445566" )
			->setGatewayTxnId( '000000891566072501680000200001' )
			->setAmount( '23.45' )
			->setCurrency( 'USD' );

		$this->approvePaymentResponse = self::getApprovePaymentResponse();
	}

	public static function getHostedPaymentStatusResponse(): PaymentProviderExtendedResponse {
		return ( new PaymentProviderExtendedResponse() )
			->setPaymentSubmethod( 'visa' )
			->setRiskScores( [
				'avs' => 0,
				'cvv' => 0,
			] )
			->setRawResponse( self::$hostedPaymentStatusRawResponse )
			->setRawStatus( '600' )
			->setStatus( FinalStatus::PENDING_POKE )
			->setSuccessful( true )
			->setInitialSchemeTransactionId( "112233445566" )
			->setGatewayTxnId( '000000891566072501680000200001' )
			->setAmount( '23.45' )
			->setCurrency( 'USD' );
	}

	public static function getApprovePaymentResponse(): ApprovePaymentResponse {
		return ( new ApprovePaymentResponse() )
			->setRawResponse(
				[
					"payment" => [
						"id" => "000000850010000188180000200001",
						"paymentOutput" => [
							"amountOfMoney" => [
								"amount" => 2890,
								"currencyCode" => "EUR"
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
									"avsResult" => "0",
									"cvvResult" => "M",
									"fraudServiceResult" => "no-advice"
								]
							]
						],
						"status" => "CAPTURE_REQUESTED",
						"statusOutput" => [
							"isCancellable" => false,
							"statusCode" => 800,
							"statusCodeChangeDateTime" => "20140627140735",
							"isAuthorized" => true
						]
					]
				]
			)
			->setRawStatus( '800' )
			->setStatus( FinalStatus::COMPLETE )
			->setSuccessful( true )
			->setGatewayTxnId( '000000850010000188180000200001' );
	}

	public function setUpIntegrationMocks() {
		$providerConfig = TestingContext::get()->getProviderConfiguration();
		$this->hostedCheckoutProvider = $this->getMockBuilder( HostedCheckoutProvider::class )
			->enableProxyingToOriginalMethods()
			->disableOriginalClone()
			->disableArgumentCloning()
			->disallowMockingUnknownTypes()
			->setConstructorArgs( [ [ 'subdomain' => 'wmf-pay' ] ] )
			->getMock();

		$providerConfig->overrideObjectInstance(
			'payment-provider/cc',
			$this->hostedCheckoutProvider
		);

		$this->curlWrapper = $this->createMock( CurlWrapper::class );
		$providerConfig->overrideObjectInstance( 'curl/wrapper', $this->curlWrapper );
	}

	public static function getDonorTestData( $country = '' ) {
		$data = parent::getDonorTestData( $country );
		$data['gateway_session_id'] = (string)mt_rand();
		return $data;
	}

	/**
	 * @return array
	 */
	protected function getGoodHostedCheckoutCurlResponse(): array {
		return [
			'body' => json_encode( [
				'partialRedirectUrl' => $this->partialUrl,
				'hostedCheckoutId' => '8915-28e5b79c889641c8ba770f1ba576c1fe',
				'RETURNMAC' => 'f5b66cf9-c64c-4c8d-8171-b47205c89a56'
			] ),
			'headers' => [],
			'status' => 200
		];
	}
}
