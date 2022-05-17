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
		$init['gateway'] = 'ingenico';

		$assertNodes = [
			'submethod-mc' => [
				'nodename' => 'input'
			],
			'selected-amount' => [
				'nodename' => 'span',
				'innerhtmlmatches' => '/^\s*' .
					str_replace( '$', '\$',
						Amount::format( 1.55, 'USD', $init['language'] . '_' . $init['country'] )
					) .
					'\s*$/',
			],
			'state_province' => [
				'nodename' => 'select',
				'selected' => 'CA',
			],
		];

		$this->verifyFormOutput( 'IngenicoGateway', $init, $assertNodes, true );
	}

	public function testIngenicoFormLoad_FR() {
		$init = $this->getDonorTestData( 'FR' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';
		$init['gateway'] = 'ingenico';

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

		$this->verifyFormOutput( 'IngenicoGateway', $init, $assertNodes, true );
	}

	/**
	 * Ensure that form loads for Italy
	 */
	public function testIngenicoFormLoad_IT() {
		$init = $this->getDonorTestData( 'IT' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';
		$init['gateway'] = 'ingenico';

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
		$init['language'] = $language;
		$init['gateway'] = 'ingenico';

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
		$init['language'] = $language;
		$init['gateway'] = 'ingenico';

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

		$this->verifyFormOutput( 'IngenicoGateway', $init, $assertNodes, true );
	}
}
