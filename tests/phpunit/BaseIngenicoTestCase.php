<?php

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

	protected function setUp() {
		parent::setUp();
		$providerConfig = $this->setSmashPigProvider( 'ingenico' );

		$this->hostedCheckoutProvider = $this->getMockBuilder(
			HostedCheckoutProvider::class
		)->disableOriginalConstructor()->getMock();

		$providerConfig->overrideObjectInstance(
			'payment-provider/cc',
			$this->hostedCheckoutProvider
		);

		$vmad_countries = array( 'US', );
		$vmaj_countries = array(
			'AD', 'AT', 'AU', 'BE', 'BH', 'DE', 'EC', 'ES', 'FI', 'FR', 'GB',
			'GF', 'GR', 'HK', 'IE', 'IT', 'JP', 'KR', 'LU', 'MY', 'NL', 'PR',
			'PT', 'SG', 'SI', 'SK', 'TH', 'TW',
		);
		$vma_countries = array(
			'AE', 'AL', 'AN', 'AR', 'BG', 'CA', 'CH', 'CN', 'CR', 'CY', 'CZ', 'DK',
			'DZ', 'EE', 'EG', 'JO', 'KE', 'HR', 'HU', 'IL', 'KW', 'KZ', 'LB', 'LI',
			'LK', 'LT', 'LV', 'MA', 'MT', 'NO', 'NZ', 'OM', 'PK', 'PL', 'QA', 'RO',
			'RU', 'SA', 'SE', 'TN', 'TR', 'UA',
		);
		$this->setMwGlobals( array(
			'wgIngenicoGatewayEnabled' => true,
			'wgDonationInterfaceAllowedHtmlForms' => array(
				'cc-vmad' => array(
					'gateway' => 'ingenico',
					'payment_methods' => array( 'cc' => array( 'visa', 'mc', 'amex', 'discover' ) ),
					'countries' => array(
						'+' => $vmad_countries,
					),
				),
				'cc-vmaj' => array(
					'gateway' => 'ingenico',
					'payment_methods' => array( 'cc' => array( 'visa', 'mc', 'amex', 'jcb' ) ),
					'countries' => array(
						'+' => $vmaj_countries,
					),
				),
				'cc-vma' => array(
					'gateway' => 'ingenico',
					'payment_methods' => array( 'cc' => array( 'visa', 'mc', 'amex' ) ),
					'countries' => array(
						// Array merge with cc-vmaj as fallback in case 'j' goes down
						// Array merge with cc-vmad as fallback in case 'd' goes down
						'+' => array_merge(
							$vmaj_countries,
							$vmad_countries,
							$vma_countries
						),
					),
				),
				'rtbt-sofo' => array(
					'gateway' => 'ingenico',
					'countries' => array(
						'+' => array( 'AT', 'BE', 'CH', 'DE' ),
						'-' => 'GB'
					),
					'currencies' => array( '+' => 'EUR' ),
					'payment_methods' => array( 'rtbt' => 'rtbt_sofortuberweisung' ),
				),
			),
		) );

		$this->partialUrl = 'poweredbyglobalcollect.com/pay8915-53ebca407e6b4a1dbd086aad4f10354d:' .
			'8915-28e5b79c889641c8ba770f1ba576c1fe:9798f4c44ac6406e8288494332d1daa0';

		$this->hostedCheckoutCreateResponse = array(
			'partialRedirectUrl' => $this->partialUrl,
			'hostedCheckoutId' => '8915-28e5b79c889641c8ba770f1ba576c1fe',
			'RETURNMAC' => 'f5b66cf9-c64c-4c8d-8171-b47205c89a56'
		);

		$this->hostedPaymentStatusResponse = array(
			"createdPaymentOutput" => array(
				"payment" => array(
					"id" => "000000891566072501680000200001",
					"paymentOutput" => array(
						"amountOfMoney" => array(
							"amount" => 2345,
							"currencyCode" => "USD"
						),
						"references" => array(
							"paymentReference" => "0"
						),
						"paymentMethod" => "card",
						"cardPaymentMethodSpecificOutput" => array(
							"paymentProductId" => 1,
							"authorisationCode" => "123456",
							"card" => array(
								"cardNumber" => "************7977",
								"expiryDate" => "1220"
							),
							"fraudResults" => array(
								"avsResult" => "0",
								"cvvResult" => "M",
								"fraudServiceResult" => "no-advice"
							)
						)
					),
					"status" => "PENDING_APPROVAL",
					"statusOutput" => array(
						"isCancellable" => true,
						"statusCode" => 600,
						"statusCodeChangeDateTime" => "20140717145840",
						"isAuthorized" => true
					)
				),
				"paymentCreationReferences" => array(
					"additionalReference" => "00000089156607250168",
					"externalReference" => "000000891566072501680000200001"
				),
				"tokens" => ""
			),
			"status" => "PAYMENT_CREATED"
		);

		$this->hostedPaymentStatusResponseBadCvv = $this->hostedPaymentStatusResponse;
		$this->hostedPaymentStatusResponseBadCvv['createdPaymentOutput']['payment']
			['paymentOutput'] ['cardPaymentMethodSpecificOutput']['fraudResults']
			['cvvResult'] = 'N';

		$this->approvePaymentResponse = array(
			"payment" => array(
				"id" => "000000850010000188180000200001",
				"paymentOutput" => array(
					"amountOfMoney" => array(
						"amount" => 2890,
						"currencyCode" => "EUR"
					),
					"references" => array(
						"paymentReference" => "0"
					),
					"paymentMethod" => "card",
					"cardPaymentMethodSpecificOutput" => array(
						"paymentProductId" => 1,
						"authorisationCode" => "123456",
						"card" => array(
							"cardNumber" => "************7977",
							"expiryDate" => "1220"
						),
						"fraudResults" => array(
							"avsResult" => "0",
							"cvvResult" => "M",
							"fraudServiceResult" => "no-advice"
						)
					)
				),
				"status" => "CAPTURE_REQUESTED",
				"statusOutput" => array(
					"isCancellable" => false,
					"statusCode" => 800,
					"statusCodeChangeDateTime" => "20140627140735",
					"isAuthorized" => true
				)
			)
		);
	}

	public static function getDonorTestData( $country = '' ) {
		$data = parent::getDonorTestData( $country );
		$data['gateway_session_id'] = mt_rand();
		return $data;
	}
}
