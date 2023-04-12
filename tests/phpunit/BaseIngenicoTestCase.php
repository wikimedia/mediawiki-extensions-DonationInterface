<?php

use SmashPig\Core\Http\CurlWrapper;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Ingenico\HostedCheckoutProvider;
use SmashPig\PaymentProviders\Responses\ApprovePaymentResponse;
use SmashPig\PaymentProviders\Responses\PaymentDetailResponse;
use SmashPig\Tests\TestingContext;

class BaseIngenicoTestCase extends DonationInterfaceTestCase {

	protected $partialUrl;

	protected $hostedCheckoutCreateResponse;

	protected $hostedPaymentStatusRawResponse;

	protected $hostedPaymentStatusRawResponseBadCvv;

	protected $approvePaymentResponse;

	/**
	 * @var HostedCheckoutProvider
	 */
	protected $hostedCheckoutProvider;

	/**
	 * @var PHPUnit_Framework_MockObject_MockObject|CurlWrapper
	 */
	protected $curlWrapper;

	protected $testAdapterClass = IngenicoAdapter::class;

	protected function setUp(): void {
		parent::setUp();
		$providerConfig = $this->setSmashPigProvider( 'ingenico' );

		$this->hostedCheckoutProvider = $this->createMock( HostedCheckoutProvider::class );

		$providerConfig->overrideObjectInstance(
			'payment-provider/cc',
			$this->hostedCheckoutProvider
		);

		$this->setMwGlobals( [
			'wgIngenicoGatewayEnabled' => true,
		] );

		$this->partialUrl = 'poweredbyglobalcollect.com/pay8915-53ebca407e6b4a1dbd086aad4f10354d:' .
			'8915-28e5b79c889641c8ba770f1ba576c1fe:9798f4c44ac6406e8288494332d1daa0';

		$this->hostedPaymentStatusRawResponse = [
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

		$this->hostedPaymentStatusResponse = new PaymentDetailResponse();
		$this->hostedPaymentStatusResponse->setRawResponse( $this->hostedPaymentStatusRawResponse );
		$this->hostedPaymentStatusResponse->setSuccessful( true );
		$this->hostedPaymentStatusResponse->setInitialSchemeTransactionId( "112233445566" );

		$this->hostedPaymentStatusRawResponseBadCvv = $this->hostedPaymentStatusRawResponse;
		$this->hostedPaymentStatusRawResponseBadCvv['createdPaymentOutput']['payment']
			['paymentOutput']['cardPaymentMethodSpecificOutput']['fraudResults']
			['cvvResult'] = 'N';

		$this->hostedPaymentStatusResponseBadCvv = new PaymentDetailResponse();
		$this->hostedPaymentStatusResponseBadCvv->setRawResponse( $this->hostedPaymentStatusRawResponseBadCvv );
		$this->hostedPaymentStatusResponse->setSuccessful( true );

		$this->approvePaymentResponse = ( new ApprovePaymentResponse() )
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
