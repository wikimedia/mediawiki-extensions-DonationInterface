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
	/**
	 * Constructor - set up the new special page
	 */
	public function __construct() {
		$this->adapter = new GlobalCollectAdapter();
		parent::__construct(); //the next layer up will know who we are.
	}

	/**
	 * Show the special page
	 *
	 * @todo
	 * - Finish error handling
	 */
	protected function handleRequest() {
		$this->getOutput()->allowClickjacking();

		$this->setHeaders();

		// dispatch forms/handling
		if ( $this->adapter->checkTokens() ) {
			if ( $this->adapter->posted ) {
				// The form was submitted and the payment method has been set
				$payment_method = $this->adapter->getPaymentMethod();

				if ( $payment_method === 'dd'
						and !$this->adapter->getPaymentSubmethod() ) {
					// Synthesize a submethod based on the country.
					$country_code = strtolower( $this->adapter->getData_Unstaged_Escaped( 'country' ) );
					$this->adapter->addRequestData( array(
						'payment_submethod' => "dd_{$country_code}",
					) );
				}

				// If there were errors, redisplay form, otherwise proceed to next step
				if ( $this->validateForm() ) {
					$this->displayForm();
				} else { // The submitted form data is valid, so process it
					// allow any external validators to have their way with the data
					// Execute the proper transaction code:
					
					switch ( $payment_method ){
						case 'cc': 
							// FIXME: we don't actually use this code path, it's done from gc.cc.js instead.

							$this->adapter->do_transaction( 'INSERT_ORDERWITHPAYMENT' );

							// Display an iframe for credit cards
							if ( $this->executeIframeForCreditCard() ) {
								$this->displayResultsForDebug();
								// Nothing left to process
								return;
							}
							break;
							
						case 'bt':
						case 'obt':
							$this->adapter->do_transaction( 'INSERT_ORDERWITHPAYMENT' );

							if ( in_array( $this->adapter->getFinalStatus(), $this->adapter->getGoToThankYouOn() ) ) {
								return $this->displayEndTransactionInfo( $payment_method );
							}
							break;
							
						case 'dd':
							$result = $this->adapter->do_transaction('Direct_Debit');
							break;
							
						case 'ew':
						case 'rtbt':
						case 'cash':
							$this->adapter->do_transaction( 'INSERT_ORDERWITHPAYMENT' );
							$formAction = $this->adapter->getTransactionDataFormAction();

							// Redirect to the bank
							if ( !empty( $formAction ) ) {
								return $this->getOutput()->redirect( $formAction );
							}
							break;
						
						default: 
							$this->adapter->do_transaction( 'INSERT_ORDERWITHPAYMENT' );
					}

					return $this->resultHandler();

				}
			} else {
				// Display form

				//TODO: NO.
				//This probably has something to do with the dumbass way that bt, rtbt, and dd were done.
				//
//				// See GlobalCollectAdapter::stage_returnto()
//				$oid = $this->getRequest()->getText( 'order_id' );
//				if ( $oid ) {
//					$this->adapter->do_transaction( 'GET_ORDERSTATUS' );
//					$this->displayResultsForDebug();
//				}
				//TODO: Get rid of $data out here completely, by putting this logic inside the adapter somewhere.
				//All we seem to be doing with it now, is internal adapter logic outside of the adapter.
				$data = $this->adapter->getData_Unstaged_Escaped();

				// If the result of the previous transaction was failure, set the retry message.
				if ( $data && array_key_exists( 'response', $data ) && $data['response'] == 'failure' ) {
					$error['retryMsg'] = $this->msg( 'php-response-declined' )->text();
					$this->adapter->addManualError( $error );
				}

				$this->displayForm();
			}
		} else { //token mismatch
			$error['general']['token-mismatch'] = $this->msg( 'donate_interface-token-mismatch' )->text();
			$this->adapter->addManualError( $error );
			$this->displayForm();
		}
	}

	/**
	 * Execute iframe for credit card
	 *
	 * @return boolean	Returns true if formaction exists for iframe.
	 */
	protected function executeIframeForCreditCard() {
		$formAction = $this->adapter->getTransactionDataFormAction();
		$mercid = $this->adapter->getMerchantID();

		$attrs = array(
			'id' => 'globalcollectframe',
			'name' => 'globalcollectframe',
			'width' => '680',
			'height' => '300'
		);

		if ( $formAction ) {
			if ( $mercid === 'test' ) {
				$paymentFrame = (
					Xml::openElement( 'div', $attrs ) .
					'<input type="button" id="globalcollect_gateway-fakesucceed"' .
					'value="' . $this->msg( 'globalcollect_gateway-fakesucceed' ) . '" />' .
					'<input type="button" id="globalcollect_gateway-fakefail"' .
					'value="' . $this->msg( 'globalcollect_gateway-fakefail' ) . '" />' .
					Xml::closeElement( 'div' )
				);
			} else {
				$attrs['frameborder'] = '0';
				$attrs['style'] = 'display:block;';
				$attrs['src'] = $formAction;
				$paymentFrame = Xml::openElement( 'iframe', $attrs );
				$paymentFrame .= Xml::closeElement( 'iframe' );
			}

			$this->getOutput()->addHTML( $paymentFrame );

			return true;
		}

		return false;
	}
	
	protected function displayEndTransactionInfo( $payment_method ){
		switch ( $payment_method ){
			case 'bt':
				return $this->displayBankTransferInformation();
				break;
			case 'obt':
				return $this->displayOnlineBankTransferInformation();
				break;
		}
	}

	/**
	 * Display information for bank transfer
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

		$queryString = '?payment_method=' . $this->adapter->getPaymentMethod() . '&payment_submethod=' . $this->adapter->getPaymentSubmethod();

		$encUrl = Xml::encodeJsVar( $this->adapter->getThankYouPage() . $queryString );

		$link = HTML::input('MyButton', $this->msg( 'donate_interface-bt-finished')->text(), 'button', array( 'onclick' => "window.location = $encUrl" ) );

		$return .= Xml::tags( 'p', array( 'style' => 'text-align:center;' ), $link );
		$return .= Xml::closeElement( 'div' );  // $id

		return $this->getOutput()->addHTML( $return );
	}

	/**
	 * Display information for online bank transfer
	 */
	protected function displayOnlineBankTransferInformation() {
		global $wgScriptPath;
		
		$data = $this->adapter->getTransactionData();

		$return = '';
		$fields = array(
			'CUSTOMERPAYMENTREFERENCE'	=> array('translation' => 'donate_interface-obt-customer_payment_reference', ),
			'BILLERID'					=> array('translation' => 'donate_interface-obt-biller_id', ),
		);

		$id = 'bank_transfer_information';

		$return .= Xml::openElement( 'div', array( 'id' => $id ) ); // $id
		$return .= Xml::tags( 'h2', array(), $this->msg( 'donate_interface-obt-information' )->escaped() );
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
		$return .= Xml::element( 'img', array( 'src' => $wgScriptPath . "/extensions/DonationInterface/gateway_forms/includes/BPAY_Landscape_MONO.gif", 'style' => 'vertical-align:center; width:100px; margin-right: 1em;' ) );
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

		$queryString = '?payment_method=' . $this->adapter->getPaymentMethod() . '&payment_submethod=' . $this->adapter->getPaymentSubmethod();

		$encUrl = Xml::encodeJsVar( $this->adapter->getThankYouPage() . $queryString );

		$link = HTML::input('MyButton', 'finished', 'button', array( 'onclick' => "window.location = $encUrl" ) );

		$return .= Xml::tags( 'p', array(), $link );
		$return .= Xml::closeElement( 'div' );  // $id

		return $this->getOutput()->addHTML( $return );
	}
}
