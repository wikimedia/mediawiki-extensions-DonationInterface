<?php

use Psr\Log\LogLevel;
use SmashPig\Core\PaymentError;
use SmashPig\PaymentData\ErrorCode;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\PaymentProviderFactory;
use SmashPig\PaymentProviders\Responses\PaymentDetailResponse;

/**
 * PayPal Express Checkout name value pair integration
 *
 * https://developer.paypal.com/docs/classic/express-checkout/overview-ec/
 * https://developer.paypal.com/docs/classic/products/
 * https://developer.paypal.com/docs/classic/express-checkout/ht_ec-singleItemPayment-curl-etc/
 * https://developer.paypal.com/docs/classic/express-checkout/ht_ec-recurringPaymentProfile-curl-etc/
 * TODO: We would need reference transactions to do recurring in Germany or China.
 * https://developer.paypal.com/docs/classic/express-checkout/integration-guide/ECReferenceTxns/#id094UM0C03Y4
 * https://developer.paypal.com/docs/classic/api/gs_PayPalAPIs/
 * https://developer.paypal.com/docs/classic/express-checkout/integration-guide/ECCustomizing/
 */
class PaypalExpressAdapter extends GatewayAdapter {
	const GATEWAY_NAME = 'Paypal Express Checkout';
	const IDENTIFIER = 'paypal_ec';
	const GLOBAL_PREFIX = 'wgPaypalExpressGateway';

	// https://developer.paypal.com/docs/classic/release-notes/#ec
	const API_VERSION = 204;

	public function getCommunicationType() {
		return 'namevalue';
	}

	/**
	 * @return true if the adapter is configured for SSL client certificate
	 * authentication.
	 */
	protected function isCertificateAuthentication() {
		// TODO: generalize certificate path into a class.
		return isset( $this->account_config['CertificatePath'] );
	}

	protected function getProcessorUrl() {
		if ( !self::getGlobal( 'Test' ) ) {
			if ( $this->isCertificateAuthentication() ) {
				$url = self::getGlobal( 'CertificateURL' );
			} else {
				$url = self::getGlobal( 'SignatureURL' );
			}
		} else {
			if ( $this->isCertificateAuthentication() ) {
				$url = self::getGlobal( 'TestingCertificateURL' );
			} else {
				$url = self::getGlobal( 'TestingSignatureURL' );
			}
		}
		return $url;
	}

	public function getResponseType() {
		return 'query_string';
	}

	protected function defineAccountInfo() {
		$this->accountInfo = [];
	}

	/**
	 * TODO: Get L_SHORTMESSAGE0 and L_LONGMESSAGE0
	 */
	protected function defineReturnValueMap() {
		$this->return_value_map = [];
	}

	/**
	 * Use our own Order ID sequence.
	 */
	protected function defineOrderIDMeta() {
		$this->order_id_meta = [
			'generate' => true,
			'ct_id' => true,
		];
	}

	protected function setGatewayDefaults( $options = [] ) {
		if ( $this->getData_Unstaged_Escaped( 'payment_method' ) == null ) {
			$this->addRequestData(
				[ 'payment_method' => 'paypal' ]
			);
		}
	}

	protected function getCurlBaseOpts() {
		$opts = parent::getCurlBaseOpts();

		if ( $this->isCertificateAuthentication() ) {
			$opts[CURLOPT_SSLCERTTYPE] = 'PEM';
			$opts[CURLOPT_SSLCERT] = $this->account_config['CertificatePath'];
		}

		return $opts;
	}

	/**
	 * TODO: Support "response" specification.
	 */
	protected function defineTransactions() {
		$this->transactions = [];

		// https://developer.paypal.com/docs/classic/api/merchant/SetExpressCheckout_API_Operation_NVP/
		$this->transactions['SetExpressCheckout'] = [
			'request' => [
				'return_url',
				'cancel_url',
				'language',
				'amount',
				'currency',
				'description',
				'order_id',
				'recurring',
			],
			'values' => [
				'cancel_url' => ResultPages::getCancelPage( $this ),
			],
		];

		// https://developer.paypal.com/docs/classic/api/merchant/DoExpressCheckoutPayment_API_Operation_NVP/
		$this->transactions['DoExpressCheckoutPayment'] = [
			'request' => [
				'amount',
				'currency',
				'gateway_session_id',
				'description',
				'order_id',
				'processor_contact_id',
			],
			'values' => [
				'description' => WmfFramework::formatMessage( 'donate_interface-donation-description' ),
			],
		];

		// https://developer.paypal.com/docs/classic/api/merchant/CreateRecurringPaymentsProfile_API_Operation_NVP/
		$this->transactions['CreateRecurringPaymentsProfile'] = [
			'request' => [
				'USER',
				'PWD',
				'VERSION',
				'METHOD',
				'TOKEN',
				'DESC',
				// 'L_PAYMENTREQUEST_0_AMT0',
				// 'L_PAYMENTREQUEST_0_DESC0',
				// 'L_PAYMENTREQUEST_n_NAME0',
				// 'L_PAYMENTREQUEST_0_ITEMCATEGORY0',
				'PROFILESTARTDATE',
				'PROFILEREFERENCE',
				'AUTOBILLOUTAMT',
				'BILLINGPERIOD',
				'BILLINGFREQUENCY',
				'TOTALBILLINGCYCLES',
				'MAXFAILEDPAYMENTS',
				'AMT',
				'CURRENCYCODE',
				'EMAIL',
			],
			'values' => [
				'USER' => $this->account_config['User'],
				'PWD' => $this->account_config['Password'],
				'VERSION' => self::API_VERSION,
				'METHOD' => 'CreateRecurringPaymentsProfile',
				'DESC' => WmfFramework::formatMessage( 'donate_interface-monthly-donation-description' ),
				// 'L_PAYMENTREQUEST_0_DESC0' => WmfFramework::formatMessage( 'donate_interface-monthly-donation-description' ),
				// 'L_PAYMENTREQUEST_0_ITEMCATEGORY0' => 'Digital',
				// 'L_PAYMENTREQUEST_n_NAME0' => WmfFramework::formatMessage( 'donate_interface-monthly-donation-description' ),
				// Do not charge for the balance if payments fail.
				'AUTOBILLOUTAMT' => 'NoAutoBill',
				'BILLINGPERIOD' => 'Month',
				'BILLINGFREQUENCY' => 1,
				'TOTALBILLINGCYCLES' => 0, // Forever.
				'MAXFAILEDPAYMENTS' => 0, // Just keep trying
			],
			'response' => [
				# FIXME: Make sure this gets passed as subscription_id in the message
				'PROFILEID',
				'PROFILESTATUS'
			],
		];

		$this->transactions['RefundTransaction'] = [
			'request' => [
				'USER',
				'PWD',
				'VERSION',
				'METHOD',
				'TRANSACTIONID'
			],
			'values' => [
				'USER' => $this->account_config['User'],
				'PWD' => $this->account_config['Password'],
				'VERSION' => self::API_VERSION,
				'METHOD' => 'RefundTransaction'

			],
			'response' => [
				'REFUNDSTATUS',
				'NETREFUNDAMT',
				'GROSSREFUNDAMT'
			]
		];

		$this->transactions['ManageRecurringPaymentsProfileStatusCancel'] = [
			'request' => [
				'USER',
				'PWD',
				'VERSION',
				'METHOD',
				'ACTION',
				'PROFILEID'
			],
			'values' => [
				'USER' => $this->account_config['User'],
				'PWD' => $this->account_config['Password'],
				'VERSION' => self::API_VERSION,
				'METHOD' => 'ManageRecurringPaymentsProfileStatus',
				'ACTION' => 'Cancel'
			],
			'response' => [
				'PROFILEID'
			]
		];

		// Add the Signature field to all API calls, if necessary.
		// Note that this gives crappy security, vulnerable to replay attacks.
		// The signature is static, not a checksum of the request.
		if ( !$this->isCertificateAuthentication() ) {
			foreach ( $this->transactions as $_name => &$info ) {
				// This whole method will go away at the end of the refactor, but for now, just exclude the API
				// calls we have migrated to SmashPig
				if ( isset( $info['request'] ) && $_name !== 'SetExpressCheckout' && $_name !== 'DoExpressCheckoutPayment' ) {
					$info['request'][] = 'SIGNATURE';
					$info['values']['SIGNATURE'] = $this->account_config['Signature'];
				}
			}
		}
	}

	/**
	 * Just needed till we switch all the calls over to using SmashPig
	 *
	 * @return void
	 */
	protected function overrideVarMap() {
		// Transitional code, override var_map
		$this->var_map = [
			'amount' => 'amount',
			'cancel_url' => 'cancel_url',
			'currency' => 'currency',
			'description' => 'description',
			'gateway_session_id' => 'gateway_session_id',
			'language' => 'language',
			'order_id' => 'order_id',
			'processor_contact_id' => 'processor_contact_id',
			'recurring' => 'recurring',
			'return_url' => 'return_url',
		];
	}

	public function doPayment() {
		$this->config['transformers'][] = 'PaypalExpressReturnUrl';
		$this->data_transformers[] = new PaypalExpressReturnUrl();
		$this->stageData();
		$provider = PaymentProviderFactory::getProviderForMethod(
			$this->getPaymentMethod()
		);
		// Transitional code, override var_map
		$this->overrideVarMap();
		$this->setCurrentTransaction( 'SetExpressCheckout' );

		$descriptionKey = $this->getData_Unstaged_Escaped( 'recurring' ) ?
			'donate_interface-monthly-donation-description' :
			'donate_interface-donation-description';

		$this->transactions['SetExpressCheckout']['values']['description'] =
			WmfFramework::formatMessage( $descriptionKey );

		// Returns a token which and a redirect URL to send the donor to PayPal
		$paymentSessionResult = $provider->createPaymentSession( $this->buildRequestArray() );
		if ( $paymentSessionResult->isSuccessful() ) {
			$this->addResponseData( [ 'gateway_session_id' => $paymentSessionResult->getPaymentSession() ] );
			$this->session_addDonorData();
			return PaymentResult::newRedirect( $paymentSessionResult->getRedirectUrl() );
		}

		return PaymentResult::newFailure( $paymentSessionResult->getErrors() );
	}

	/**
	 * @return bool false, but we're kinda lying.
	 * We do need to DoExpressCheckoutPayment when donors return, but it's
	 * better to lose a few donations and show the thank you page than to
	 * risk duplicate donations and problems for donor services. We handle
	 * donors who return with no cookies by running a pending transaction
	 * resolver like we do with Ingenico.
	 */
	public function isReturnProcessingRequired() {
		return false;
	}

	public function getRequestProcessId( $requestValues ) {
		return $requestValues['token'];
	}

	protected function processResponse( $response ) {
		$this->transaction_response->setData( $response );
		// FIXME: I'm not sure why we're responsible for failing the
		// transaction.  If not, we can omit the try/catch here.
		try {
			if ( !$response ) {
				throw new ResponseProcessingException(
					'Missing or badly formatted response',
					ErrorCode::NO_RESPONSE
				);
			}

			switch ( $this->getCurrentTransaction() ) {
				case 'CreateRecurringPaymentsProfile':
					$this->checkResponseAck( $response );

					// Grab the subscription ID
					$this->addResponseData( $this->unstageKeys( $response ) );

					// We've created a subscription, but we haven't got an initial
					// payment yet, so we leave the details in the pending queue.
					// The IPN listener will push the donation through to Civi when
					// it gets notifications from PayPal.
					// TODO: it would be nice to send the subscr_start message to
					// the recurring queue here.
					$this->finalizeInternalStatus( FinalStatus::PENDING );
					$this->postProcessDonation();
					break;

				case 'ManageRecurringPaymentsProfileStatusCancel':
				case 'RefundTransaction':
					$this->checkResponseAck( $response ); // Sets the comms status so we don't hit the error block below
			}

			if ( !$this->transaction_response->getCommunicationStatus() ) {
				// TODO: so much boilerplate...  Just throw an exception subclass.
				$logme = 'Failed response for Order ID ' . $this->getData_Unstaged_Escaped( 'order_id' );
				$this->logger->error( $logme );
				$this->transaction_response->addError( new PaymentError(
					'internal-0000',
					$logme,
					LogLevel::ERROR
				) );
			}
		} catch ( Exception $ex ) {
			$errors = $this->parseResponseErrors( $response );
			$fatal = true;
			// TODO: Handle more error codes
			foreach ( $errors as $error ) {
				// There are errors, so it wasn't a total comms failure
				$this->transaction_response->setCommunicationStatus( true );
				$code = $error->getErrorCode();
				$debugInfo = $error->getDebugMessage();
				$this->logger->warning(
					"Error code $code returned: '$debugInfo'"
				);
				switch ( $code ) {
					case '10486':
						// Donor's first funding method failed, but they might have another
						$this->transaction_response->setRedirect(
							$this->createRedirectUrl( $this->getData_Unstaged_Escaped( 'gateway_session_id' ) )
						);
						$fatal = false;
						break;
					case '10411':
						if ( $this->isBatchProcessor() ) {
							$this->finalizeInternalStatus( FinalStatus::TIMEOUT );
							$fatal = false;
							break;
						}
					default:
						$this->transaction_response->addError( $error );
				}
			}
			if ( $fatal ) {
				if ( empty( $errors ) ) {
					// Unrecognizable problems, log the whole thing
					$this->logger->error( "Failure detected in " . json_encode( $response ) );
				}
				$this->finalizeInternalStatus( FinalStatus::FAILED );
				throw $ex;
			}
		}
	}

	/**
	 * @param array $response
	 * @return PaymentError[]
	 */
	protected function parseResponseErrors( $response ) {
		$errors = [];
		// TODO: can they put errors in other places too?
		if ( isset( $response['L_ERRORCODE0'] ) ) {
			$errors[] = new PaymentError(
				$response['L_ERRORCODE0'],
				$response['L_LONGMESSAGE0'] ?? '',
				LogLevel::ERROR
			);
		}
		return $errors;
	}

	public function processDonorReturn( $requestValues ) {
		if (
			empty( $requestValues['token'] )
		) {
			throw new ResponseProcessingException(
				'Missing required parameters in request',
				ErrorCode::MISSING_REQUIRED_DATA
			);
		}
		$requestData = [
			'gateway_session_id' => urldecode( $requestValues['token'] )
		];
		if (
			empty( $requestValues['PayerID'] )
		) {
			$this->logger->info( 'Notice missing PayerID in PaypalExpressAdapater::ProcessDonorReturn' );
		} else {
			$requestData['payer_id'] = $requestValues['PayerID'];
		}
		$this->addRequestData( $requestData );
		$provider = PaymentProviderFactory::getProviderForMethod(
			$this->getPaymentMethod()
		);
		$detailsResult = $provider->getLatestPaymentStatus( [
			'gateway_session_id' => $requestData['gateway_session_id']
		] );

		if ( !$detailsResult->isSuccessful() ) {
			if ( $detailsResult->requiresRedirect() ) {
				return PaymentResult::newRedirect( $detailsResult->getRedirectUrl() );
			}
			$this->finalizeInternalStatus( $detailsResult->getStatus() );
			return PaymentResult::newFailure();
		}
		$this->addDonorDetailsToSession( $detailsResult );
		if ( $detailsResult->getStatus() === FinalStatus::PENDING_POKE ) {
			if ( $this->getData_Unstaged_Escaped( 'recurring' ) ) {
				// Set up recurring billing agreement.
				$this->addRequestData( [
					'date' => time()
				] );
				$resultData = $this->do_transaction( 'CreateRecurringPaymentsProfile' );
				if ( !$resultData->getCommunicationStatus() ) {
					throw new ResponseProcessingException(
						'Failed to create a recurring profile', ErrorCode::UNKNOWN );
				}
			} else {
				// Transitional code, override var_map
				$this->overrideVarMap();
				// One-time payment, or initial payment in a subscription.
				$this->setCurrentTransaction( 'DoExpressCheckoutPayment' );
				$approvePaymentParams = $this->buildRequestArray();
				$approveResult = $provider->approvePayment( $approvePaymentParams );
				if ( $approveResult->isSuccessful() ) {
					$this->addResponseData(
						[ 'gateway_txn_id' => $approveResult->getGatewayTxnId() ]
					);
					$this->finalizeInternalStatus( FinalStatus::COMPLETE );
					$this->postProcessDonation();
				} else {
					$this->finalizeInternalStatus( FinalStatus::FAILED );
					return PaymentResult::newFailure( $approveResult->getErrors() );
				}
			}
		} else {
			$this->finalizeInternalStatus( $detailsResult->getStatus() );
		}
		return PaymentResult::fromResults(
			$this->getTransactionResponse() ?? new PaymentTransactionResponse(),
			$this->getFinalStatus()
		);
	}

	protected function addDonorDetailsToSession( PaymentDetailResponse $detailResponse ): void {
		$donorDetails = $detailResponse->getDonorDetails();
		if ( $donorDetails !== null ) {
			$responseData = [
				'first_name' => $donorDetails->getFirstName(),
				'last_name' => $donorDetails->getLastName(),
				'email' => $donorDetails->getEmail(),
				'processor_contact_id' => $detailResponse->getProcessorContactID(),
			];
			$address = $donorDetails->getBillingAddress();
			if ( $address !== null ) {
				$responseData += [
					'city' => $address->getCity(),
					'country' => $address->getCountryCode(),
					'street_address' => $address->getStreetAddress(),
					'postal_code' => $address->getPostalCode(),
					'state_province' => $address->getPostalCode()
				];
			}
			$this->addResponseData( $responseData );
			$this->session_addDonorData();
		}
	}

	/**
	 * Shared snippet to parse the ACK response field and store it as
	 * communication status.
	 *
	 * @param array $response The response from the PayPal API call
	 * @throws ResponseProcessingException
	 */
	protected function checkResponseAck( $response ) {
		if (
			isset( $response['ACK'] ) &&
			// SuccessWithWarning is OK too
			substr( $response['ACK'], 0, 7 ) === 'Success'
		) {
			$this->transaction_response->setCommunicationStatus( true );
			if ( $response['ACK'] === 'SuccessWithWarning' ) {
				$this->logger->warning(
					'Transaction succeeded with warning. Response: ' .
					print_r( $response, true )
				);
			}
		} else {
			throw new ResponseProcessingException( "Failure response", $response['ACK'] );
		}
	}

	public function doRefund() {
		$response = $this->do_transaction( 'RefundTransaction' );
		if ( !$response->getCommunicationStatus() ) {
			return PaymentResult::newFailure( $response->getErrors() );
		}
		return PaymentResult::fromResults( $response, FinalStatus::COMPLETE );
	}

	public function cancelSubscription() {
		$response = $this->do_transaction( 'ManageRecurringPaymentsProfileStatusCancel' );
		if ( !$response->getCommunicationStatus() || count( $response->getErrors() ) > 0 ) {
			return PaymentResult::newFailure( $response->getErrors() );
		}
		return PaymentResult::fromResults( $response, FinalStatus::COMPLETE );
	}

	/**
	 * TODO: add test
	 * @return array
	 */
	public function createDonorReturnParams() {
		return [ 'token' => $this->getData_Staged( 'gateway_session_id' ) ];
	}

	/**
	 * Returns true becaues all payment methods can be rectified
	 * FIXME: Add handling for session expiration limits?
	 * @return bool
	 */
	public function shouldRectifyOrphan() {
		return true;
	}

	protected function createRedirectUrl( $token ) {
		return $this->account_config['RedirectURL'] . $token . '&useraction=commit';
	}
}
