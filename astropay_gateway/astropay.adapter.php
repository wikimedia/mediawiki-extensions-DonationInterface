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
use SmashPig\Core\PaymentError;
use SmashPig\Core\ValidationError;
use SmashPig\PaymentData\ErrorCode;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\ValidationAction;

/**
 * AstroPayAdapter
 * Implementation of GatewayAdapter for processing payments via AstroPay
 */
class AstroPayAdapter extends GatewayAdapter {
	const GATEWAY_NAME = 'AstroPay';
	const IDENTIFIER = 'astropay';
	const GLOBAL_PREFIX = 'wgAstroPayGateway';

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

	protected function defineAccountInfo() {
		$this->accountInfo = $this->account_config;
	}

	/**
	 * TODO: How to DRYly configurify?
	 */
	protected function defineErrorMap() {
		$this->error_map = [
			'internal-0000' => 'donate_interface-processing-error', // Failed pre-process checks.
			'internal-0001' => 'donate_interface-try-again',
			ErrorCode::DUPLICATE_ORDER_ID => 'donate_interface-processing-error', // Order ID already used in a previous transaction
		];
	}

	protected function defineReturnValueMap() {
		$this->return_value_map = [];
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
	 * For AstroPay, we use the ct_id.sequence format because we don't get
	 * a gateway transaction ID until the user has actually paid.  If the user
	 * doesn't return to the result switcher, we will need to use the order_id
	 * to find a pending queue message with donor details to flesh out the
	 * audit entry or listener message that tells us the payment succeeded.
	 */
	public function defineOrderIDMeta() {
		$this->order_id_meta = [
			'alt_locations' => [ 'request' => 'x_invoice' ],
			'generate' => true,
			'ct_id' => true,
			'length' => 20,
		];
	}

	protected function defineTransactions() {
		$this->transactions = [];

		$this->transactions['NewInvoice'] = [
			'path' => 'api_curl/streamline/NewInvoice',
			'request' => [
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
				'x_version',
				// Omitting the following optional fields
				// 'x_bdate',
				 'x_address',
				// 'x_zip',
				 'x_city',
				// 'x_state',
				'control',
				'type',
			],
			'values' => [
				'x_login' => $this->accountInfo['Create']['Login'],
				'x_trans_key' => $this->accountInfo['Create']['Password'],
				'x_description' => WmfFramework::formatMessage( 'donate_interface-donation-description' ),
				'x_version' => "1.1",
				'type' => 'json',
			],
			'check_required' => true
		];

		$this->transactions[ 'GetBanks' ] = [
			'path' => 'api_curl/apd/get_banks_by_country',
			'request' => [
				'x_login',
				'x_trans_key',
				'country_code',
				'type',
			],
			'values' => [
				'x_login' => $this->accountInfo['Create']['Login'],
				'x_trans_key' => $this->accountInfo['Create']['Password'],
				'type' => 'json',
			]
		];

		$this->transactions[ 'PaymentStatus' ] = [
			'path' => '/apd/webpaystatus',
			'request' => [
				'x_login',
				'x_trans_key',
				'x_invoice',
			],
			'values' => [
				'x_login' => $this->accountInfo['Status']['Login'],
				'x_trans_key' => $this->accountInfo['Status']['Password'],
			],
			'response_type' => 'delimited',
			'response_delimiter' => '|',
			'response_keys' => [
				'result', // status code
				'x_iduser',
				'x_invoice',
				'x_amount',
				'PT', // 0 for production, 1 for test
				'x_control', // signature, calculated like control string
							// called 'Sign' in docs, but renamed here for consistency
							// with parameter POSTed to resultswitcher.
				'x_document', // unique id at AstroPay
				'x_bank',
				'x_payment_type',
				'x_bank_name',
				'x_currency',
			]
		];

		// Not for running with do_transaction, just a handy place to keep track
		// of what we expect POSTed to the resultswitcher.
		$this->transactions[ 'ProcessReturn' ] = [
			'request' => [
				'result',
				'x_invoice',
				'x_iduser',
				'x_description',
				'x_document',
				'x_amount',
				'x_control',
			]
		];
	}

	protected function getBasedir() {
		return __DIR__;
	}

	public function definePaymentMethods() {
		parent::definePaymentMethods();

		if ( self::getGlobal( 'Test' ) ) {
			// Test bank labelled 'GNB' on their site
			// Data for testing in Brazil (other countries can use random #s)
			// Cpf: 00003456789
			// Email: testing2@dlocal.com
			// Name: DLOCAL TESTING
			// Birthdate: 04/03/1984
			$this->payment_submethods['test_bank'] = [
				'bank_code' => 'TE',
				'label' => 'GNB',
				'group' => 'cc',
			];
		}
	}

	public function doPayment() {
		$this->ensureUniqueOrderID();

		$transaction_result = $this->do_transaction( 'NewInvoice' );
		$this->runAntifraudFilters();
		if ( $this->getValidationAction() !== ValidationAction::PROCESS ) {
			$this->finalizeInternalStatus( FinalStatus::FAILED );
		}
		$result = PaymentResult::fromResults(
			$transaction_result,
			$this->getFinalStatus()
		);
		return $result;
	}

	public function getCurrencies( $options = [] ) {
		$country = $options['country'] ?? $this->getData_Unstaged_Escaped( 'country' );

		if ( !$country ) {
			throw new InvalidArgumentException( 'Need to specify country if not yet set in unstaged data' );
		}
		if ( !isset( $this->config['currencies'][$country] ) ) {
			return [];
		}
		return (array)$this->config['currencies'][$country];
	}

	/**
	 * Processes JSON data from AstroPay API
	 * @param array $response JSON response decoded to array, or GET/POST
	 *        params from request
	 * @throws ResponseProcessingException
	 */
	protected function processResponse( $response ) {
		$this->transaction_response->setData( $response );
		if ( !$response ) {
			throw new ResponseProcessingException(
				'Missing or badly formatted response',
				ErrorCode::NO_RESPONSE
			);
		}
		switch ( $this->getCurrentTransaction() ) {
		case 'PaymentStatus':
			$this->processStatusResponse( $response );
			break;
		case 'NewInvoice':
			$this->processNewInvoiceResponse( $response );
			if ( isset( $response['link'] ) ) {
				$this->transaction_response->setRedirect( $response['link'] );
			}
			break;
		}
	}

	public function processDonorReturn( $requestValues ) {
		// Need to flag that this is a donor return so we use the correct
		// keys to check the signature.
		$this->setCurrentTransaction( 'ProcessReturn' );
		$this->processStatusResponse( $requestValues );
		if ( !isset( $requestValues['x_document'] ) ) {
			$this->logger->error( 'AstroPay did not post back their transaction ID in x_document' );
			throw new ResponseProcessingException(
				'AstroPay did not post back their transaction ID in x_document',
				ErrorCode::MISSING_TRANSACTION_ID
			);
		}
		// Make sure we record the right amount, even if the donor has opened
		// a new window and messed with their session data.
		// Unfortunately, we don't get the currency code back.
		$this->addResponseData( [
			'amount' => $requestValues['x_amount'],
			'gateway_txn_id' => $requestValues['x_document']
		] );
		// FIXME: There is no real API response, so we just create a blank one
		$this->transaction_response = new PaymentTransactionResponse();
		$status = $this->findCodeAction( 'PaymentStatus', 'result', $requestValues['result'] );
		$this->logger->info( "Payment status $status coming back to ResultSwitcher" );
		$this->finalizeInternalStatus( $status );
		$this->postProcessDonation();
		return PaymentResult::fromResults(
			$this->transaction_response,
			$status
		);
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
			$this->logger->error( 'AstroPay response does not have a status code' );
			throw new ResponseProcessingException(
				'AstroPay response does not have a status code',
				ErrorCode::MISSING_REQUIRED_DATA
			);
		}
		$this->transaction_response->setCommunicationStatus( true );
		if ( $response['status'] === '0' ) {
			if ( !isset( $response['link'] ) ) {
				$this->logger->error( 'AstroPay NewInvoice success has no link' );
				throw new ResponseProcessingException(
					'AstroPay NewInvoice success has no link',
					ErrorCode::MISSING_REQUIRED_DATA
				);
			}
		} else {
			$logme = 'AstroPay response has non-zero status.  Full response: '
				. print_r( $response, true );
			$this->logger->warning( $logme );

			$error = new PaymentError(
				'internal-0000',
				$logme,
				LogLevel::WARNING
			);

			if ( isset( $response['desc'] ) ) {
				// error codes are unreliable, so we have to examine the description
				if ( preg_match( '/^invoice already used/i', $response['desc'] ) ) {
					$this->logger->error( 'Order ID collision! Starting again.' );
					throw new ResponseProcessingException(
						'Order ID collision! Starting again.',
						ErrorCode::DUPLICATE_ORDER_ID,
						[ 'order_id' ]
					);
				} elseif ( preg_match( '/^could not (register user|make the deposit)/i', $response['desc'] ) ) {
					// AstroPay is overwhelmed.  Tell the donor to try again soon.
					$error = new PaymentError(
						'internal-0001',
						$logme,
						LogLevel::WARNING
					);
				} elseif ( preg_match( '/^user (unauthorized|blacklisted)/i', $response['desc'] ) ) {
					// They are marked as suspicious by AstroPay,
					// or listed delinquent by their government.
					// Either way, we can't process 'em through AstroPay
					$this->finalizeInternalStatus( FinalStatus::FAILED );
				} elseif ( preg_match( '/^the user limit has been exceeded/i', $response['desc'] ) ) {
					// They've spent too much via AstroPay today.
					// Setting context to 'amount' will tell the form to treat
					// this like a validation error and make amount editable.
					$error = new ValidationError(
						'amount',
						'donate_interface-error-msg-limit',
						[ $this->localizeGlobal( 'OtherWaysURL' ) ]
					);
				} elseif ( preg_match( '/param x_cpf$/i', $response['desc'] ) ) {
					// Something wrong with the fiscal number
					$error = new ValidationError(
						'fiscal_number',
						'donate_interface-error-msg-fiscal_number'
					);
				} elseif ( preg_match( '/invalid control/i', $response['desc'] ) ) {
					// They think we screwed up the signature.  Log what we signed.
					$signed = AstroPaySignature::getNewInvoiceMessage(
						$this->getData_Staged()
					);
					$signature = $this->getData_Staged( 'control' );
					$this->logger->error( "$logme Signed message: '$signed' Signature: '$signature'" );
				} else {
					// Some less common error.  Also log message at 'error' level
					$this->logger->error( $logme );
				}
			}
			$this->transaction_response->addError( $error );
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
			!isset( $response['x_control'] )
		) {
			$this->transaction_response->setCommunicationStatus( false );
			$message = 'AstroPay response missing one or more required keys.  Full response: '
				. print_r( $response, true );
			$this->logger->error( $message );
			throw new ResponseProcessingException( $message, ErrorCode::MISSING_REQUIRED_DATA );
		}
		$this->verifyStatusSignature( $response );
		if ( $response['result'] === '6' ) {
			$logme = 'AstroPay reports they cannot find the transaction for order ID ' .
				$this->getData_Unstaged_Escaped( 'order_id' );
			$this->logger->error( $logme );
			$this->transaction_response->addError(
				new PaymentError(
					'internal-0000',
					$logme,
					LogLevel::ERROR
				)
			);
		}
	}

	/**
	 * Check whether a status message has a valid signature.
	 * @param array $data
	 *        Requires 'result', 'x_amount', 'x_invoice', and 'x_control' keys
	 * @throws ResponseProcessingException if signature is invalid
	 */
	protected function verifyStatusSignature( $data ) {
		if ( $this->getCurrentTransaction() === 'ProcessReturn' ) {
			$login = $this->accountInfo['Create']['Login'];
		} else {
			$login = $this->accountInfo['Status']['Login'];
		}

		$message = $login .
			$data['result'] .
			$data['x_amount'] .
			$data['x_invoice'];
		$signature = AstroPaySignature::calculateSignature( $this, $message );

		if ( $signature !== $data['x_control'] ) {
			$message = 'Bad signature in transaction ' . $this->getCurrentTransaction();
			$this->logger->error( $message );
			throw new ResponseProcessingException( $message, ErrorCode::BAD_SIGNATURE );
		}
	}
}
