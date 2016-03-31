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
		$fields[] = 'address';
		$fields[] = 'payment_submethod';
		return $fields;
	}

	function defineAccountInfo() {
		$this->accountInfo = array(
			'merchantAccount' => $this->account_config[ 'AccountName' ],
			'skinCode' => $this->account_config[ 'SkinCode' ],
			'hashSecret' => $this->account_config[ 'SharedSecret' ],
		);
	}

	function defineErrorMap() {
		$this->error_map = array(
			'internal-0000' => 'donate_interface-processing-error', // Failed failed pre-process checks.
		);
	}

	function defineStagedVars() {
		$this->staged_vars = array(
			'amount',
			'full_name',
			'street',
			'zip',
			'risk_score',
			'hpp_signature' // Keep this at the end - it depends on the rest
		);
	}

	public function defineDataTransformers() {
		$this->data_transformers = parent::getCoreDataTransformers();
	}

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

	function setGatewayDefaults() {}

	/**
	 * Define transactions
	 */
	function defineTransactions() {
		
		$this->transactions = array( );

		$this->transactions[ 'donate' ] = array(
			'request' => array(
				'allowedMethods',
				'billingAddress.street',
				'billingAddress.city',
				'billingAddress.postalCode',
				'billingAddress.stateOrProvince',
				'billingAddress.country',
				'billingAddressType',
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
			),
			'values' => array(
				'allowedMethods' => implode( ',', $this->getAllowedPaymentMethods() ),
				'billingAddressType' => 2, // hide billing UI fields
				'merchantAccount' => $this->accountInfo[ 'merchantAccount' ],
				'sessionValidity' => date( 'c', strtotime( '+2 days' ) ),
				'shipBeforeDate' => date( 'Y-M-d', strtotime( '+2 days' ) ),
				'skinCode' => $this->accountInfo[ 'skinCode' ],
				//'shopperLocale' => language _ country
			),
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
		$this->transaction_response = new PaymentTransactionResponse();

		if ( $this->transaction_option( 'iframe' ) ) {
			// slightly different than other gateways' iframe method,
			// we don't have to make the round-trip, instead just
			// stage the variables and return the iframe url in formaction.

			switch ( $transaction ) {
				case 'donate':
					$formaction = $this->url . '/hpp/pay.shtml';
					$this->runAntifraudHooks();
					// Add the risk score to our data. This will also trigger
					// staging, placing the risk score in the constructed URL
					// as 'offset' for use in processor-side fraud filters.
					$this->addRequestData( array ( 'risk_score' => $this->risk_score ) );
					if ( $this->getValidationAction() != 'process' ) {
						// copied from base class.
						$this->logger->info( "Failed pre-process checks for transaction type $transaction." );
						$message = $this->getErrorMapByCodeAndTranslate( 'internal-0000' );
						$this->transaction_response->setCommunicationStatus( false );
						$this->transaction_response->setMessage( $message );
						$this->transaction_response->setErrors( array(
							'internal-0000' => array(
								'message' => $message,
								'debugInfo' => "Failed pre-process checks for transaction type $transaction.",
								'logLevel' => LogLevel::INFO
							),
						) );
						break;
					}
					$requestParams = $this->buildRequestParams();

					$this->transaction_response->setData( array(
						'FORMACTION' => $formaction,
						'gateway_params' => $requestParams,
					) );
					$this->logger->info( "launching external iframe request: " . print_r( $requestParams, true )
					);
					$this->logPaymentDetails();
					$this->setLimboMessage( 'pending' );
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
	protected function getStompTransaction() {
		$transaction = parent::getStompTransaction();
		$transaction['risk_score'] = $this->risk_score;
		return $transaction;
	}

	//@TODO: Determine why this is being overloaded here.
	//This looks like a var-renamed copy of the parent. :[
	protected function buildRequestParams() {
		// Look up the request structure for our current transaction type in the transactions array
		$structure = $this->getTransactionRequestStructure();
		if ( !is_array( $structure ) ) {
			return FALSE;
		}

		$queryvals = array();
		foreach ( $structure as $fieldname ) {
			$fieldvalue = $this->getTransactionSpecificValue( $fieldname );
			if ( $fieldvalue !== '' && $fieldvalue !== false ) {
				$queryvals[$fieldname] = $fieldvalue;
			}
		}
		return $queryvals;
	}

	/**
	 * For Adyen, we only call this on the donor's return to the ResultSwitcher
	 * @param array $response GET/POST params from request
	 * @throws ResponseProcessingException
	 */
	public function processResponse( $response ) {
		// Always called outside do_transaction, so just make a new response object
		$this->transaction_response = new PaymentTransactionResponse();
		if ( empty( $response ) ) {
			$this->logger->info( "No response from gateway" );
			throw new ResponseProcessingException(
				'No response from gateway',
				ResponseCodes::NO_RESPONSE
			);
		}
		$this->logger->info( "Processing user return data: " . print_r( $response, TRUE ) );

		if ( !$this->checkResponseSignature( $response ) ) {
			$this->logger->info( "Bad signature in response" );
			throw new ResponseProcessingException(
				'Bad signature in response',
				ResponseCodes::BAD_SIGNATURE
			);
		}
		$this->logger->debug( 'Good signature' );

		// Overwrite the order ID we have with the return data, in case the
		// donor opened a second window.
		$orderId = $response['merchantReference'];
		$this->addRequestData( array(
			'order_id' => $orderId,
		) );
		$gateway_txn_id = isset( $response['pspReference'] ) ? $response['pspReference'] : '';
		$this->transaction_response->setGatewayTransactionId( $gateway_txn_id );

		$result_code = isset( $response['authResult'] ) ? $response['authResult'] : '';
		if ( $result_code == 'PENDING' || $result_code == 'AUTHORISED' ) {
			// Both of these are listed as pending because we have to submit a capture
			// request on 'AUTHORIZATION' ipn message receipt.
			$this->logger->info( "User came back as pending or authorised, placing in payments-init queue" );
			$this->finalizeInternalStatus( FinalStatus::PENDING );
		}
		else {
			$this->deleteLimboMessage( 'pending' );
			$this->finalizeInternalStatus( FinalStatus::FAILED );
			$this->logger->info( "Negative response from gateway. Full response: " . print_r( $response, TRUE ) );
		}
		$this->runPostProcessHooks();
	}

	/**
	 * Overriding this function because we're queueing our pending message
	 * before we redirect the user, so we don't need to send another one
	 * when doStompTransaction is called from runPostProcessHooks.
	 */
	protected function doStompTransaction() {}

	/**
	 * TODO do we want to stage the country code for language variants?
	protected function stage_language( $type = 'request' ) {
	*/

	protected function stage_risk_score() {
		//This isn't smart enough to grab a new value here;
		//Late-arriving values have to trigger a restage via addData or
		//this will always equal the risk_score at the time of object
		//construction. Still need the formatting, though.
		if ( isset( $this->unstaged_data['risk_score'] ) ) {
			$this->staged_data['risk_score'] = ( string ) round( $this->unstaged_data['risk_score'] );
		}
	}

	protected function stage_hpp_signature() {
		$params = $this->buildRequestParams();
		if ( $params ) {
			$this->staged_data['hpp_signature'] = $this->calculateSignature( $params );
		}
	}

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

		$calculated_sig = $this->calculateSignature( $requestVars );
		return ( $calculated_sig === $requestVars[ 'merchantSig' ] );
	}

	protected function calculateSignature( $values ) {
		$ignoredKeys = array(
			'sig',
			'merchantSig',
			'title',
			'liberated',
		);

		foreach ( array_keys( $values ) as $key ) {
			if ( substr( $key, 0, 7 ) === 'ignore.' || in_array( $key, $ignoredKeys ) ) {
				unset( $values[$key] );
			} else {
				// escape colons and backslashes
				$values[$key] = str_replace( ':', '\\:', str_replace( '\\', '\\\\', $values[$key] ) );
			}
		}

		ksort( $values, SORT_STRING );

		$joined = implode( ':', array_merge( array_keys( $values ), array_values( $values ) ) );
		return base64_encode(
			hash_hmac( 'sha256', $joined, pack( "H*", $this->accountInfo[ 'hashSecret' ] ), true )
		);
	}
}
