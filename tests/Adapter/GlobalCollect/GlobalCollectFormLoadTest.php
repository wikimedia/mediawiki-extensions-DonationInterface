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

	public function testGCFormLoad() {
		$init = $this->getDonorTestData( 'US' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['ffname'] = 'cc-vmad';

		$assertNodes = array (
			'cc-mc' => array (
				'nodename' => 'input'
			),
			'selected-amount' => array (
				'nodename' => 'span',
				'innerhtml' => '$1.55',
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
				'innerhtml' => '€1.55',
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
				'innerhtml' => '€1.55',
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
				'innerhtml' => '€1.55',
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
	 * Make sure Belgian form loads in all of that country's supported languages
	 * @dataProvider belgiumLanguageProvider
	 */
	public function testGlobalCollectSofortLoad_BE( $language ) {
		$init = $this->getDonorTestData( 'BE' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'rtbt';
		$init['payment_submethod'] = 'rtbt_sofortuberweisung';
		$init['ffname'] = 'rtbt-sofo';
		$init['language'] = $language;

		$assertNodes = array (
			'fname' => array (
				'nodename' => 'input',
				'placeholder' => wfMessage( 'donate_interface-donor-fname')->inLanguage( $language )->text(),
			),
			'lname' => array (
				'nodename' => 'input',
				'placeholder' => wfMessage( 'donate_interface-donor-lname')->inLanguage( $language )->text(),
			),
			'emailAdd' => array (
				'nodename' => 'input',
				'placeholder' => wfMessage( 'donate_interface-donor-email')->inLanguage( $language )->text(),
			),
			'informationsharing' => array (
				'nodename' => 'p',
				'innerhtml' => wfMessage( 'donate_interface-informationsharing', '.*' )->inLanguage( $language )->text(),
			),
			'country' => array (
				'nodename' => 'select',
				'selected' => 'BE',
			),
		);

		$this->verifyFormOutput( 'TestingGlobalCollectGateway', $init, $assertNodes, true );
	}

	/**
	 * Supported languages for Belgium
	 */
	public function belgiumLanguageProvider() {
		return array(
			array( 'nl' ),
			array( 'de' ),
			array( 'fr' ),
		);
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

		$assertNodes = array (
			'selected-amount' => array (
				'nodename' => 'span',
				'innerhtml' => '1.55 CAD',
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
