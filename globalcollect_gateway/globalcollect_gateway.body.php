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
class GlobalCollectGateway extends GatewayForm {

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
	 * - Add transaction type handler
	 * - What should a failure on transaction_type issues do? log & message client 
	 * - Set up BANK_TRANSFER: Story #308
	 *
	 * @param $par Mixed: parameter passed to the page or null
	 */
	public function execute( $par ) {
		global $wgRequest, $wgOut, $wgExtensionAssetsPath;
		$CSSVersion = $this->adapter->getGlobal( 'CSSVersion' );

		$wgOut->allowClickjacking();

		$wgOut->addExtensionStyle(
			$wgExtensionAssetsPath . '/DonationInterface/gateway_forms/css/gateway.css?284' .
			$CSSVersion );

		// Hide unneeded interface elements
		$wgOut->addModules( 'donationInterface.skinOverride' );

		// Used to add gateway specific error messages.
		$gateway_id = $this->adapter->getIdentifier();

		$this->addErrorMessageScript();

		// Make the wiki logo not clickable.
		// @fixme can this be moved into the form generators?
		$js = <<<EOT
<script type="text/javascript">
jQuery(document).ready(function() {
	jQuery("div#p-logo a").attr("href","#");
});
</script>
EOT;
		$wgOut->addHeadItem( 'logolinkoverride', $js );

		$this->setHeaders();

		/**
		 *  handle PayPal redirection
		 *
		 *  if paypal redirection is enabled ($wgPayflowProGatewayPaypalURL must be defined)
		 *  and the PaypalRedirect form value must be true
		 */
		if ( $wgRequest->getText( 'PaypalRedirect', 0 ) ) {
			$this->paypalRedirect();
			return;
		}

		//TODO: This is short-circuiting what I really want to do here. 
		//so stop it. 
		$data = $this->adapter->getDisplayData();

		// dispatch forms/handling
		if ( $this->adapter->checkTokens() ) {
			if ( $this->adapter->posted ) {
				// The form was submitted and the payment method has been set
				$this->adapter->log( "Form posted and payment method set." );

				/* Commenting out because this is completely breaking Credit Card in GC.
				* Under usual circumstances, that would be an automatic revert, but 
				* there were no small number of clean places to do that.
				**/
				
//				/*
//				 * The $payment_method should default to false.
//				 *
//				 * An invalid $payment_method will cause an error.
//				 */
//				$payment_method = ( isset( $data['payment_method'] ) && !empty( $data['payment_method'] ) ) ? $data['payment_method'] : false;
//				$payment_submethod = ( isset( $data['payment_submethod'] ) && !empty( $data['payment_submethod'] ) ) ? $data['payment_submethod'] : false;
//		
//				$payment_submethodMeta = $this->adapter->getPaymentSubmethodMeta( $payment_submethod, array( 'log' => true, ) );
//				
//				// Check form for errors
//				$form_errors = $this->validateForm( $data, $this->errors, $payment_submethodMeta['validation'] );
				
				$form_errors = $this->validateForm( $data, $this->errors, array( 'address', 'amount', 'creditCard', 'email', 'name' ) );

				// If there were errors, redisplay form, otherwise proceed to next step
				if ( $form_errors ) {

					$this->displayForm( $data, $this->errors );
				} else { // The submitted form data is valid, so process it
					// allow any external validators to have their way with the data
					// Execute the proper transaction code:

					$result = $this->adapter->do_transaction( 'INSERT_ORDERWITHPAYMENT' );
			
					$this->displayResultsForDebug( $result );

					//if ( $payment_method == 'credit' ) {

					$this->executeIframeForCreditCard( $result );
					//}


					//TODO: add all the hooks back in. 
				}
			} else {
				// Display form for the first time
				$oid = $wgRequest->getText( 'order_id' );
				if ( $oid ) {
					$wgOut->addHTML( "<pre>CAME BACK FROM SOMETHING.</pre>" );
					$result = $this->adapter->do_transaction( 'GET_ORDERSTATUS' );
					$this->displayResultsForDebug( $result );
				}
				$this->adapter->log( "Not posted, or not processed. Showing the form for the first time." );
				$this->displayForm( $data, $this->errors );
			}
		} else {
			if ( !$this->adapter->isCache() ) {
				// if we're not caching, there's a token mismatch
				$this->errors['general']['token-mismatch'] = wfMsg( $gateway_id . '_gateway-token-mismatch' );
			}
			$this->displayForm( $data, $this->errors );
		}
	}

	/**
	 * Execute iframe for credit card
	 *
	 * @param array	$result	The result array from the gateway adapter
	 *
	 * @todo
	 * - this needs to be moved out of @see GlobalCollectGateway and into the adapter.
	 */
	public function executeIframeForCreditCard( $result ) {

		global $wgOut;

		if ( !empty( $result['data'] ) ) {

			if ( array_key_exists( 'FORMACTION', $result['data'] ) ) {
				$paymentFrame = Xml::openElement( 'iframe', array(
						'id' => 'globalcollectframe',
						'name' => 'globalcollectframe',
						'width' => '680',
						'height' => '300',
						'frameborder' => '0',
						'style' => 'display:block;',
						'src' => $result['data']['FORMACTION']
						)
				);
				$paymentFrame .= Xml::closeElement( 'iframe' );

				$wgOut->addHTML( $paymentFrame );
			}
		}
	}

	//TODO: Remember why the heck I decided to leave this here...
	//arguably, it's because it's slightly more "view" related, but... still, shouldn't you get stashed 
	//in the new GatewayForm class so we can override in chlidren if we feel like it? Odd. 
	function addErrorMessageScript() {
		global $wgOut;
		$gateway_id = $this->adapter->getIdentifier();

		$scriptVars = array(
			$gateway_id . 'GatewayErrorMsgJs' => wfMsg( $gateway_id . '_gateway-error-msg-js' ),
			$gateway_id . 'GatewayErrorMsgEmail' => wfMsg( $gateway_id . '_gateway-error-msg-email' ),
			$gateway_id . 'GatewayErrorMsgAmount' => wfMsg( $gateway_id . '_gateway-error-msg-amount' ),
			$gateway_id . 'GatewayErrorMsgEmailAdd' => wfMsg( $gateway_id . '_gateway-error-msg-emailAdd' ),
			$gateway_id . 'GatewayErrorMsgFname' => wfMsg( $gateway_id . '_gateway-error-msg-fname' ),
			$gateway_id . 'GatewayErrorMsgLname' => wfMsg( $gateway_id . '_gateway-error-msg-lname' ),
			$gateway_id . 'GatewayErrorMsgStreet' => wfMsg( $gateway_id . '_gateway-error-msg-street' ),
			$gateway_id . 'GatewayErrorMsgCity' => wfMsg( $gateway_id . '_gateway-error-msg-city' ),
			$gateway_id . 'GatewayErrorMsgState' => wfMsg( $gateway_id . '_gateway-error-msg-state' ),
			$gateway_id . 'GatewayErrorMsgZip' => wfMsg( $gateway_id . '_gateway-error-msg-zip' ),
			$gateway_id . 'GatewayErrorMsgCountry' => wfMsg( $gateway_id . '_gateway-error-msg-country' ),
			$gateway_id . 'GatewayErrorMsgCardType' => wfMsg( $gateway_id . '_gateway-error-msg-card_type' ),
			$gateway_id . 'GatewayErrorMsgCardNum' => wfMsg( $gateway_id . '_gateway-error-msg-card_num' ),
			$gateway_id . 'GatewayErrorMsgExpiration' => wfMsg( $gateway_id . '_gateway-error-msg-expiration' ),
			$gateway_id . 'GatewayErrorMsgCvv' => wfMsg( $gateway_id . '_gateway-error-msg-cvv' ),
			$gateway_id . 'GatewayCVVExplain' => wfMsg( $gateway_id . '_gateway-cvv-explain' ),
		);

		$wgOut->addScript( Skin::makeVariablesScript( $scriptVars ) );
	}

}

// end class
