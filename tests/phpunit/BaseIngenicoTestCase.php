<?php

use SmashPig\Core\Context;
use SmashPig\PaymentProviders\Ingenico\HostedCheckoutProvider;
use SmashPig\Tests\TestingContext;
use SmashPig\Tests\TestingProviderConfiguration;

class BaseIngenicoTestCase extends DonationInterfaceTestCase {

	/**
	 * @var HostedCheckoutProvider
	 */
	protected $hostedCheckoutProvider;

	protected $testAdapterClass = 'IngenicoAdapter';

	protected function setUp() {
		parent::setUp();
		$providerConfig = $this->setSmashPigProvider( 'ingenico' );

		$this->hostedCheckoutProvider = $this->getMockBuilder(
			'SmashPig\PaymentProviders\Ingenico\HostedCheckoutProvider'
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
	}
}
