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
 * AstropayAdapter
 * Implementation of GatewayAdapter for processing payments via Astropay
 * FIXME: camlcase "P"
 */
class AstropayAdapter extends GatewayAdapter {
	const GATEWAY_NAME = 'Astropay';
	const IDENTIFIER = 'astropay';
	const GLOBAL_PREFIX = 'wgAstropayGateway';

	public function getCommunicationType() {
		return 'namevalue';
	}

	public function getResponseType() {
		$override = $this->transaction_option( 'response_type' );
		if ( $override ) {
			return $override;
		}
		return 'json';
	}

	function defineAccountInfo() {
		$this->accountInfo = $this->account_config;
	}

	function defineErrorMap() {
		$this->error_map = array(
			'internal-0000' => 'donate_interface-processing-error', // Failed pre-process checks.
			ResponseCodes::DUPLICATE_ORDER_ID => 'donate_interface-processing-error', // Order ID already used in a previous transaction
		);
	}

	function defineStagedVars() {}

	function defineReturnValueMap() {
		$this->return_value_map = array();
		// 6: Transaction not found in the system
		$this->addCodeRange( 'PaymentStatus', 'result', FinalStatus::FAILED, 6 );
		// 7: Pending transaction awaiting approval
		$this->addCodeRange( 'PaymentStatus', 'result', FinalStatus::PENDING, 7 );
		// 8: Operation rejected by bank
		$this->addCodeRange( 'PaymentStatus', 'result', FinalStatus::FAILED, 8 );
		// 9: Amount Paid.  Transaction successfully concluded
		$this->addCodeRange( 'PaymentStatus', 'result', FinalStatus::COMPLETE, 9 );
	}

	/**
	 * Sets up the $order_id_meta array.
	 * For Astropay, we use the ct_id.sequence format because we don't get
	 * a gateway transaction ID until the user has actually paid.  If the user
	 * doesn't return to the result switcher, we will need to use the order_id
	 * to find a pending queue message with donor details to flesh out the
	 * audit entry or listener message that tells us the payment succeeded.
	 */
	public function defineOrderIDMeta() {
		$this->order_id_meta = array (
			'alt_locations' => array ( 'request' => 'x_invoice' ),
			'generate' => TRUE,
			'ct_id' => TRUE,
			'length' => 20,
		);
	}

	function setGatewayDefaults() {}

	function defineTransactions() {
		$this->transactions = array( );

		$this->transactions[ 'NewInvoice' ] = array(
			'path' => 'api_curl/streamline/NewInvoice',
			'request' => array(
				'x_login',
				'x_trans_key', // password
				'x_invoice', // order id
				'x_amount',
				'x_currency',
				'x_bank', // payment submethod bank code
				'x_country',
				'x_description',
				'x_iduser',
				'x_cpf',
				'x_name',
				'x_email',
				// Omitting the following optional fields
				// 'x_bdate',
				// 'x_address',
				// 'x_zip',
				// 'x_city',
				// 'x_state',
				'control',
				'type',
			),
			'values' => array(
				'x_login' => $this->accountInfo['Create']['Login'],
				'x_trans_key' => $this->accountInfo['Create']['Password'],
				'x_description' => WmfFramework::formatMessage( 'donate_interface-donation-description' ),
				'type' => 'json',
			)
		);

		$this->transactions[ 'GetBanks' ] = array(
			'path' => 'api_curl/apd/get_banks_by_country',
			'request' => array(
				'x_login',
				'x_trans_key',
				'country_code',
				'type',
			),
			'values' => array(
				'x_login' => $this->accountInfo['Create']['Login'],
				'x_trans_key' => $this->accountInfo['Create']['Password'],
				'type' => 'json',
			)
		);

		$this->transactions[ 'PaymentStatus' ] = array(
			'path' => '/apd/webpaystatus',
			'request' => array(
				'x_login',
				'x_trans_key',
				'x_invoice',
			),
			'values' => array(
				'x_login' => $this->accountInfo['Status']['Login'],
				'x_trans_key' => $this->accountInfo['Status']['Password'],
			),
			'response_type' => 'delimited',
			'response_delimiter' => '|',
			'response_keys' => array(
				'result', // status code
				'x_iduser',
				'x_invoice',
				'x_amount',
				'PT', // 0 for production, 1 for test
				'x_control', // signature, calculated like control string
							// called 'Sign' in docs, but renamed here for consistency
							// with parameter POSTed to resultswitcher.
				'x_document', // unique id at Astropay
				'x_bank',
				'x_payment_type',
				'x_bank_name',
				'x_currency',
			)
		);

		// Not for running with do_transaction, just a handy place to keep track
		// of what we expect POSTed to the resultswitcher.
		$this->transactions[ 'ProcessReturn' ] = array(
			'request' => array(
				'result',
				'x_invoice',
				'x_iduser',
				'x_description',
				'x_document',
				'x_amount',
				'x_control',
			)
		);
	}

	function getBasedir() {
		return __DIR__;
	}

	public function definePaymentMethods() {
		parent::definePaymentMethods();

		if ( self::getGlobal( 'Test' ) ) {
			// Test bank labelled 'GNB' on their site
			// Data for testing in Brazil (other countries can use random #s)
			// Cpf: 00003456789
			// Email: testing@astropaycard.com
			// Name: ASTROPAY TESTING
			// Birthdate: 04/03/1984
			$this->payment_submethods['test_bank'] = array(
				'bank_code' => 'TE',
				'label' => 'GNB',
				'group' => 'cc',
			);
		}
	}

	function doPayment() {
		// If this is not our first NewInvoice call, get a fresh order ID
		if ( $this->session_getData( 'sequence' ) ) {
			$this->regenerateOrderID();
		}

		$transaction_result = $this->do_transaction( 'NewInvoice' );
		$this->runAntifraudHooks();
		if ( $this->getValidationAction() !== 'process' ) {
			$this->finalizeInternalStatus( FinalStatus::FAILED );
		}
		$result = PaymentResult::fromResults(
			$transaction_result,
			$this->getFinalStatus()
		);
		if ( $result->getRedirect() ) {
			// Write the donor's details to the log for the audit processor
			$this->logPaymentDetails();
			// Feed the message into the pending queue, so the CRM queue consumer
			// can read it to fill in donor details when it gets a partial message
			$this->setLimboMessage( 'pending' );
		}
		return $result;
	}

	/**
	 * Overriding parent method to add fiscal number
	 * @return array of required field names
	 */
	public function getRequiredFields() {
		$fields = parent::getRequiredFields();
		$noFiscalRequired = array( 'MX', 'PE' );
		$country = $this->getData_Unstaged_Escaped( 'country' );
		if ( !in_array( $country, $noFiscalRequired ) ) {
			$fields[] = 'fiscal_number';
		}
		$fields[] = 'payment_submethod';
		return $fields;
	}
	/**
	 * Overriding @see GatewayAdapter::getTransactionSpecificValue to add a
	 * calculated signature.
	 * @param string $gateway_field_name
	 * @param boolean $token
	 * @return mixed
	 */
	protected function getTransactionSpecificValue( $gateway_field_name, $token = false ) {
		if ( $gateway_field_name === 'control' ) {
			$message = $this->getMessageToSign();
			return $this->calculateSignature( $message );
		}
		return parent::getTransactionSpecificValue( $gateway_field_name, $token );
	}

	protected function getMessageToSign() {
		return str_replace( '+', ' ',
			$this->getData_Staged( 'order_id' ) . 'V'
			. $this->getData_Staged( 'amount' ) . 'I'
			. $this->getData_Staged( 'donor_id' ) . '2'
			. $this->getData_Staged( 'bank_code' ) . '1'
			. $this->getData_Staged( 'fiscal_number' ) . 'H'
			. /* bdate omitted */ 'G'
			. $this->getData_Staged( 'email' ) .'Y'
			. /* zip omitted */ 'A'
			. /* street omitted */ 'P'
			. /* city omitted */ 'S'
			. /* state omitted */ 'P' );
	}

	public function getCurrencies( $options = array() ) {
		$country = isset( $options['country'] ) ?
					$options['country'] :
					$this->getData_Unstaged_Escaped( 'country' );

		if ( !$country ) {
			throw new InvalidArgumentException( 'Need to specify country if not yet set in unstaged data' );
		}
		if ( !isset( $this->config['currencies'][$country] ) ) {
			throw new OutOfBoundsException( "No supported currencies for $country" );
		}
		return (array)$this->config['currencies'][$country];
	}

	/**
	 * Processes JSON data from Astropay API, and also processes GET/POST params
	 * on donor's return to ResultSwitcher
	 * @param array $response JSON response decoded to array, or GET/POST
	 *        params from request
	 * @throws ResponseProcessingException
	 */
	public function processResponse( $response ) {
		// May need to initialize transaction_response, as we can be called by
		// GatewayPage to process responses outside of do_transaction
		if ( !$this->transaction_response ) {
			$this->transaction_response = new PaymentTransactionResponse();
		}
		$this->transaction_response->setData( $response );
		if ( !$response ) {
			throw new ResponseProcessingException(
				'Missing or badly formatted response',
				ResponseCodes::NO_RESPONSE
			);
		}
		switch( $this->getCurrentTransaction() ) {
		case 'PaymentStatus':
			$this->processStatusResponse( $response );
			break;
		case 'ProcessReturn':
			$this->processStatusResponse( $response );
			if ( !isset( $response['x_document'] ) ) {
				$this->logger->error( 'Astropay did not post back their transaction ID in x_document' );
				throw new ResponseProcessingException(
					'Astropay did not post back their transaction ID in x_document',
					ResponseCodes::MISSING_TRANSACTION_ID
				);
			}
			// Make sure we record the right amount, even if the donor has opened
			// a new window and messed with their session data.
			// Unfortunately, we don't get the currency code back.
			$this->addResponseData( array(
				'amount' => $response['x_amount'],
			) );
			$this->transaction_response->setGatewayTransactionId( $response['x_document'] );
			$status = $this->findCodeAction( 'PaymentStatus', 'result', $response['result'] );
			$this->logger->info( "Payment status $status coming back to ResultSwitcher" );
			$this->finalizeInternalStatus( $status );
			$this->runPostProcessHooks();
			$this->deleteLimboMessage( 'pending' );
			break;
		case 'NewInvoice':
			$this->processNewInvoiceResponse( $response );
			if ( isset( $response['link'] ) ) {
				$this->transaction_response->setRedirect( $response['link'] );
			}
			break;
		}
	}

	/**
	 * Sets communication status and errors for responses to NewInvoice
	 * @param array $response
	 */
	protected function processNewInvoiceResponse( $response ) {
		// Increment sequence number so next NewInvoice call gets a new order ID
		$this->incrementSequenceNumber();
		if ( !isset( $response['status'] ) ) {
			$this->transaction_response->setCommunicationStatus( false );
			$this->logger->error( 'Astropay response does not have a status code' );
			throw new ResponseProcessingException(
				'Astropay response does not have a status code',
				ResponseCodes::MISSING_REQUIRED_DATA
			);
		}
		$this->transaction_response->setCommunicationStatus( true );
		if ( $response['status'] === '0' ) {
			if ( !isset( $response['link'] ) ) {
				$this->logger->error( 'Astropay NewInvoice success has no link' );
				throw new ResponseProcessingException(
					'Astropay NewInvoice success has no link',
					ResponseCodes::MISSING_REQUIRED_DATA
				);
			}
		} else {
			$logme = 'Astropay response has non-zero status.  Full response: '
				. print_r( $response, true );
			$this->logger->warning( $logme );

			$code = 'internal-0000';
			$message = $this->getErrorMapByCodeAndTranslate( $code );
			$context = null;

			if ( isset( $response['desc'] ) ) {
				// error codes are unreliable, so we have to examine the description
				if ( preg_match( '/^invoice already used/i', $response['desc'] ) ) {
					$this->logger->error( 'Order ID collision! Starting again.' );
					throw new ResponseProcessingException(
						'Order ID collision! Starting again.',
						ResponseCodes::DUPLICATE_ORDER_ID,
						array( 'order_id' )
					);
				} else if ( preg_match( '/^could not (register user|make the deposit)/i', $response['desc'] ) ) {
					// AstroPay is overwhelmed.  Tell the donor to try again soon.
					$message = WmfFramework::formatMessage( 'donate_interface-try-again' );
				} else if ( preg_match( '/^user (unauthorized|blacklisted)/i', $response['desc'] ) ) {
					// They are blacklisted by Astropay for shady doings,
					// or listed delinquent by their government.
					// Either way, we can't process 'em through AstroPay
					$this->finalizeInternalStatus( FinalStatus::FAILED );
				} else if ( preg_match( '/^the user limit has been exceeded/i', $response['desc'] ) ) {
					// They've spent too much via AstroPay today.
					// Setting context to 'amount' will tell the form to treat
					// this like a validation error and make amount editable.
					$context = 'amount';
					$message = WmfFramework::formatMessage( 'donate_interface-error-msg-limit' );
				} else if ( preg_match( '/param x_cpf$/i', $response['desc'] ) ) {
					// Something wrong with the fiscal number
					$context = 'fiscal_number';
					$language = $this->dataObj->getVal_Escaped( 'language' );
					$country = $this->dataObj->getVal_Escaped( 'country' );
					$message = DataValidator::getErrorMessage( 'fiscal_number', 'calculated', $language, $country );
				} else if ( preg_match( '/invalid control/i', $response['desc'] ) ) {
					// They think we screwed up the signature.  Log what we signed.
					$signed = $this->getMessageToSign();
					$signature = $this->getTransactionSpecificValue( 'control' );
					$this->logger->error( "$logme Signed message: '$signed' Signature: '$signature'" );
				} else {
					// Some less common error.  Also log message at 'error' level
					$this->logger->error( $logme );
				}
			}
			$this->transaction_response->setErrors( array(
				$code => array (
					'message' => $message,
					'debugInfo' => $logme,
					'logLevel' => LogLevel::WARNING,
					'context' => $context
				)
			) );
		}
	}

	/**
	 * Sets communication status and errors for responses to PaymentStatus or
	 * parameters POSTed back to ResultSwitcher
	 * @param array $response
	 */
	protected function processStatusResponse( $response ) {
		if ( !isset( $response['result'] ) ||
			 !isset( $response['x_amount'] ) ||
			 !isset( $response['x_invoice'] ) ||
			 !isset( $response['x_control'] ) ) {
			$this->transaction_response->setCommunicationStatus( false );
			$message = 'Astropay response missing one or more required keys.  Full response: '
				. print_r( $response, true );
			$this->logger->error( $message );
			throw new ResponseProcessingException( $message, ResponseCodes::MISSING_REQUIRED_DATA );
		}
		$this->verifyStatusSignature( $response );
		if ( $response['result'] === '6' ) {
			$logme = 'Astropay reports they cannot find the transaction for order ID ' .
				$this->getData_Unstaged_Escaped( 'order_id' );
			$this->logger->error( $logme );
			$this->transaction_response->setErrors( array(
				'internal-0000' => array (
					'message' => $this->getErrorMapByCodeAndTranslate( 'internal-0000' ),
					'debugInfo' => $logme,
					'logLevel' => LogLevel::ERROR
				)
			) );
		}
	}

	/**
	 * Check whether a status message has a valid signature.
	 * @param array $data
	 *        Requires 'result', 'x_amount', 'x_invoice', and 'x_control' keys
	 * @throws ResponseProcessingException if signature is invalid
	 */
	function verifyStatusSignature( $data ) {
		if ( $this->getCurrentTransaction() === 'ProcessReturn' ) {
			$login = $this->accountInfo['Create']['Login'];
		} else {
			$login = $this->accountInfo['Status']['Login'];
		}

		$message = $login .
			$data['result'] .
			$data['x_amount'] .
			$data['x_invoice'];
		$signature = $this->calculateSignature( $message );

		if ( $signature !== $data['x_control'] ) {
			$message = 'Bad signature in transaction ' . $this->getCurrentTransaction();
			$this->logger->error( $message );
			throw new ResponseProcessingException( $message, ResponseCodes::BAD_SIGNATURE );
		}
	}

	protected function calculateSignature( $message ) {
		$key = $this->accountInfo['SecretKey'];
		return strtoupper(
			hash_hmac( 'sha256', pack( 'A*', $message ), pack( 'A*', $key ) )
		);
	}
}
