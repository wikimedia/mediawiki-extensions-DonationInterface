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
use Psr\Log\LogLevel;

/**
 * AdyenAdapter
 *
 */
class AdyenAdapter extends GatewayAdapter {
	const GATEWAY_NAME = 'Adyen';
	const IDENTIFIER = 'adyen';
	const GLOBAL_PREFIX = 'wgAdyenGateway';

	public function getCommunicationType() {
		return 'namevalue';
	}

	public function getRequiredFields() {
		$fields = parent::getRequiredFields();
		$fields[] = 'payment_submethod';
		return $fields;
	}

	function defineAccountInfo() {
		$this->accountInfo = array(
			'merchantAccount' => $this->account_config[ 'AccountName' ],
			'skinCode' => $this->account_config[ 'SkinCode' ],
		);
	}

	// FIXME: That's not what ReturnValueMap is for!
	// Unused?
	function defineReturnValueMap() {
		$this->return_value_map = array(
			'authResult' => 'result',
			'merchantReference' => 'order_id',
			'merchantReturnData' => 'return_data',
			'pspReference' => 'gateway_txn_id',
			'skinCode' => 'skin_code',
		);
	}

	/**
	 * Sets up the $order_id_meta array.
	 * Should contain the following keys/values:
	 * 'alt_locations' => array( $dataset_name, $dataset_key ) //ordered
	 * 'type' => numeric, or alphanumeric
	 * 'length' => $max_charlen
	 */
	public function defineOrderIDMeta() {
		$this->order_id_meta = array (
			'alt_locations' => array ( 'request' => 'merchantReference' ),
			'ct_id' => TRUE,
			'generate' => TRUE,
		);
	}

	/**
	 * Define transactions
	 */
	function defineTransactions() {

		$this->transactions = array( );

		$requestFields = array(
				'allowedMethods',
				'card.cardHolderName',
				'currencyCode',
				'merchantAccount',
				'merchantReference',
				'merchantSig',
				'offset',
				'paymentAmount',
				'sessionValidity',
				'shipBeforeDate',
				'skinCode',
				'shopperLocale',
				'shopperEmail',
				// TODO more fields we might want to send to Adyen
				//'shopperReference',
				//'recurringContract',
				//'blockedMethods',
				//'shopperStatement',
				//'merchantReturnData',
				//'deliveryAddressType',
		);

		// Add address fields for countries that use them.
		$addressFields = array (
			'billingAddress.street',
			'billingAddress.city',
			'billingAddress.postalCode',
			'billingAddress.stateOrProvince',
			'billingAddress.country',
			'billingAddressType',
			'billingAddress.houseNumberOrName',
		);

		if ( in_array( 'street_address', $this->getRequiredFields() ) )  {
			$requestFields = array_merge( $requestFields, $addressFields );
		}

		$this->transactions['donate'] = array(
			'request' => $requestFields,
			'values' => array(
				'allowedMethods' => implode( ',', $this->getAllowedPaymentMethods() ),
				'billingAddressType' => 2, // hide billing UI fields
				'merchantAccount' => $this->accountInfo[ 'merchantAccount' ],
				'sessionValidity' => date( 'c', strtotime( '+2 days' ) ),
				'shipBeforeDate' => date( 'Y-M-d', strtotime( '+2 days' ) ),
				'skinCode' => $this->accountInfo[ 'skinCode' ],
				//'shopperLocale' => language _ country
			),
			'check_required' => TRUE,
			'iframe' => TRUE,
		);
	}

	protected function getAllowedPaymentMethods() {
		return array(
			'card',
		);
	}

	function getBasedir() {
		return __DIR__;
	}

	function doPayment() {
		return PaymentResult::fromResults(
			$this->do_transaction( 'donate' ),
			$this->getFinalStatus()
		);
	}

	/**
	 * FIXME: I can't help but feel like it's bad that the parent's do_transaction
	 * is never used at all.
	 */
	function do_transaction( $transaction ) {
		// If this is not our first call, get a fresh order ID
		// FIXME: This is repeated in three places. Maybe always regenerate in incrementSequenceNumber?
		if ( $this->session_getData( 'sequence' ) ) {
			$this->regenerateOrderID();
		}
		$this->session_addDonorData();
		$this->setCurrentTransaction( $transaction );

		$this->validate();
		if ( !$this->validatedOK() ){
			//If the data didn't validate okay, prevent all data transmissions.
			$response = $this->getFailedValidationResponse();
			$this->logger->info( "Failed Validation. Aborting $transaction " . print_r( $this->errors, true ) );
			return $response;
		}
		$this->transaction_response = new PaymentTransactionResponse();
		if ( $this->transaction_option( 'iframe' ) ) {
			// slightly different than other gateways' iframe method,
			// we don't have to make the round-trip, instead just
			// stage the variables and return the iframe url in formaction.

			switch ( $transaction ) {
				case 'donate':
					$formaction = $this->getProcessorUrl() . '/hpp/pay.shtml';
					// Run Session Velocity here because we don't cURL anything
					$this->runSessionVelocityFilter();
					// FIXME: should skip next step if session velocity rejected
					$this->runAntifraudFilters();
					// Add the risk score to our data. This will also trigger
					// staging, placing the risk score in the constructed URL
					// as 'offset' for use in processor-side fraud filters.
					// Whatever the risk score, we're going to show them the
					// card entry iframe. If it's sorta-fraudy, the listener
					// will leave it for manual review. If it's hella fraudy
					// the listener will cancel it.
					$this->addRequestData( array ( 'risk_score' => $this->risk_score ) );

					$requestParams = $this->buildRequestParams();

					$this->transaction_response->setData( array(
						'FORMACTION' => $formaction,
						'gateway_params' => $requestParams,
					) );
					$this->logger->info( "launching external iframe request: " . print_r( $requestParams, true )
					);
					$this->logPaymentDetails();
					$this->sendPendingMessage();
					break;
			}
		}
		// Ensure next attempt gets a unique order ID
		$this->incrementSequenceNumber();
		return $this->transaction_response;
	}

	/**
	 * Add risk score to the message we send to the pending queue.
	 * The IPN listener will combine this with scores based on CVV and AVS
	 * results returned with the authorization notification and determine
	 * whether to capture the payment or leave it for manual review.
	 * @return array
	 */
	protected function getQueueDonationMessage() {
		$transaction = parent::getQueueDonationMessage();
		$transaction['risk_score'] = $this->risk_score;
		return $transaction;
	}

	/**
	 * @param array $requestValues GET/POST params from request
	 * @throws ResponseProcessingException
	 * @return PaymentResult
	 */
	public function processDonorReturn( $requestValues ) {
		// Always called outside do_transaction, so just make a new response object
		$this->transaction_response = new PaymentTransactionResponse();
		if ( empty( $requestValues ) ) {
			$this->logger->info( "No response from gateway" );
			throw new ResponseProcessingException(
				'No response from gateway',
				ResponseCodes::NO_RESPONSE
			);
		}
		$this->logger->info( "Processing user return data: " . print_r( $requestValues, TRUE ) );

		if ( !$this->checkResponseSignature( $requestValues ) ) {
			$this->logger->info( "Bad signature in response" );
			throw new ResponseProcessingException(
				'Bad signature in response',
				ResponseCodes::BAD_SIGNATURE
			);
		}
		$this->logger->debug( 'Good signature' );

		// Overwrite the order ID we have with the return data, in case the
		// donor opened a second window.
		$orderId = $requestValues['merchantReference'];
		$this->addRequestData( array(
			'order_id' => $orderId,
		) );
		$gateway_txn_id = isset( $requestValues['pspReference'] ) ? $requestValues['pspReference'] : '';
		$this->transaction_response->setGatewayTransactionId( $gateway_txn_id );

		$result_code = isset( $requestValues['authResult'] ) ? $requestValues['authResult'] : '';
		$paymentResult = null;
		if ( $result_code == 'PENDING' || $result_code == 'AUTHORISED' ) {
			// Both of these are listed as pending because we have to submit a capture
			// request on 'AUTHORIZATION' ipn message receipt.
			// We should still have risk scores in the session from before we
			// showed the iframe. What did we decide then? Show a fail page if
			// the donation was fishy enough that our listener isn't going to
			// auto-capture it, so as not to tell carders the auth worked.
			// FIXME: need to keep action ranges in sync between DI and listener.
			$action = Gateway_Extras_CustomFilters::determineStoredAction( $this );
			if ( $action === 'process' ) {
				$this->logger->info( "User came back as pending or authorised, placing in payments-init queue" );
				$this->finalizeInternalStatus( FinalStatus::PENDING );
				$paymentResult = PaymentResult::newSuccess();
			} else {
				$this->logger->info(
					"User came back authorized but with action $action. " .
					"Showing a fail page, but leaving details in case of manual capture."
				);
				$this->finalizeInternalStatus( FinalStatus::FAILED );
				$paymentResult = PaymentResult::newFailure();
			}
		}
		else {
			$this->finalizeInternalStatus( FinalStatus::FAILED );
			$paymentResult = PaymentResult::newFailure();
			$this->logger->info( "Negative response from gateway. Full response: " . print_r( $requestValues, TRUE ) );
		}
		$this->postProcessDonation();
		return $paymentResult;
	}

	/**
	 * Overriding this function because we're queueing our pending message
	 * before we redirect the user, so we don't need to send another one
	 * when doStompTransaction is called from postProcessDonation.
	 */
	protected function doStompTransaction() {}

	/**
	 * Overriding @see GatewayAdapter::getTransactionSpecificValue to strip
	 * newlines.
	 * @param string $gateway_field_name
	 * @param boolean $token
	 * @return mixed
	 */
	protected function getTransactionSpecificValue( $gateway_field_name, $token = false ) {
		$value = parent::getTransactionSpecificValue( $gateway_field_name, $token );
		return str_replace( '\n', '', $value );
	}

	function checkResponseSignature( $requestVars ) {
		if ( !isset( $requestVars[ 'merchantSig' ] ) ) {
			return false;
		}

		$calculated_sig = AdyenHostedSignature::calculateSignature(
			$this, $requestVars
		);
		return ( $calculated_sig === $requestVars[ 'merchantSig' ] );
	}

}
