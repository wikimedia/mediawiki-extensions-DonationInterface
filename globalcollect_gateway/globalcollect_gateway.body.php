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
 * GlobalCollectGateway
 *
 */
class GlobalCollectGateway extends GatewayPage {

	protected $gatewayIdentifier = GlobalCollectAdapter::IDENTIFIER;

	/**
	 * Show the special page
	 *
	 * @todo
	 * - Finish error handling
	 */
	protected function handleRequest() {
		$this->getOutput()->allowClickjacking();
		// TODO: remove conditional when we have a dedicated error render
		// page and move addModule to Mustache#getResources
		if( $this->adapter->getFormClass() === 'Gateway_Form_Mustache' ) {
			$this->getOutput()->addModules( 'ext.donationinterface.ingenico.scripts' );
		}
		$this->handleDonationRequest();
	}

	protected function renderResponse( PaymentResult $result ) {
		// FIXME: This workaround can be deleted once we convert
		// these two result pages to render using normal templates.
		if ( $result->getForm() === 'end-bt' ) {
			$this->displayBankTransferInformation();
		} elseif ( $result->getForm() === 'end-obt' ) {
			$this->displayOnlineBankTransferInformation();
		} else {
			parent::renderResponse( $result );
		}
	}

	/**
	 * Display information for bank transfer
	 *
	 * @deprecated
	 */
	protected function displayBankTransferInformation() {
		$data = $this->adapter->getTransactionData();

		$return = '';
		$fields = array(
			'ACCOUNTHOLDER'			=> array('translation' => 'donate_interface-bt-account_holder', ),
			'BANKNAME'				=> array('translation' => 'donate_interface-dd-bank_name', ),
			'BANKACCOUNTNUMBER'		=> array('translation' => 'donate_interface-bt-bank_account_number', ),
			'CITY'					=> array('translation' => 'donate_interface-donor-city', ),
			'COUNTRYDESCRIPTION'	=> array('translation' => 'donate_interface-bt-country_description', ),
			'IBAN'					=> array('translation' => 'donate_interface-dd-iban', ),
			'PAYMENTREFERENCE'		=> array('translation' => 'donate_interface-bt-payment_reference', ),
			'SWIFTCODE'				=> array('translation' => 'donate_interface-bt-swift_code', ),
			'SPECIALID'				=> array('translation' => 'donate_interface-bt-special_id', ),
		);

		$id = 'bank_transfer_information';

		$return .= Xml::openElement( 'div', array( 'id' => $id ) ); // $id
		$return .= Xml::tags( 'h2', array(), $this->msg( 'donate_interface-bt-information' )->escaped() );
		$return .= Xml::openElement( 'table', array( 'id' => $id . '_table', 'style' => 'width:600px; margin-left:auto; margin-right:auto;' ) );

		foreach ( $fields as $field => $meta ) {
			if ( isset( $data[ $field ] ) ) {
				$return .= Xml::openElement( 'tr', array() );
				$return .= Xml::tags( 'td', array( 'style' => 'text-align:right; font-weight:bold; padding-right:0.5em;' ), $this->msg( $meta['translation'] )->escaped() );
				$return .= Xml::tags( 'td', array ('style' => 'padding-left:0.5em;'), htmlspecialchars( $data[$field], ENT_QUOTES ) );
				$return .= Xml::closeElement( 'tr' );
			}
		}
						
		$return .= Xml::openElement( 'tr', array() );
		$return .= Xml::tags( 'td', array( 'style' => 'font-weight:bold;' ), $this->msg( 'donate_interface-bank_transfer_message' )->escaped() );
		$return .= Xml::closeElement( 'tr' );

		$return .= Xml::closeElement( 'table' ); // close $id . '_table'

		$queryParams = array(
			'payment_method' => $this->adapter->getPaymentMethod(),
			'payment_submethod' => $this->adapter->getPaymentSubmethod(),
		);

		$encUrl = Xml::encodeJsVar(
			ResultPages::getThankYouPage( $this->adapter, $queryParams )
		);

		$link = Html::input('MyButton', $this->msg( 'donate_interface-bt-finished')->text(), 'button', array( 'onclick' => "window.location = $encUrl" ) );

		$return .= Xml::tags( 'p', array( 'style' => 'text-align:center;' ), $link );
		$return .= Xml::closeElement( 'div' );  // $id

		$this->getOutput()->addHTML( $return );
	}

	/**
	 * Display information for online bank transfer
	 *
	 * @deprecated
	 */
	protected function displayOnlineBankTransferInformation() {
		$data = $this->adapter->getTransactionData();

		$return = '';
		$fields = array(
			'CUSTOMERPAYMENTREFERENCE'	=> array('translation' => 'donate_interface-obt-customer_payment_reference', ),
			'BILLERID'					=> array('translation' => 'donate_interface-obt-biller_id', ),
		);

		$id = 'bank_transfer_information';

		$return .= Xml::openElement( 'div', array( 'id' => $id ) ); // $id
		$return .= Xml::tags( 'h2', array(), $this->msg( 'donate_interface-obt-information' )->escaped() );
		$return .= Xml::tags( 'p', array(), $this->msg( 'donate_interface-obt-customer_payment_reference_note' )->escaped() );
		$return .= Xml::openElement( 'table', array( 'id' => $id . '_table' ) );

		foreach ( $fields as $field => $meta ) {

			if ( isset( $data[ $field ] ) ) {
				$return .= Xml::openElement( 'tr', array() );
				$return .= Xml::tags( 'th', array(), $this->msg( $meta['translation'] )->escaped() );
				$return .= Xml::tags( 'td', array (), htmlspecialchars( $data[$field], ENT_QUOTES ) );
				$return .= Xml::closeElement( 'tr' );
			}
		}

		$return .= Xml::closeElement( 'table' ); // close $id . '_table'
		$return .= Xml::openElement( 'table' ); //open info table
		$return .= Xml::openElement( 'tr' );
		$return .= Xml::openElement ( 'td' );
		$return .= Xml::tags( 'p', array(), $this->msg( 'donate_interface-online_bank_transfer_message' )->escaped() );
		$return .= Xml::closeElement ( 'td' );
		$return .= Xml::closeElement ( 'tr' );
		$return .= Xml::openElement ( 'tr' );
		$return .= Xml::openElement( 'td' );
		$scriptPath = $this->getConfig()->get( 'ScriptPath' );
		$return .= Xml::element( 'img', array( 'src' => $scriptPath . "/extensions/DonationInterface/gateway_forms/includes/BPAY_Landscape_MONO.gif", 'style' => 'vertical-align:center; width:100px; margin-right: 1em;' ) );
		$return .= Xml::closeElement ( 'td' );
		$return .= Xml::openElement ( 'td' );
		$return .= Xml::tags( 'p',  array(), 'Contact your bank or financial institution <br /> to make this payment from your cheque, <br /> debit, or transaction account. <br /> More info: www.bpay.com.au ' );
		$return .= Xml::closeElement ( 'td' );
		$return .= Xml::closeElement ( 'tr' );
		$return .= Xml::openElement ( 'tr' );
		$return .= Xml::openElement ( 'td' );
		$return .= Xml::tags( 'p', array(), '<br /> &reg; Registered to BPAY Pty Ltd ABN 69 079 137 518');
		$return .= Xml::closeElement ( 'td' );
		$return .= Xml::closeElement ( 'tr' );
		$return .= Xml::closeElement ( 'table' ); //close info table

		$queryParams = array(
			'payment_method' => $this->adapter->getPaymentMethod(),
			'payment_submethod' => $this->adapter->getPaymentSubmethod(),
		);

		$encUrl = Xml::encodeJsVar(
			ResultPages::getThankYouPage( $this->adapter, $queryParams )
		);

		$link = Html::input('MyButton', 'finished', 'button', array( 'onclick' => "window.location = $encUrl" ) );

		$return .= Xml::tags( 'p', array(), $link );
		$return .= Xml::closeElement( 'div' );  // $id

		$this->getOutput()->addHTML( $return );
	}
}
