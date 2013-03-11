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
 * AdyenAdapter
 *
 */
class AdyenAdapter extends GatewayAdapter {
	const GATEWAY_NAME = 'Adyen';
	const IDENTIFIER = 'adyen';
	const COMMUNICATION_TYPE = 'namevalue';
	const GLOBAL_PREFIX = 'wgAdyenGateway';

	function defineAccountInfo() {
		$this->accountInfo = array(
			'merchantAccount' => $this->account_config[ 'AccountName' ],
			'skinCode' => $this->account_config[ 'SkinCode' ],
			'hashSecret' => $this->account_config[ 'SharedSecret' ],
		);
	}

	function defineDataConstraints() {
	}

	function defineErrorMap() {
		$this->error_map = array(
			'internal-0000' => 'donate_interface-processing-error', // Failed failed pre-process checks.
		);
	}

	function defineStagedVars() {
		$this->staged_vars = array(
			'amount',
			'street',
			'zip',
			'billing_signature',
			'hpp_signature',
		);
	}
	
	/**
	 * Define var_map
	 */
	function defineVarMap() {
		$this->var_map = array(
			'allowedMethods'	=> 'allowed_methods',
			'billingAddress.city' => 'city',
			'billingAddress.country' => 'country',
			'billingAddress.postalCode' => 'zip',
			'billingAddressSig'	=> 'billing_signature',
			'billingAddress.stateOrProvince' => 'state',
			'billingAddress.street' => 'street',
			'billingAddressType' => 'billing_address_type',
			'blockedMethods' => 'blocked_methods',
			'currencyCode'		=> 'currency_code',
			'deliveryAddressType' => 'delivery_address_type',
			'merchantAccount'	=> 'merchant_account',
			'merchantReference'	=> 'order_id',
			'merchantReturnData' => 'return_data',
			'merchantSig'		=> 'hpp_signature',
			'offset'			=> 'risk_score',
			'orderData'			=> 'order_data',
			'paymentAmount'		=> 'amount',
			'pspReference'		=> 'gateway_txn_id',
			'recurringContract' => 'recurring_type',
			'sessionValidity'	=> 'session_expiration',
			'shipBeforeDate'	=> 'expiration',
			'shopperEmail'		=> 'sent_email',
			'shopperLocale'		=> 'language',
			'shopperReference' => 'customer_id',
			'shopperStatement' => 'statement_template',
			'skinCode'			=> 'skin_code',
		);
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
				'billingAddressSig',
				'billingAddressType',
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
				// TODO more fields we might want to send to Adyen
				//'shopperEmail',
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

	/**
	 * Because GC has some processes that involve more than one do_transaction 
	 * chained together, we're catching those special ones in an overload and 
	 * letting the rest behave normally. 
	 */
	function do_transaction( $transaction ) {
		$this->setCurrentTransaction( $transaction );

		if ( $this->transaction_option( 'iframe' ) ) {
			// slightly different than other gateways' iframe method,
			// we don't have to make the round-trip, instead just
			// stage the variables and return the iframe url in formaction.

			switch ( $transaction ) {
				case 'donate':
					$formaction = $this->getGlobal( 'BaseURL' ) . '/hpp/pay.shtml';
					$this->runPreProcessHooks();
					if ( $this->getValidationAction() != 'process' ) {
						// copied from base class.
						self::log( "Failed pre-process checks for transaction type $transaction.", LOG_INFO );
						$this->transaction_results = array(
							'status' => false,
							'message' => $this->getErrorMapByCodeAndTranslate( 'internal-0000' ),
							'errors' => array(
								'internal-0000' => $this->getErrorMapByCodeAndTranslate( 'internal-0000' ),
							),
							'action' => $this->getValidationAction(),
						);
						break;
					}
					$this->stageData();

					$this->setTransactionResult(
						array( 'FORMACTION' => $formaction ),
						'data'
					);
					$this->setTransactionResult(
						$this->buildRequestParams(),
						'gateway_params'
					);
					$this->addDonorDataToSession();
					$this->doLimboStompTransaction();
					break;
			}
		}
		return $this->getTransactionAllResults();
	}

	function isResponse() {
		global $wgRequest;
		$authResult = $wgRequest->getVal( 'authResult' );
		return !empty( $authResult );
	}

	function getResponseStatus( $response ) {
	}

	function getResponseErrors( $response ) {
	}

	function getResponseData( $response ) {
	}

	function getCurrencies() {
		$currencies = array(
			//XXX
			'USD', // U.S. dollar
		);
		return $currencies;
	}

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

	function processResponse( $response = null, &$retryVars = null ) {
		if ( $response === NULL ) { // convert GET data
			$request_vars = $_GET;
			$log_prefix = $this->getLogMessagePrefix();

			if ( !$this->checkResponseSignature( $request_vars ) ) {
				self::log( $log_prefix . "Bad signature in reponse" );
				return 'BAD_SIGNATURE';
			}

			$oid = $this->unstaged_data[ 'order_id' ];

			$gateway_txn_id = isset( $request_vars[ 'pspReference' ] ) ? $request_vars[ 'pspReference' ] : '';

			$result_code = isset( $request_vars[ 'authResult' ] ) ? $request_vars[ 'authResult' ] : '';
			if ( $result_code == 'PENDING' ) {
				$this->setTransactionWMFStatus( 'pending' );
			}
			elseif ( $result_code == 'AUTHORISED' ) {
				$this->setTransactionWMFStatus( 'complete' );
			}
			else {
				self::log( $log_prefix . "Negative response from gateway. Full response: " . print_r( $request_vars, TRUE ) );
				$this->unsetAllSessionData();
				return 'UNKNOWN';
			}
			$this->setTransactionResult( $gateway_txn_id, 'gateway_txn_id' );
			$this->setTransactionResult( $this->getTransactionWMFStatus(), 'txn_message' );
			$this->runPostProcessHooks();
			$this->unsetAllSessionData();
			$this->doLimboStompTransaction( TRUE ); // add antimessage
			return null;
		}
		self::log( $log_prefix . "No response from gateway" );
		return 'NO_RESPONSE';
	}

	/**
	 * TODO do we want to stage the country code for language variants?
	protected function stage_language( $type = 'request' ) {
	*/

	/**
	 * Stage: amount
	 *
	 * For example: JPY 1000.05 get changed to 100005. This need to be 100000.
	 * For example: JPY 1000.95 get changed to 100095. This need to be 100000.
	 *
	 * @param string	$type	request|response
	 */
	protected function stage_amount( $type = 'request' ) {
		switch ( $type ) {
			case 'request':
				if ( !DataValidator::is_fractional_currency( $this->staged_data['currency_code'] ) ) {
					$this->staged_data['amount'] = floor( $this->staged_data['amount'] );
				}

				$this->staged_data['amount'] = $this->staged_data['amount'] * 100;
				break;

			case 'response':
				$this->staged_data['amount'] = $this->staged_data['amount'] / 100;
				break;
		}
	}

	protected function stage_risk_score( $type = 'request' ) {
		$this->staged_data[ 'risk_score' ] = (string)round( $this->risk_score );
	}

	protected function stage_hpp_signature( $type = 'request' ) {
		$keys = array(
			'amount',
			'currency_code',
			'expiration',
			'order_id',
			'skin_code',
			'merchant_account',
			'session_expiration',
			'sent_email',
			'customer_id',
			'recurring_type',
			'allowed_methods',
			'blocked_methods',
			'statement_template',
			'return_data',
			'billing_address_type',
			'delivery_address_type',
			'risk_score',
		);
		$sig_values = $this->getStagedValues( $this->getGatewayKeys( $keys ) );
		$this->staged_data['hpp_signature'] = $this->calculateSignature( $sig_values );
	}

	protected function stage_billing_signature( $type = 'request' ) {
		$keys = array(
			'street',
			'city',
			'zip',
			'state',
			'country',
		);
		$sig_values = $this->getStagedValues( $this->getGatewayKeys( $keys ) );
		$this->staged_data['billing_signature'] = $this->calculateSignature( $sig_values );
	}

	protected function getGatewayKeys( $keys ) {
		$staged = array();
		$staging_map = array_flip( $this->var_map );
		foreach ( $keys as $normal_form_key ) {
			$staged[] = $staging_map[ $normal_form_key ];
		}
		return $staged;
	}

	protected function getStagedValues( $keys ) {
		$values = array();
		foreach ( $keys as $key ) {
			$s = $this->getTransactionSpecificValue( $key );
			if ( $s !== NULL ) {
				$values[] = $s;
			}
		}
		return $values;
	}

	function checkResponseSignature( $request_vars ) {
		$normal_form_keys = array(
			'result',
			'gateway_txn_id',
			'order_id',
			'skin_code',
			'return_data'
		);
		$unstage_map = array_flip( $this->return_value_map );
		$keys = array();
		foreach ( $normal_form_keys as $normal_key ) {
			$keys[] = $unstage_map[ $normal_key ];
		}
		$sig_values = array();
		foreach ( $keys as $key ) {
			$sig_values[] = ( array_key_exists( $key, $request_vars ) ? $request_vars[ $key ] : "" );
		}
		$calculated_sig = $this->calculateSignature( $sig_values );
		return ( $calculated_sig === $request_vars[ 'merchantSig' ] );
	}

	protected function calculateSignature( $values ) {
		$joined = implode( '', $values );
		return base64_encode(
			hash_hmac( 'sha1', $joined, $this->accountInfo[ 'hashSecret' ], TRUE )
		);
	}

	/**
	 * Stage the street address. In the event that there isn't anything in
	 * there, we need to send something along so that AVS checks get triggered
	 * at all.
	 * The zero is intentional: Allegedly, Some banks won't perform the check
	 * if the address line contains no numerical data.
	 * @param string $type request|response
	 */
	protected function stage_street( $type = 'request') {
		if ( $type === 'request' ){
			$is_garbage = false;
			$street = trim( $this->staged_data['street'] );
			( strlen( $street ) === 0 ) ? $is_garbage = true : null;
			( !DataValidator::validate_not_just_punctuation( $street ) ) ? $is_garbage = true : null;

			if ( $is_garbage ){
				$this->staged_data['street'] = 'N0NE PROVIDED'; //The zero is intentional. See function comment.
			}
		}
	}

	/**
	 * Stage the zip. In the event that there isn't anything in
	 * there, we need to send something along so that AVS checks get triggered
	 * at all.
	 * @param string $type request|response
	 */
	protected function stage_zip( $type = 'request') {
		if ( $type === 'request' && strlen( trim( $this->staged_data['zip'] ) ) === 0  ){
			//it would be nice to check for more here, but the world has some
			//straaaange postal codes...
			$this->staged_data['zip'] = '0';
		}
	}
}
