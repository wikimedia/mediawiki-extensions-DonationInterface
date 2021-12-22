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
		$fields = [
			'ACCOUNTHOLDER'			=> [ 'translation' => 'donate_interface-bt-account_holder', ],
			'BANKNAME'				=> [ 'translation' => 'donate_interface-dd-bank_name', ],
			'BANKACCOUNTNUMBER'		=> [ 'translation' => 'donate_interface-bt-bank_account_number', ],
			'CITY'					=> [ 'translation' => 'donate_interface-donor-city', ],
			'COUNTRYDESCRIPTION'	=> [ 'translation' => 'donate_interface-bt-country_description', ],
			'IBAN'					=> [ 'translation' => 'donate_interface-dd-iban', ],
			'PAYMENTREFERENCE'		=> [ 'translation' => 'donate_interface-bt-payment_reference', ],
			'SWIFTCODE'				=> [ 'translation' => 'donate_interface-bt-swift_code', ],
			'SPECIALID'				=> [ 'translation' => 'donate_interface-bt-special_id', ],
		];

		$id = 'bank_transfer_information';

		$return .= Xml::openElement( 'div', [ 'id' => $id ] ); // $id
		$return .= Xml::tags( 'h2', [], $this->msg( 'donate_interface-bt-information' )->escaped() );
		$return .= Xml::openElement( 'table', [ 'id' => $id . '_table', 'style' => 'width:600px; margin-left:auto; margin-right:auto;' ] );

		foreach ( $fields as $field => $meta ) {
			if ( isset( $data[ $field ] ) ) {
				$return .= Xml::openElement( 'tr', [] );
				$return .= Xml::tags( 'td', [ 'style' => 'text-align:right; font-weight:bold; padding-right:0.5em;' ], $this->msg( $meta['translation'] )->escaped() );
				$return .= Xml::tags( 'td', [ 'style' => 'padding-left:0.5em;' ], htmlspecialchars( $data[$field], ENT_QUOTES ) );
				$return .= Xml::closeElement( 'tr' );
			}
		}

		$return .= Xml::openElement( 'tr', [] );
		$email = $this->adapter->getGlobal( 'ProblemsEmail' );
		$return .= Xml::tags( 'td', [ 'style' => 'font-weight:bold;' ], $this->msg( 'donate_interface-bank_transfer_message', $email )->escaped() );
		$return .= Xml::closeElement( 'tr' );

		$return .= Xml::closeElement( 'table' ); // close $id . '_table'

		$queryParams = [
			'payment_method' => $this->adapter->getPaymentMethod(),
			'payment_submethod' => $this->adapter->getPaymentSubmethod(),
		];

		$encUrl = Xml::encodeJsVar(
			ResultPages::getThankYouPage( $this->adapter, $queryParams )
		);

		$link = Html::input( 'MyButton', $this->msg( 'donate_interface-bt-finished' )->text(), 'button', [ 'onclick' => "window.location = $encUrl" ] );

		$return .= Xml::tags( 'p', [ 'style' => 'text-align:center;' ], $link );
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
		$fields = [
			'CUSTOMERPAYMENTREFERENCE'	=> [ 'translation' => 'donate_interface-obt-customer_payment_reference', ],
			'BILLERID'					=> [ 'translation' => 'donate_interface-obt-biller_id', ],
		];

		$id = 'bank_transfer_information';

		$return .= Xml::openElement( 'div', [ 'id' => $id ] ); // $id
		$return .= Xml::tags( 'h2', [], $this->msg( 'donate_interface-obt-information' )->escaped() );
		$return .= Xml::tags( 'p', [], $this->msg( 'donate_interface-obt-customer_payment_reference_note' )->escaped() );
		$return .= Xml::openElement( 'table', [ 'id' => $id . '_table' ] );

		foreach ( $fields as $field => $meta ) {
			if ( isset( $data[ $field ] ) ) {
				$return .= Xml::openElement( 'tr', [] );
				$return .= Xml::tags( 'th', [], $this->msg( $meta['translation'] )->escaped() );
				$return .= Xml::tags( 'td', [], htmlspecialchars( $data[$field], ENT_QUOTES ) );
				$return .= Xml::closeElement( 'tr' );
			}
		}

		$return .= Xml::closeElement( 'table' ); // close $id . '_table'
		$return .= Xml::openElement( 'table' ); // open info table
		$return .= Xml::openElement( 'tr' );
		$return .= Xml::openElement( 'td' );
		$email = $this->adapter->getGlobal( 'ProblemsEmail' );
		$return .= Xml::tags( 'p', [], $this->msg( 'donate_interface-online_bank_transfer_message', $email )->escaped() );
		$return .= Xml::closeElement( 'td' );
		$return .= Xml::closeElement( 'tr' );
		$return .= Xml::openElement( 'tr' );
		$return .= Xml::openElement( 'td' );
		$scriptPath = $this->getConfig()->get( 'ScriptPath' );
		$return .= Xml::element( 'img', [
			'src' => $scriptPath . "/extensions/DonationInterface/gateway_forms/includes/BPAY_Landscape_MONO.gif",
			'style' => 'vertical-align:center; width:100px; margin-right: 1em;'
		] );
		$return .= Xml::closeElement( 'td' );
		$return .= Xml::openElement( 'td' );
		$return .= Xml::tags( 'p', [], 'Contact your bank or financial institution <br /> ' .
			'to make this payment from your cheque, <br /> debit, or transaction account. <br /> ' .
			'More info: www.bpay.com.au ' );
		$return .= Xml::closeElement( 'td' );
		$return .= Xml::closeElement( 'tr' );
		$return .= Xml::openElement( 'tr' );
		$return .= Xml::openElement( 'td' );
		$return .= Xml::tags( 'p', [], '<br /> &reg; Registered to BPAY Pty Ltd ABN 69 079 137 518' );
		$return .= Xml::closeElement( 'td' );
		$return .= Xml::closeElement( 'tr' );
		$return .= Xml::closeElement( 'table' ); // close info table

		$queryParams = [
			'payment_method' => $this->adapter->getPaymentMethod(),
			'payment_submethod' => $this->adapter->getPaymentSubmethod(),
		];

		$encUrl = Xml::encodeJsVar(
			ResultPages::getThankYouPage( $this->adapter, $queryParams )
		);

		$link = Html::input( 'MyButton', 'finished', 'button', [ 'onclick' => "window.location = $encUrl" ] );

		$return .= Xml::tags( 'p', [], $link );
		$return .= Xml::closeElement( 'div' );  // $id

		$this->getOutput()->addHTML( $return );
	}
}
