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
	 * - Finish error handling
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
		

		// dispatch forms/handling
		if ( $this->adapter->checkTokens() ) {	

			//TODO: Get rid of $data out here completely, by putting this logic inside the adapter somewhere. 
			//All we seem to be doing with it now, is internal adapter logic outside of the adapter. 
			$data = $this->adapter->getDisplayData();

			if ( $this->adapter->posted ) {
				
				// The form was submitted and the payment method has been set
				/*
				 * The $payment_method should default to false.
				 *
				 * An invalid $payment_method will cause an error.
				 */

				$payment_method = ( isset( $data['payment_method'] ) && !empty( $data['payment_method'] ) ) ? $data['payment_method'] : 'cc';
				$payment_submethod = ( isset( $data['payment_submethod'] ) && !empty( $data['payment_submethod'] ) ) ? $data['payment_submethod'] : '';
		
				$payment_submethodMeta = $this->adapter->getPaymentSubmethodMeta( $payment_submethod, array( 'log' => true, ) );
				
				// Check form for errors
				$form_errors = $this->validateForm( $this->errors, $payment_submethodMeta['validation'] );
				
				// If there were errors, redisplay form, otherwise proceed to next step
				if ( $form_errors ) {

					$this->displayForm( $this->errors );
				} else { // The submitted form data is valid, so process it
					// allow any external validators to have their way with the data
					// Execute the proper transaction code:

					if ( $payment_method == 'cc' ) {

						$this->adapter->do_transaction( 'INSERT_ORDERWITHPAYMENT' );
						
						// Display an iframe for credit cards
						if ( $this->executeIframeForCreditCard() ) {
							
							// Nothing left to process
							return;
						}
					}
					elseif ( $payment_method == 'rtbt' ) {

						$this->adapter->do_transaction( 'INSERT_ORDERWITHPAYMENT' );

						$formAction = $this->adapter->getTransactionDataFormAction();
						
						// Redirect to the bank
						if ( !empty( $formAction ) ) {
							return $wgOut->redirect( $formAction );
						}

					}
					elseif ( $payment_method == 'dd' ) {

						$this->adapter->do_transaction( 'DO_BANKVALIDATION' );

						if ( $this->adapter->getTransactionStatus() ) {

							$this->adapter->do_transaction( 'INSERT_ORDERWITHPAYMENT' );
						}
						
					}
					else {
						$this->adapter->do_transaction( 'INSERT_ORDERWITHPAYMENT' );
					}
			
					return $this->resultHandler();

				}
			} else {
				// Display form
				
				// See GlobalCollectAdapter::stage_returnto()
				$oid = $wgRequest->getText( 'order_id' );
				if ( $oid ) {
					$this->adapter->do_transaction( 'GET_ORDERSTATUS' );
					$this->displayResultsForDebug();
				}
				
				// If the result of the previous transaction was failure, set the retry message.
				if ( $data && array_key_exists( 'response', $data ) && $data['response'] == 'failure' ) {
					$this->errors['retryMsg'] = wfMsg( 'php-response-declined' );
				}
				
				$this->displayForm( $this->errors );
			}
		} else {
			if ( !$this->adapter->isCaching() ) {
				// if we're not caching, there's a token mismatch
				$this->errors['general']['token-mismatch'] = wfMsg( 'donate_interface-token-mismatch' );
			}
			$this->displayForm( $this->errors );
		}
	}

	/**
	 * Execute iframe for credit card
	 *
	 * @return boolean	Returns true if formaction exists for iframe.
	 */
	protected function executeIframeForCreditCard() {

		global $wgOut;

		$formAction = $this->adapter->getTransactionDataFormAction();
		
		if ( $formAction ) {

			$paymentFrame = Xml::openElement( 'iframe', array(
					'id' => 'globalcollectframe',
					'name' => 'globalcollectframe',
					'width' => '680',
					'height' => '300',
					'frameborder' => '0',
					'style' => 'display:block;',
					'src' => $formAction,
				)
			);
			$paymentFrame .= Xml::closeElement( 'iframe' );

			$wgOut->addHTML( $paymentFrame );
			
			return true;
		}

		return false;
	}

}

// end class
