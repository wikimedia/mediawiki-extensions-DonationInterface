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
 * @since		r98249
 * @author Jeremy Postlethwaite <jpostlethwaite@wikimedia.org>
 */

/**
 * This form is designed for bank transfers
 */
class Gateway_Form_TwoStepAmount extends Gateway_Form {

	/**
	 * Initialize the form
	 *
	 */
	protected function init() {
		
		//TODO: This is pretty odd to do here. However, as this form is only 
		//being used for testing purposes, it's getting the update that goes 
		//along with yet another change in Form Class construction.
		$this->form_data['payment_method'] = empty($this->form_data['payment_method']) ? 'bt' : $this->form_data['payment_method'];
		$this->form_data['payment_submethod'] = empty($this->form_data['payment_submethod']) ? 'bt' : $this->form_data['payment_submethod'];
		
		$this->setPaymentMethod( $this->form_data['payment_method'] );
		$this->setPaymentSubmethod( $this->form_data['payment_submethod'] );
		
		$this->form_data['process'] = 'other';

		$this->loadResources();
	}

	/**
	 * Load form resources
	 */
	protected function loadResources() {
		
		$this->loadValidateJs();
	}

	/**
	 * Load extra javascript
	 */
	protected function loadValidateJs() {
		global $wgOut;
		$wgOut->addModules( 'gc.form.core.validate' );
		
		//$js = "\n" . '<script type="text/javascript">' . "validateForm( { validate: { address: true, amount: true, creditCard: false, email: true, name: true, }, payment_method: '" . $this->getPaymentMethod() . "', payment_submethod: '" . $this->getPaymentSubmethod() . "', formId: '" . $this->getFormId() . "' } );" . '</script>' . "\n";
		$js = "\n" . '<script type="text/javascript">'
			. "var validatePaymentForm = {
				formId: '" . $this->getFormId() . "',
				payment_method: '" . $this->getPaymentMethod() . "',
				payment_submethod: '" . $this->getPaymentSubmethod() . "',
			}"
		. '</script>' . "\n";
		$wgOut->addHeadItem( 'loadValidateJs', $js );
	}

	/**
	 * Required method for returning the full HTML for a form.
	 *
	 * @return string The entire form HTML
	 */
	public function getForm() {
		$form = $this->generateFormStart();
		$form .= $this->getCaptchaHTML();
		$form .= $this->generateFormEnd();
		return $form;
	}

	public $payment_methods = array();
	public $payment_submethods = array();

	/**
	 * Generate the payment information
	 *
	 * @todo
	 * - a large part of this method is for debugging and may need to be removed.
	 */
	public function generateFormPaymentInformation() {
		
		$form = '';
		
		// Payment debugging information
		$form .= Xml::openElement( 'div', array( 'id' => 'mw-payment-information' ) );
		
		$form .= Xml::tags( 'h2', array(), 'Payment debugging information' );
		
		$form .= Xml::openElement( 'ul', array() ); // open div#mw-payment-information ul
		$form .= Xml::tags( 'li', array(), 'payment_method: ' . $this->getPaymentMethod() );
		$form .= Xml::tags( 'li', array(), 'payment_submethod: ' . $this->getPaymentSubmethod() );
		
		if ( isset( $this->form_data['issuer_id'] ) ) {
			$form .= Xml::tags( 'li', array(), 'issuer_id: ' . $this->form_data['issuer_id'] );
		}
		
		$form .= Xml::closeElement( 'ul' ); // close div#mw-payment-information ul

		$form .= Xml::tags( 'h3', array(), 'Payment choices' );

		$form .= Xml::tags( 'h4', array(), 'Payment method:' );
		
		// Bank Transfers
		$this->payment_methods['bt'] = array(
			'label'	=> 'Bank transfer',
			'types'	=> array( 'bt', ),
			'validation' => array( 'creditCard' => false, )
			//'forms'	=> array( 'Gateway_Form_TwoStepAmount', ),
		);
		
		// Credit Cards
		//$this->payment_methods['cc'] = array(
		//	'label'	=> 'Credit Cards',
		//	'types'	=> array( '', 'visa', 'mc', 'amex', 'discover', 'maestro', 'solo', 'laser', 'jcb,', 'cb', ),
		//);
		
		// Direct Debit
		$this->payment_methods['dd'] = array(
			'label'	=> 'Direct Debit',
			'types'	=> array( 'dd_johnsen_nl', 'dd_johnsen_de', 'dd_johnsen_at', 'dd_johnsen_fr', 'dd_johnsen_gb', 'dd_johnsen_be', 'dd_johnsen_ch', 'dd_johnsen_it', 'dd_johnsen_es', ),
			'validation' => array( 'creditCard' => false, )
			//'forms'	=> array( 'Gateway_Form_TwoStepAmount', ),
		);
		
		// Real Time Bank Transfers
		$this->payment_methods['rtbt'] = array(
			'label'	=> 'Real time bank transfer',
			'types'	=> array( 'rtbt_ideal', 'rtbt_eps', 'rtbt_sofortuberweisung', 'rtbt_nordea_sweeden', 'rtbt_enets', ),
		);
		 
		// Ideal
		$this->payment_submethods['rtbt_ideal'] = array(
			'paymentproductid'	=> 809,
			'label'	=> 'Ideal',
			'group'	=> 'rtbt',
			'validation' => array(),
			'issuerids' => array( 
				771	=> 'RegioBank',
				161	=> 'Van Lanschot Bankiers',
				31	=> 'ABN AMRO',
				761	=> 'ASN Bank',
				21	=> 'Rabobank',
				511	=> 'Triodos Bank',
				721	=> 'ING',
				751	=> 'SNS Bank',
				91	=> 'Friesland Bank',
			)
		);
		// eps Online-Überweisung
		$this->payment_submethods['rtbt_eps'] = array(
			'paymentproductid'	=> 856,
			'label'	=> 'eps Online-Überweisung',
			'group'	=> 'rtbt',
			'validation' => array(),
			'issuerids' => array( 
				824	=> 'Bankhaus Spängler',
				825	=> 'Hypo Tirol Bank',
				822	=> 'NÖ HYPO',
				823	=> 'Voralberger HYPO',
				828	=> 'P.S.K.',
				829	=> 'Easy',
				826	=> 'Erste Bank und Sparkassen',
				827	=> 'BAWAG',
				820	=> 'Raifeissen',
				821	=> 'Volksbanken Gruppe',
				831	=> 'Sparda-Bank',
			)
		);
		
		$form .= Xml::openElement( 'ul', array() ); // open div#mw-payment-information ul
		//<a href="http://wikimediafoundation.org/wiki/Ways_to_Give/en">Other ways to give</a>
		
		foreach ( $this->payment_methods as $payment_method => $payment_methodMeta ) {

			$form .= Xml::openElement( 'li', array() );

				$form .= Xml::tags( 'span', array(), $payment_method );
	
				foreach ( $payment_methodMeta['types'] as $payment_submethod ) {
					$form .= ' - ' . Xml::tags( 'a', array('href'=>'?form_name=TwoStepAmount&payment_method=' . $payment_method . '&payment_submethod=' . $payment_submethod), $payment_submethod );
				}

			$form .= Xml::closeElement( 'li' );
		}
		
		$form .= Xml::closeElement( 'ul' ); // close div#mw-payment-information ul
		
		$form .= Xml::closeElement( 'div' ); // close div#mw-payment-information
		
		return $form;
	}
	
	/**
	 * Generate the issuerId for real time bank transfer
	 */
	public function generateFormIssuerIdDropdown() {
		
		$form = '';
		
		if ( !isset( $this->payment_submethods[ $this->getPaymentSubmethod() ] ) ) {
			
			// No issuer_id to load
			return $form;
		}

		$selectOptions = '';

		// generate dropdown of issuer_ids
		foreach ( $this->payment_submethods[ $this->getPaymentSubmethod() ]['issuerids'] as $issuer_id => $issuer_id_label ) {
			$selected = ( $this->form_data['issuer_id'] == $value ) ? true : false;
			//$selectOptions .= Xml::option( wfMsg( 'donate_interface-rtbt-' . $issuer_id ), $issuer_id_label, $selected );
			$selectOptions .= Xml::option( $issuer_id_label, $issuer_id_label, $selected );
		}
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label( wfMsg( 'donate_interface-donor-issuer_id' ), 'issuer_id' ) . '</td>';

		$form .= '<td>';
		$form .= Xml::openElement(
			'select',
			array(
				'name' => 'issuer_id',
				'id' => 'issuer_id',
				'onchange' => '',
			) );
		$form .= $selectOptions;
		$form .= Xml::closeElement( 'select' );

		$form .= '</td>';
		$form .= '</tr>';
		
		return $form;
	}
	
	/**
	 * Generate the first part of the form
	 */
	public function generateFormStart() {
		
		$form = '';
		
		//$form .= $this->generateBannerHeader();

		$form .= Xml::openElement( 'div', array( 'id' => 'mw-creditcard' ) );

		// provide a place at the top of the form for displaying general messages
		if ( $this->form_errors['general'] ) {
			$form .= Xml::openElement( 'div', array( 'id' => 'mw-payment-general-error' ) );
			if ( is_array( $this->form_errors['general'] ) ) {
				foreach ( $this->form_errors['general'] as $this->form_errors_msg ) {
					$form .= Xml::tags( 'p', array( 'class' => 'creditcard-error-msg' ), $this->form_errors_msg );
				}
			} else {
				$form .= Xml::tags( 'p', array( 'class' => 'creditcard-error-msg' ), $this->form_errors_msg );
			}
			$form .= Xml::closeElement( 'div' );
		}

		// add noscript tags for javascript disabled browsers
		$form .= $this->getNoScript();
		
		$form .= $this->generateFormPaymentInformation();

		// open form
		$form .= Xml::openElement( 'div', array( 'id' => 'mw-creditcard-form' ) );

		// Xml::element seems to convert html to htmlentities
		$form .= "<p class='creditcard-error-msg'>" . $this->form_errors['retryMsg'] . "</p>";
		$form .= Xml::openElement( 'form', array( 'id' => $this->getFormId(), 'name' => $this->getFormName(), 'method' => 'post', 'action' => $this->getNoCacheAction(), 'onsubmit' => '', 'autocomplete' => 'off' ) );

		$form .= Xml::openElement( 'div', array( 'id' => 'left-column', 'class' => 'payment-cc-form-section' ) );
		$form .= $this->generatePersonalContainer();
		$form .= $this->generatePaymentContainer();
		$form .= $this->generateFormSubmit();
		$form .= Xml::closeElement( 'div' ); // close div#left-column

		//$form .= Xml::openElement( 'div', array( 'id' => 'right-column', 'class' => 'payment-cc-form-section' ) );

		return $form;
	}

	public function generateFormSubmit() {
		// submit button
		$form = Xml::openElement( 'div', array( 'id' => 'payment_gateway-form-submit' ) );
		$form .= Xml::openElement( 'div', array( 'id' => 'mw-donate-submit-button' ) );
		$form .= Xml::element( 'input', array( 'class' => 'button-plain', 'value' => wfMsg( 'donate_interface-submit-button' ), 'type' => 'submit' ) );
		$form .= Xml::closeElement( 'div' ); // close div#mw-donate-submit-button
		$form .= Xml::closeElement( 'div' ); // close div#payment_gateway-form-submit
		return $form;
	}

	public function generateFormEnd() {
		$form = '';
		// add hidden fields
		$hidden_fields = $this->getHiddenFields();
		foreach ( $hidden_fields as $field => $value ) {
			$form .= Html::hidden( $field, $value );
		}

		$form .= Xml::closeElement( 'form' );
		$form .= Xml::closeElement( 'div' ); // close div#mw-creditcard-form
		$form .= $this->generateDonationFooter();
		$form .= Xml::closeElement( 'div' ); // div#close mw-creditcard
		return $form;
	}

	protected function generatePersonalContainer() {
		$form = '';
		$form .= Xml::openElement( 'div', array( 'id' => 'payment_gateway-personal-info' ) );                 ;
		//$form .= Xml::tags( 'h3', array( 'class' => 'payment-cc-form-header', 'id' => 'payment-cc-form-header-personal' ), wfMsg( 'donate_interface-cc-form-header-personal' ) );
		$form .= Xml::openElement( 'table', array( 'id' => 'payment-table-donor' ) );

		$form .= $this->generatePersonalFields();

		$form .= Xml::closeElement( 'table' ); // close table#payment-table-donor
		$form .= Xml::closeElement( 'div' ); // close div#payment_gateway-personal-info

		return $form;
	}

	protected function generatePersonalFields() {
		// first name
		$form = $this->getNameField();

		// country
		$form .= $this->getCountryField();

		// street
		$form .= $this->getStreetField();


		// city
		$form .= $this->getCityField();

		// state
		$form .= $this->getStateField();

		// zip
		$form .= $this->getZipField();

		// email
		$form .= $this->getEmailField();

		return $form;
	}

	protected function generatePaymentContainer() {
		$form = '';
		// credit card info
		$form .= Xml::openElement( 'div', array( 'id' => 'donation-payment-info' ) );
		//$form .= Xml::tags( 'h3', array( 'class' => 'payment-cc-form-header', 'id' => 'payment-cc-form-header-payment' ), wfMsg( 'donate_interface-cc-form-header-payment' ) );
		$form .= Xml::openElement( 'table', array( 'id' => 'donation-table-cc' ) );

		$form .= $this->generatePaymentFields();

		$form .= Xml::closeElement( 'table' ); // close table#payment-table-cc
		$form .= Xml::closeElement( 'div' ); // close div#payment_gateway-payment-info

		return $form;
	}

	protected function generatePaymentFields() {
		// amount
		$form = '<tr>';
		$form .= '<td colspan="2"><span class="donation-error-msg">' . $this->form_errors['invalidamount'] . '</span></td>';
		$form .= '</tr>';
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label( wfMsg( 'donate_interface-donor-amount' ), 'amount' ) . '</td>';
		$form .= '<td>' . Xml::input( 'amount', '7', $this->form_data['amount'], array( 'class' => 'required', 'type' => 'text', 'maxlength' => '10', 'id' => 'amount' ) ) .
		' ' . $this->generateCurrencyDropdown( array( 'showCardsOnCurrencyChange' => false, ) ) . '</td>';
		$form .= '</tr>';

		$form .= $this->generateFormIssuerIdDropdown();

		return $form;
	}
}
