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
 * @see DonationInterfaceTestCase
 */
require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'DonationInterfaceTestCase.php';

/**
 * @group Fundraising
 * @group DonationInterface
 * @group FormChooser
 */
class DonationInterface_FormChooserTestCase extends DonationInterfaceTestCase {

	/**
	 *
	 */
	public function __construct(){
		$adapterclass = TESTS_ADAPTER_DEFAULT;
		$this->testAdapterClass = $adapterclass;

		parent::__construct();
		self::setupMoreForms();
	}

	public static function setupMoreForms() {
		global $wgDonationInterfaceAllowedHtmlForms, $wgDonationInterfaceHtmlFormDir,
		$wgGlobalCollectGatewayHtmlFormDir, $wgPaypalGatewayHtmlFormDir,
		$wgAmazonGatewayHtmlFormDir, $wgDonationInterfaceFormDirs;

		$form_dirs = array (
			'default' => $wgDonationInterfaceHtmlFormDir,
			'gc' => $wgGlobalCollectGatewayHtmlFormDir,
			'paypal' => $wgPaypalGatewayHtmlFormDir,
			'amazon' => $wgAmazonGatewayHtmlFormDir,
		);

		$moreForms = array ( );
		$moreForms['cc-vmad'] = array (
			'file' => $form_dirs['gc'] . '/cc/cc-vmad.html',
			'gateway' => 'globalcollect',
			'payment_methods' => array ( 'cc' => array ( 'visa', 'mc', 'amex', 'discover' ) ),
			'countries' => array (
				'+' => array ( 'US', ),
			),
		);

		$moreForms['cc-vmaj'] = array (
			'file' => $form_dirs['gc'] . '/cc/cc-vmaj.html',
			'gateway' => 'globalcollect',
			'payment_methods' => array ( 'cc' => array ( 'visa', 'mc', 'amex', 'jcb' ) ),
			'countries' => array (
				'+' => array ( 'AD', 'AT', 'AU', 'BE', 'BH', 'DE', 'EC', 'ES', 'FI', 'FR', 'GB',
					'GF', 'GR', 'HK', 'IE', 'IT', 'JP', 'KR', 'LU', 'MY', 'NL', 'PR',
					'PT', 'SG', 'SI', 'SK', 'TH', 'TW', ),
			),
		);

		$moreForms['cc-vma'] = array (
			'file' => $form_dirs['gc'] . '/cc/cc-vma.html',
			'gateway' => 'globalcollect',
			'payment_methods' => array ( 'cc' => array ( 'visa', 'mc', 'amex' ) ),
			'countries' => array (
				// Array merge with cc-vmaj as fallback in case 'j' goes down
				// Array merge with cc-vmad as fallback in case 'd' goes down
				'+' => array_merge(
					$moreForms['cc-vmaj']['countries']['+'], $moreForms['cc-vmad']['countries']['+'], array ( 'AE', 'AL', 'AN', 'AR', 'BG', 'CA', 'CH', 'CN', 'CR', 'CY', 'CZ', 'DK',
					'DZ', 'EE', 'EG', 'JO', 'KE', 'HR', 'HU', 'IL', 'KW', 'KZ', 'LB', 'LI',
					'LK', 'LT', 'LV', 'MA', 'MT', 'NO', 'NZ', 'OM', 'PK', 'PL', 'QA', 'RO',
					'RU', 'SA', 'SE', 'TN', 'TR', 'UA', )
				)
			),
		);

		$moreForms['cc'] = array (
			'file' => $form_dirs['gc'] . '/cc/cc.html',
			'gateway' => 'globalcollect',
			'payment_methods' => array ( 'cc' => 'ALL' ),
			'countries' => array ( '-' => 'VN' )
		);

		$moreForms['paypal'] = array (
			'file' => $form_dirs['paypal'] . '/paypal.html',
			'gateway' => 'paypal',
			'payment_methods' => array ( 'paypal' => 'ALL' ),
		);

		$wgDonationInterfaceAllowedHtmlForms = array_merge( $wgDonationInterfaceAllowedHtmlForms, $moreForms );
		$wgDonationInterfaceFormDirs = $form_dirs;
	}

	function testGetOneValidForm_CC_SpecificCountry() {
		$tests = array (
			0 => array (
				'country' => 'US',
				'payment_method' => 'cc',
				'currency' => 'USD',
				'expected' => 'cc-vmad'
			),
			1 => array (
				'country' => 'DK',
				'payment_method' => 'cc',
				'currency' => 'DKK',
				'expected' => 'cc-vma'
			),
		);

		foreach ( $tests as $testno => $data ) {
			$form = GatewayFormChooser::getOneValidForm( $data['country'], $data['currency'], $data['payment_method'] );
			$this->assertEquals( $data['expected'], $form, "$form is not the preferred option for " . $data['payment_method'] . ' in ' . $data['country'] );
		}
	}

}


