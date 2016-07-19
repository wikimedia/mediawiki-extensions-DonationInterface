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
			'wgGlobalCollectGatewayEnabled' => true,
			'wgDonationInterfaceAllowedHtmlForms' => array(
				'cc-vmad' => array(
					'gateway' => 'globalcollect',
					'payment_methods' => array('cc' => array( 'visa', 'mc', 'amex', 'discover' )),
					'countries' => array(
						'+' => $vmad_countries,
					),
				),
				'cc-vmaj' => array(
					'gateway' => 'globalcollect',
					'payment_methods' => array('cc' => array( 'visa', 'mc', 'amex', 'jcb' )),
					'countries' => array(
						'+' => $vmaj_countries,
					),
				),
				'cc-vma' => array(
					'gateway' => 'globalcollect',
					'payment_methods' => array('cc' => array( 'visa', 'mc', 'amex' )),
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
					'gateway' => 'globalcollect',
					'countries' => array(
						'+' => array( 'AT', 'BE', 'CH', 'DE' ),
						'-' => 'GB'
					),
					'currencies' => array( '+' => 'EUR' ),
					'payment_methods' => array('rtbt' => 'rtbt_sofortuberweisung'),
				),
			),
		) );
	}

	public function testGCFormLoad() {
		$init = $this->getDonorTestData( 'US' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['ffname'] = 'cc-vmad';

		$assertNodes = array (
			'submethod-mc' => array (
				'nodename' => 'input'
			),
			'selected-amount' => array (
				'nodename' => 'span',
				'innerhtmlmatches' => '/^\s*' .
					str_replace( '$', '\$',
						Amount::format( 1.55, 'USD', $init['language'] . '_' . $init['country'] )
					).
					'\s*$/',
			),
			'state' => array (
				'nodename' => 'select',
				'selected' => 'CA',
			),
		);

		$this->verifyFormOutput( 'TestingGlobalCollectGateway', $init, $assertNodes, true );
	}

	function testGCFormLoad_FR() {
		$init = $this->getDonorTestData( 'FR' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['ffname'] = 'cc-vmaj';

		$assertNodes = array (
			'selected-amount' => array (
				'nodename' => 'span',
				'innerhtmlmatches' => '/^\s*' .
					Amount::format( 1.55, 'EUR', $init['language'] . '_' . $init['country'] ) .
					'\s*$/',
			),
			'fname' => array (
				'nodename' => 'input',
				'value' => 'Prénom',
			),
			'lname' => array (
				'nodename' => 'input',
				'value' => 'Nom',
			),
			'country' => array (
				'nodename' => 'input',
				'value' => 'FR',
			),
		);

		$this->verifyFormOutput( 'TestingGlobalCollectGateway', $init, $assertNodes, true );
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

		$assertNodes = array (
			'selected-amount' => array (
				'nodename' => 'span',
				'innerhtmlmatches' => '/^\s*' .
					Amount::format( 1.55, 'EUR', $init['language'] . '_' . $init['country'] ) .
					'\s*$/',
			),
			'fname' => array (
				'nodename' => 'input',
				'placeholder' => wfMessage( 'donate_interface-donor-fname')->inLanguage( 'it' )->text(),
			),
			'lname' => array (
				'nodename' => 'input',
				'placeholder' => wfMessage( 'donate_interface-donor-lname')->inLanguage( 'it' )->text(),
			),
			'informationsharing' => array (
				'nodename' => 'p',
				'innerhtml' => wfMessage( 'donate_interface-informationsharing', '.*' )->inLanguage( 'it' )->text(),
			),
			'country' => array (
				'nodename' => 'input',
				'value' => 'IT',
			),
		);

		$this->verifyFormOutput( 'TestingGlobalCollectGateway', $init, $assertNodes, true );
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

		$assertNodes = array (
			'selected-amount' => array (
				'nodename' => 'span',
				'innerhtmlmatches' => '/^\s*' .
					Amount::format( 1.55, 'EUR', $init['language'] . '_' . $init['country'] ) .
					'\s*$/',
			),
			'fname' => array (
				'nodename' => 'input',
				'placeholder' => wfMessage( 'donate_interface-donor-fname')->inLanguage( $language )->text(),
			),
			'lname' => array (
				'nodename' => 'input',
				'placeholder' => wfMessage( 'donate_interface-donor-lname')->inLanguage( $language )->text(),
			),
			'informationsharing' => array (
				'nodename' => 'p',
				'innerhtml' => wfMessage( 'donate_interface-informationsharing', '.*' )->inLanguage( $language )->text(),
			),
			'country' => array (
				'nodename' => 'input',
				'value' => 'BE',
			),
		);

		$this->verifyFormOutput( 'TestingGlobalCollectGateway', $init, $assertNodes, true );
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
		$locale = $language . '_CA';

		$assertNodes = array (
			'selected-amount' => array (
				'nodename' => 'span',
				'innerhtmlmatches' => '/^\s*' .
					str_replace( '$', '\$',
						Amount::format( 1.55, 'CAD', $init['language'] . '_' . $init['country'] )
					) .
					'\s*$/',
			),
			'fname' => array (
				'nodename' => 'input',
				'placeholder' => wfMessage( 'donate_interface-donor-fname')->inLanguage( $language )->text(),
			),
			'lname' => array (
				'nodename' => 'input',
				'placeholder' => wfMessage( 'donate_interface-donor-lname')->inLanguage( $language )->text(),
			),
			'informationsharing' => array (
				'nodename' => 'p',
				'innerhtml' => wfMessage( 'donate_interface-informationsharing', '.*' )->inLanguage( $language )->text(),
			),
			'state' => array (
				'nodename' => 'select',
				'selected' => 'SK',
			),
			'zip' => array (
				'nodename' => 'input',
				'value' => $init['zip'],
			),
			'country' => array (
				'nodename' => 'input',
				'value' => 'CA',
			),
		);

		$this->verifyFormOutput( 'TestingGlobalCollectGateway', $init, $assertNodes, true );
	}
}
