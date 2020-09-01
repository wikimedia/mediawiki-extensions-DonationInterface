<?php

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\ApprovePaymentResponse;
use SmashPig\PaymentProviders\Ingenico\HostedCheckoutProvider;

class BaseIngenicoTestCase extends DonationInterfaceTestCase {

	protected $partialUrl;

	protected $hostedCheckoutCreateResponse;

	protected $hostedPaymentStatusResponse;

	protected $hostedPaymentStatusResponseBadCvv;

	protected $approvePaymentResponse;

	/**
	 * @var HostedCheckoutProvider
	 */
	protected $hostedCheckoutProvider;

	protected $testAdapterClass = IngenicoAdapter::class;

	protected function setUp(): void {
		parent::setUp();
		$providerConfig = $this->setSmashPigProvider( 'ingenico' );

		$this->hostedCheckoutProvider = $this->getMockBuilder(
			HostedCheckoutProvider::class
		)->disableOriginalConstructor()->getMock();

		$providerConfig->overrideObjectInstance(
			'payment-provider/cc',
			$this->hostedCheckoutProvider
		);

		$vmad_countries = [ 'US', ];
		$vmaj_countries = [
			'AD', 'AT', 'AU', 'BE', 'BH', 'DE', 'EC', 'ES', 'FI', 'FR', 'GB',
			'GF', 'GR', 'HK', 'IE', 'IT', 'JP', 'KR', 'LU', 'MY', 'NL', 'PR',
			'PT', 'SG', 'SI', 'SK', 'TH', 'TW',
		];
		$vma_countries = [
			'AE', 'AL', 'AN', 'AR', 'BG', 'CA', 'CH', 'CN', 'CR', 'CY', 'CZ', 'DK',
			'DZ', 'EE', 'EG', 'JO', 'KE', 'HR', 'HU', 'IL', 'KW', 'KZ', 'LB', 'LI',
			'LK', 'LT', 'LV', 'MA', 'MT', 'NO', 'NZ', 'OM', 'PK', 'PL', 'QA', 'RO',
			'RU', 'SA', 'SE', 'TN', 'TR', 'UA',
		];
		$this->setMwGlobals( [
			'wgIngenicoGatewayEnabled' => true,
			'wgDonationInterfaceAllowedHtmlForms' => [
				'cc-vmad' => [
					'gateway' => 'ingenico',
					'payment_methods' => [ 'cc' => [ 'visa', 'mc', 'amex', 'discover' ] ],
					'countries' => [
						'+' => $vmad_countries,
					],
				],
				'cc-vmaj' => [
					'gateway' => 'ingenico',
					'payment_methods' => [ 'cc' => [ 'visa', 'mc', 'amex', 'jcb' ] ],
					'countries' => [
						'+' => $vmaj_countries,
					],
				],
				'cc-vma' => [
					'gateway' => 'ingenico',
					'payment_methods' => [ 'cc' => [ 'visa', 'mc', 'amex' ] ],
					'countries' => [
						// Array merge with cc-vmaj as fallback in case 'j' goes down
						// Array merge with cc-vmad as fallback in case 'd' goes down
						'+' => array_merge(
							$vmaj_countries,
							$vmad_countries,
							$vma_countries
						),
					],
				],
				'rtbt-sofo' => [
					'gateway' => 'ingenico',
					'countries' => [
						'+' => [ 'AT', 'BE', 'CH', 'DE' ],
						'-' => 'GB'
					],
					'currencies' => [ '+' => 'EUR' ],
					'payment_methods' => [ 'rtbt' => 'rtbt_sofortuberweisung' ],
				],
			],
		] );

		$this->partialUrl = 'poweredbyglobalcollect.com/pay8915-53ebca407e6b4a1dbd086aad4f10354d:' .
			'8915-28e5b79c889641c8ba770f1ba576c1fe:9798f4c44ac6406e8288494332d1daa0';

		$this->hostedCheckoutCreateResponse = [
			'partialRedirectUrl' => $this->partialUrl,
			'hostedCheckoutId' => '8915-28e5b79c889641c8ba770f1ba576c1fe',
			'RETURNMAC' => 'f5b66cf9-c64c-4c8d-8171-b47205c89a56'
		];

		$this->hostedPaymentStatusResponse = [
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

		$this->hostedPaymentStatusResponseBadCvv = $this->hostedPaymentStatusResponse;
		$this->hostedPaymentStatusResponseBadCvv['createdPaymentOutput']['payment']
			['paymentOutput'] ['cardPaymentMethodSpecificOutput']['fraudResults']
			['cvvResult'] = 'N';

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
			->setGatewayTxnId( '000000850010000188180000200001' );
	}

	public static function getDonorTestData( $country = '' ) {
		$data = parent::getDonorTestData( $country );
		$data['gateway_session_id'] = mt_rand();
		return $data;
	}
}
