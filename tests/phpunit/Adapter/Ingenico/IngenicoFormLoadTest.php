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
 * @group Ingenico
 */
class IngenicoFormLoadTest extends BaseIngenicoTestCase {

	public function testIngenicoFormLoad() {
		$init = $this->getDonorTestData( 'US' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['ffname'] = 'cc-vmad';
		$init['gateway'] = 'ingenico';

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
			'state_province' => array (
				'nodename' => 'select',
				'selected' => 'CA',
			),
		);

		$this->verifyFormOutput( 'IngenicoGateway', $init, $assertNodes, true );
	}

	function testIngenicoFormLoad_FR() {
		$init = $this->getDonorTestData( 'FR' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['ffname'] = 'cc-vmaj';
		$init['gateway'] = 'ingenico';

		$assertNodes = array (
			'selected-amount' => array (
				'nodename' => 'span',
				'innerhtmlmatches' => '/^\s*' .
					Amount::format( 1.55, 'EUR', $init['language'] . '_' . $init['country'] ) .
					'\s*$/',
			),
			'first_name' => array (
				'nodename' => 'input',
				'value' => 'Prénom',
			),
			'last_name' => array (
				'nodename' => 'input',
				'value' => 'Nom',
			),
			'country' => array (
				'nodename' => 'input',
				'value' => 'FR',
			),
		);

		$this->verifyFormOutput( 'IngenicoGateway', $init, $assertNodes, true );
	}

	/**
	 * Ensure that form loads for Italy
	 */
	public function testIngenicoFormLoad_IT() {
		$init = $this->getDonorTestData( 'IT' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['ffname'] = 'cc-vmaj';
		$init['gateway'] = 'ingenico';

		$assertNodes = array (
			'selected-amount' => array (
				'nodename' => 'span',
				'innerhtmlmatches' => '/^\s*' .
					Amount::format( 1.55, 'EUR', $init['language'] . '_' . $init['country'] ) .
					'\s*$/',
			),
			'first_name' => array (
				'nodename' => 'input',
				'placeholder' => wfMessage( 'donate_interface-donor-first_name')->inLanguage( 'it' )->text(),
			),
			'last_name' => array (
				'nodename' => 'input',
				'placeholder' => wfMessage( 'donate_interface-donor-last_name')->inLanguage( 'it' )->text(),
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

		$this->verifyFormOutput( 'IngenicoGateway', $init, $assertNodes, true );
	}

	/**
	 * Make sure Belgian form loads in all of that country's supported languages
	 * @dataProvider belgiumLanguageProvider
	 */
	public function testIngenicoFormLoad_BE( $language ) {
		$init = $this->getDonorTestData( 'BE' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['ffname'] = 'cc-vmaj';
		$init['language'] = $language;
		$init['gateway'] = 'ingenico';

		$assertNodes = array (
			'selected-amount' => array (
				'nodename' => 'span',
				'innerhtmlmatches' => '/^\s*' .
					Amount::format( 1.55, 'EUR', $init['language'] . '_' . $init['country'] ) .
					'\s*$/',
			),
			'first_name' => array (
				'nodename' => 'input',
				'placeholder' => wfMessage( 'donate_interface-donor-first_name')->inLanguage( $language )->text(),
			),
			'last_name' => array (
				'nodename' => 'input',
				'placeholder' => wfMessage( 'donate_interface-donor-last_name')->inLanguage( $language )->text(),
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

		$this->verifyFormOutput( 'IngenicoGateway', $init, $assertNodes, true );
	}

	/**
	 * Make sure Canadian CC form loads in English and French
	 * @dataProvider canadaLanguageProvider
	 */
	public function testIngenicoFormLoad_CA( $language ) {
		$init = $this->getDonorTestData( 'CA' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['ffname'] = 'cc-vma';
		$init['language'] = $language;
		$init['gateway'] = 'ingenico';

		$assertNodes = array (
			'selected-amount' => array (
				'nodename' => 'span',
				'innerhtmlmatches' => '/^\s*' .
					str_replace( '$', '\$',
						Amount::format( 1.55, 'CAD', $init['language'] . '_' . $init['country'] )
					) .
					'\s*$/',
			),
			'first_name' => array (
				'nodename' => 'input',
				'placeholder' => wfMessage( 'donate_interface-donor-first_name')->inLanguage( $language )->text(),
			),
			'last_name' => array (
				'nodename' => 'input',
				'placeholder' => wfMessage( 'donate_interface-donor-last_name')->inLanguage( $language )->text(),
			),
			'informationsharing' => array (
				'nodename' => 'p',
				'innerhtml' => wfMessage( 'donate_interface-informationsharing', '.*' )->inLanguage( $language )->text(),
			),
			'state_province' => array (
				'nodename' => 'select',
				'selected' => 'SK',
			),
			'postal_code' => array (
				'nodename' => 'input',
				'value' => $init['postal_code'],
			),
			'country' => array (
				'nodename' => 'input',
				'value' => 'CA',
			),
		);

		$this->verifyFormOutput( 'IngenicoGateway', $init, $assertNodes, true );
	}
}
