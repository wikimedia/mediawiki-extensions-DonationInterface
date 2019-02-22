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
 *
 */

/**
 *
 * @group Fundraising
 * @group DonationInterface
 * @group GlobalCollect
 */
class GlobalCollectFormLoadTest extends DonationInterfaceTestCase {
	public function setUp() {
		parent::setUp();

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
			'wgGlobalCollectGatewayEnabled' => true,
			'wgDonationInterfaceAllowedHtmlForms' => [
				'cc-vmad' => [
					'gateway' => 'globalcollect',
					'payment_methods' => [ 'cc' => [ 'visa', 'mc', 'amex', 'discover' ] ],
					'countries' => [
						'+' => $vmad_countries,
					],
				],
				'cc-vmaj' => [
					'gateway' => 'globalcollect',
					'payment_methods' => [ 'cc' => [ 'visa', 'mc', 'amex', 'jcb' ] ],
					'countries' => [
						'+' => $vmaj_countries,
					],
				],
				'cc-vma' => [
					'gateway' => 'globalcollect',
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
					'gateway' => 'globalcollect',
					'countries' => [
						'+' => [ 'AT', 'BE', 'CH', 'DE' ],
						'-' => 'GB'
					],
					'currencies' => [ '+' => 'EUR' ],
					'payment_methods' => [ 'rtbt' => 'rtbt_sofortuberweisung' ],
				],
			],
		] );
	}

	public function testGCFormLoad() {
		$init = $this->getDonorTestData( 'US' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['ffname'] = 'cc-vmad';

		$assertNodes = [
			'submethod-mc' => [
				'nodename' => 'input'
			],
			'selected-amount' => [
				'nodename' => 'span',
				'innerhtmlmatches' => '/^\s*' .
					str_replace( '$', '\$',
						Amount::format( 1.55, 'USD', $init['language'] . '_' . $init['country'] )
					).
					'\s*$/',
			],
			'state_province' => [
				'nodename' => 'select',
				'selected' => 'CA',
			],
		];

		$this->verifyFormOutput( 'GlobalCollectGateway', $init, $assertNodes, true );
	}

	function testGCFormLoad_FR() {
		$init = $this->getDonorTestData( 'FR' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['ffname'] = 'cc-vmaj';

		$assertNodes = [
			'selected-amount' => [
				'nodename' => 'span',
				'innerhtmlmatches' => '/^\s*' .
					Amount::format( 1.55, 'EUR', $init['language'] . '_' . $init['country'] ) .
					'\s*$/',
			],
			'first_name' => [
				'nodename' => 'input',
				'value' => 'Prénom',
			],
			'last_name' => [
				'nodename' => 'input',
				'value' => 'Nom',
			],
			'country' => [
				'nodename' => 'input',
				'value' => 'FR',
			],
		];

		$this->verifyFormOutput( 'GlobalCollectGateway', $init, $assertNodes, true );
	}

	/**
	 * Ensure that form loads for Italy
	 */
	public function testGlobalCollectFormLoad_IT() {
		$init = $this->getDonorTestData( 'IT' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['ffname'] = 'cc-vmaj';

		$assertNodes = [
			'selected-amount' => [
				'nodename' => 'span',
				'innerhtmlmatches' => '/^\s*' .
					Amount::format( 1.55, 'EUR', $init['language'] . '_' . $init['country'] ) .
					'\s*$/',
			],
			'first_name' => [
				'nodename' => 'input',
				'placeholder' => wfMessage( 'donate_interface-donor-first_name' )->inLanguage( 'it' )->text(),
			],
			'last_name' => [
				'nodename' => 'input',
				'placeholder' => wfMessage( 'donate_interface-donor-last_name' )->inLanguage( 'it' )->text(),
			],
			'informationsharing' => [
				'nodename' => 'p',
				'innerhtmlmatches' => '~' . wfMessage( 'donate_interface-informationsharing', '.*' )->inLanguage( 'it' )->text() . '~',
			],
			'country' => [
				'nodename' => 'input',
				'value' => 'IT',
			],
		];

		$this->verifyFormOutput( 'GlobalCollectGateway', $init, $assertNodes, true );
	}

	/**
	 * Make sure Belgian form loads in all of that country's supported languages
	 * @dataProvider belgiumLanguageProvider
	 */
	public function testGlobalCollectFormLoad_BE( $language ) {
		$init = $this->getDonorTestData( 'BE' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['ffname'] = 'cc-vmaj';
		$init['language'] = $language;

		$assertNodes = [
			'selected-amount' => [
				'nodename' => 'span',
				'innerhtmlmatches' => '/^\s*' .
					Amount::format( 1.55, 'EUR', $init['language'] . '_' . $init['country'] ) .
					'\s*$/',
			],
			'first_name' => [
				'nodename' => 'input',
				'placeholder' => wfMessage( 'donate_interface-donor-first_name' )->inLanguage( $language )->text(),
			],
			'last_name' => [
				'nodename' => 'input',
				'placeholder' => wfMessage( 'donate_interface-donor-last_name' )->inLanguage( $language )->text(),
			],
			'informationsharing' => [
				'nodename' => 'p',
				'innerhtmlmatches' => '~' . wfMessage( 'donate_interface-informationsharing', '.*' )->inLanguage( $language )->text() . '~',
			],
			'country' => [
				'nodename' => 'input',
				'value' => 'BE',
			],
		];

		$this->verifyFormOutput( 'GlobalCollectGateway', $init, $assertNodes, true );
	}

	/**
	 * Make sure Canadian CC form loads in English and French
	 * @dataProvider canadaLanguageProvider
	 */
	public function testGlobalCollectFormLoad_CA( $language ) {
		$init = $this->getDonorTestData( 'CA' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['ffname'] = 'cc-vma';
		$init['language'] = $language;

		$assertNodes = [
			'selected-amount' => [
				'nodename' => 'span',
				'innerhtmlmatches' => '/^\s*' .
					str_replace( '$', '\$',
						Amount::format( 1.55, 'CAD', $init['language'] . '_' . $init['country'] )
					) .
					'\s*$/',
			],
			'first_name' => [
				'nodename' => 'input',
				'placeholder' => wfMessage( 'donate_interface-donor-first_name' )->inLanguage( $language )->text(),
			],
			'last_name' => [
				'nodename' => 'input',
				'placeholder' => wfMessage( 'donate_interface-donor-last_name' )->inLanguage( $language )->text(),
			],
			'informationsharing' => [
				'nodename' => 'p',
				'innerhtmlmatches' => '~' . wfMessage( 'donate_interface-informationsharing', '.*' )->inLanguage( $language )->text() . '~',
			],
			'state_province' => [
				'nodename' => 'select',
				'selected' => 'SK',
			],
			'postal_code' => [
				'nodename' => 'input',
				'value' => $init['postal_code'],
			],
			'country' => [
				'nodename' => 'input',
				'value' => 'CA',
			],
		];

		$this->verifyFormOutput( 'GlobalCollectGateway', $init, $assertNodes, true );
	}

	/**
	 * Test that we show an email opt-in checkbox for Great Britain
	 */
	public function testGCFormLoadGB() {
		$init = $this->getDonorTestData( 'GB' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['ffname'] = 'cc-vmad';

		$assertNodes = [
			'opt_in_yes' => [
				'nodename' => 'input',
			],
			'opt_in_no' => [
				'nodename' => 'input',
			],
		];

		$this->verifyFormOutput( 'GlobalCollectGateway', $init, $assertNodes, true );
	}

	/**
	 * Test that we don't show an email opt-in checkbox for Great Britain if the value
	 * is given on the querystring
	 */
	public function testGCFormLoadGBOptInOnQuery() {
		$init = $this->getDonorTestData( 'GB' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['ffname'] = 'cc-vmad';
		$init['opt_in'] = '1';

		$assertNodes = [
			'opt_in_yes' => 'gone',
			'opt_in_no' => 'gone',
		];

		$this->verifyFormOutput( 'GlobalCollectGateway', $init, $assertNodes, true );
	}

	/**
	 * Test that we DO show an email opt-in checkbox for Great Britain when the value
	 * was posted.
	 */
	public function testGCFormLoadGBOptInOnPost() {
		$init = $this->getDonorTestData( 'GB' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['ffname'] = 'cc-vmad';
		$init['opt_in'] = '0';

		$assertNodes = [
			'opt_in_yes' => [
				'nodename' => 'input',
			],
			'opt_in_no' => [
				'nodename' => 'input',
			],
		];

		$this->verifyFormOutput( 'GlobalCollectGateway', $init, $assertNodes, true, null, true );
	}
}
