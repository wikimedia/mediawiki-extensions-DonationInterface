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
 * @author Mark Holmquist <mtraceur@member.fsf.org>
 */

/**
 * Only here so we can run unit tests.
 */
class TestGatewayForm extends Gateway_Form {
	public function getForm() {
		return '';
	}
}

/**
 * @group Fundraising
 * @group Splunge
 * @group Gateways
 * @group DonationInterface
 * @author Mark Holmquist <mtraceur@member.fsf.org>
 */
class DonationInterface_Gateway_FormTest extends DonationInterfaceTestCase {
	protected $adapter;
	protected $form;

	protected function setUp() {
		$this->adapter = new TestingGlobalCollectAdapter();
		$this->form = new TestGatewayForm( $this->adapter );
		parent::setUp();
	}

	/**
	 * @covers Gateway_Form::__construct
	 */
	public function testConstruct() {
		$this->assertThat(
			$this->form,
			$this->isInstanceOf( 'TestGatewayForm' )
		);
	}

	/**
	 * @covers Gateway_Form::generateDonationFooter
	 */
	public function testGenerateDonationFooter() {
		global $wgExtensionAssetsPath;

		$expected  = '<div class="payflow-cc-form-section" id="payflowpro_gateway-donate-addl-info">';
		$expected .= 	'<div id="payflowpro_gateway-donate-addl-info-secure-logos">';
		$expected .=		'<p class="">';
		$expected .=			'<img src="' . $wgExtensionAssetsPath . '/DonationInterface/gateway_forms/includes/rapidssl_ssl_certificate-nonanimated.png">';
		$expected .=		'</p>';
		$expected .=	'</div>';
		$expected .=	'<div id="payflowpro_gateway-donate-addl-info-text">';
		$expected .=		'<p class="">';
		$expected .=			wfMessage( 'donate_interface-otherways-short' )->text();
		$expected .=		'</p>';
		$expected .=		'<p class="">';
		$expected .=			wfMessage( 'donate_interface-credit-storage-processing' )->text();
		$expected .=		'</p>';
		$expected .=		'<p class="">';
		$expected .=			wfMessage( 'donate_interface-question-comment' )->text();
		$expected .=		'</p>';
		$expected .=	'</div>';
		$expected .= '</div>';

		$this->assertEquals(
			$expected,
			$this->form->generateDonationFooter()
		);
	}

	/**
	 * @covers Gateway_Form::generateCountryDropdown
	 */
	public function testGenerateCountryDropdown() {
		// Most of this is taken from the method itself - we tear out some things
		// If it was straight HTML, we'd have an insanely long test file, and I don't
		// really feel like dealing with that mess.
		$country_options = '';

		// create a new array of countries with potentially translated country names for alphabetizing later
		foreach ( GatewayPage::getCountries() as $iso_value => $full_name ) {
			$countries[$iso_value] = wfMessage( 'donate_interface-country-dropdown-' . $iso_value )->text();
		}

		// alphabetically sort the country names
		asort( $countries, SORT_STRING );

		// generate a dropdown option for each country
		foreach ( $countries as $iso_value => $full_name ) {
			$country_options .= Xml::option( $full_name, $iso_value );
		}

		// build the actual select
		$expected = Xml::openElement(
			'select',
			array(
				'name' => 'country',
				'id' => 'country'
			) );
		$expected .= Xml::option( wfMessage( 'donate_interface-select-country' )->text(), '', false );
		$expected .= $country_options;
		$expected .= Xml::closeElement( 'select' );

		$this->assertEquals(
			$expected,
			$this->form->generateCountryDropdown()
		);
	}

	/**
	 * @covers Gateway_Form::generateCardDropdown
	 */
	public function testGenerateCardDropdown() {
		$expected = (
			'<select name="card" id="card">' .
				'<option value="visa">' .
					wfMessage( 'donate_interface-card-name-visa' )->text() .
				'</option>' .
				'<option value="mc">' .
					wfMessage( 'donate_interface-card-name-mc' )->text() .
				'</option>' .
				'<option value="amex">' .
					wfMessage( 'donate_interface-card-name-amex' )->text() .
				'</option>' .
				'<option value="discover">' .
					wfMessage( 'donate_interface-card-name-discover' )->text() .
				'</option>' .
			'</select>'
		);

		$this->assertEquals(
			$expected,
			$this->form->generateCardDropdown()
		);
	}

	/**
	 * @covers Gateway_Form::generateExpiryMonthDropdown
	 */
	public function testGenerateExpiryMonthDropdown() {
		global $wgLang;

		$expected = '<select name="mos" id="expiration">';

		foreach ( range( 1, 12 ) as $mon ) {
			// Rawr, I'm a monstr!
			$monstr = str_pad( $mon, 2, '0', STR_PAD_LEFT );
			$expected .= '<option value="' . $monstr . '">';
			$expected .= wfMessage( 'donate_interface-month', $mon, $wgLang->getMonthName( $mon ) )->text();
			$expected .= '</option>';
		}

		$expected .= '</select>';

		$this->assertEquals(
			$expected,
			$this->form->generateExpiryMonthDropdown()
		);
	}

	/**
	 * @covers Gateway_Form::generateExpiryYearDropdown
	 */
	public function testGenerateExpiryYearDropdown() {
		$expected = '<select name="year" id="year">';
		$start = date( 'Y' );

		foreach ( range( $start, $start + 10 ) as $year ) {
			$expected .= '<option value="' . $year . '">';
			$expected .= $year;
			$expected .= '</option>';
		}

		$expected .= '</select>';

		$this->assertEquals(
			$expected,
			$this->form->generateExpiryYearDropdown()
		);
	}

	/**
	 * @covers Gateway_Form::generateStateDropdown
	 */
	public function testGenerateStateDropdown() {
		$states = StateAbbreviations::statesMenuXML();
		$expected = '<select name="state" id="state">';

		foreach ( $states as $val => $state ) {
			$expected .= '<option value="' . $val;
			$expected .= '">';
			$expected .= wfMessage( 'donate_interface-state-dropdown-' . $val )->text();
			$expected .= '</option>';
		}

		$expected .= '</select>';

		$this->assertEquals(
			$expected,
			$this->form->generateStateDropdown()
		);
	}

	/**
	 * @covers Gateway_Form::generateCurrencyDropdown
	 */
	public function testGenerateCurrencyDropdown(){
		$currencies = array_flip( $this->form->gateway->getCurrencies() );

		$dom_thingy = new DOMDocument();

		$dom_thingy->encoding = 'UTF-8';
		$dom_thingy->loadHTML( '<?xml encoding="UTF-8">' . $this->form->generateCurrencyDropdown() );

		$select_node = $dom_thingy->getElementById( 'input_currency_code' );
		$this->assertEquals( 'currency_code', $select_node->getAttribute( 'name' ) );
		
		foreach ( $select_node->childNodes as $option ) {
			$currency = $option->getAttribute( 'value' );
			$this->assertNotNull( $currencies[$currency], 'Currency node generated for non-existent ' . $currency );
			$msg = wfMessage( 'donate_interface-' . $currency );
			if ( ! $msg->inContentLanguage()->isBlank() ) {
				$this->assertEquals( $msg, $option->nodeValue, 'Option text wrong for currency ' . $currency );
			}
			if ( $currency === 'USD' ) {
				$this->assertNotNull( $option->getAttribute( 'selected' ) );
			}
			unset( $currencies[$currency] );
		}
		$this->assertEmpty( $currencies, 'Did not generate options for all currencies!' );
	}
}
