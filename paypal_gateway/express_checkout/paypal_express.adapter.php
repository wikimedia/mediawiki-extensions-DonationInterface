<?php

use Psr\Log\LogLevel;
use SmashPig\Core\PaymentError;
use SmashPig\PaymentData\ErrorCode;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\ValidationAction;

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
		// 0: No errors
		$this->addCodeRange( 'DoExpressCheckoutPayment', 'PAYMENTINFO_0_ERRORCODE', FinalStatus::COMPLETE, 0 );
		// 10412: Payment has already been made for this InvoiceID.
		$this->addCodeRange( 'DoExpressCheckoutPayment', 'PAYMENTINFO_0_ERRORCODE', FinalStatus::FAILED, 10412 );
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
				'USER',
				'PWD',
				// 'SIGNATURE', // See below.
				'VERSION',
				'METHOD',
				'RETURNURL',
				'CANCELURL',
				'REQCONFIRMSHIPPING',
				'NOSHIPPING',
				'LOCALECODE',
				// TODO: PAGESTYLE, HDRIMG, LOGOIMG
				'EMAIL',
				'L_PAYMENTREQUEST_0_AMT0',
				'L_PAYMENTREQUEST_0_DESC0',
				// TODO: Using item category = 'Digital' might save you on
				// rates, this should be configurable.
				// 'L_PAYMENTREQUEST_0_ITEMCATEGORY0',
				'PAYMENTREQUEST_0_AMT',
				'PAYMENTREQUEST_0_CURRENCYCODE',
				// FIXME: This should be deprecated, and is only for back-compat.
				'PAYMENTREQUEST_0_CUSTOM',
				'PAYMENTREQUEST_0_DESC',
				'PAYMENTREQUEST_0_INVNUM',
				'PAYMENTREQUEST_0_ITEMAMT', // FIXME: Not clear why this is required.
				'PAYMENTREQUEST_0_PAYMENTACTION',
				'PAYMENTREQUEST_0_PAYMENTREASON',
				'SOLUTIONTYPE',
				// TODO: Investigate why can we give this as an input:
				// PAYMENTREQUEST_n_TRANSACTIONID
				// TODO: BUYEREMAILOPTINENABLE=1
			],
			'values' => [
				'USER' => $this->account_config['User'],
				'PWD' => $this->account_config['Password'],
				'VERSION' => self::API_VERSION,
				'METHOD' => 'SetExpressCheckout',
				'CANCELURL' => ResultPages::getCancelPage( $this ),
				'REQCONFIRMSHIPPING' => 0,
				'NOSHIPPING' => 1,
				'L_PAYMENTREQUEST_0_DESC0' => WmfFramework::formatMessage( 'donate_interface-donation-description' ),
				// 'L_PAYMENTREQUEST_0_ITEMCATEGORY0' => 'Digital',
				'PAYMENTREQUEST_0_DESC' => WmfFramework::formatMessage( 'donate_interface-donation-description' ),
				'PAYMENTREQUEST_0_PAYMENTACTION' => 'Sale',
				'PAYMENTREQUEST_0_PAYMENTREASON' => 'None',
				// SOLUTIONTYPE Mark means PayPal account is required
				'SOLUTIONTYPE' => 'Mark',
			],
			'response' => [
				'TOKEN',
			],
		];

		// https://developer.paypal.com/docs/classic/api/merchant/SetExpressCheckout_API_Operation_NVP/
		$this->transactions['SetExpressCheckout_recurring'] = [
			'request' => [
				'USER',
				'PWD',
				'VERSION',
				'METHOD',
				'RETURNURL',
				'CANCELURL',
				'REQCONFIRMSHIPPING',
				'NOSHIPPING',
				'LOCALECODE',
				// TODO: PAGESTYLE, HDRIMG, LOGOIMG
				'EMAIL',
				'L_BILLINGTYPE0',
				'L_BILLINGAGREEMENTDESCRIPTION0',
				'L_BILLINGAGREEMENTCUSTOM0',
				'L_PAYMENTREQUEST_0_AMT0',
				// // Note that the DESC fields can be tweaked to get different
				// // effects in the PayPal layout.
				// 'L_PAYMENTREQUEST_0_DESC0',
				'L_PAYMENTREQUEST_0_NAME0',
				'L_PAYMENTREQUEST_0_QTY0',
				'MAXAMT',
				'PAYMENTREQUEST_0_AMT',
				'PAYMENTREQUEST_0_CURRENCYCODE',
				// // FIXME: This should be deprecated, and is only for back-compat.
				// 'PAYMENTREQUEST_0_CUSTOM',
				// 'PAYMENTREQUEST_0_DESC',
				// 'PAYMENTREQUEST_0_INVNUM',
				'PAYMENTREQUEST_0_ITEMAMT',
				// 'PAYMENTREQUEST_0_PAYMENTACTION',
				// 'PAYMENTREQUEST_0_PAYMENTREASON',
				// // TODO: Investigate why would give this as an input:
				// // PAYMENTREQUEST_n_TRANSACTIONID,
				'SOLUTIONTYPE'
			],
			'values' => [
				'USER' => $this->account_config['User'],
				'PWD' => $this->account_config['Password'],
				'VERSION' => self::API_VERSION,
				'METHOD' => 'SetExpressCheckout',
				'CANCELURL' => ResultPages::getCancelPage( $this ),
				'REQCONFIRMSHIPPING' => 0,
				'NOSHIPPING' => 1,
				'L_BILLINGTYPE0' => 'RecurringPayments',
				// FIXME: Sad!  The thank-you message would be perfect here,
				// but it seems the exclamation mark is not supported, even when
				// urlencoded properly.
				// 'L_BILLINGAGREEMENTDESCRIPTION0' => WmfFramework::formatMessage( 'donate_interface-donate-error-thank-you-for-your-support' ),
				'L_BILLINGAGREEMENTDESCRIPTION0' => WmfFramework::formatMessage( 'donate_interface-monthly-donation-description' ),
				'L_PAYMENTREQUEST_0_DESC0' => WmfFramework::formatMessage( 'donate_interface-monthly-donation-description' ),
				// 'L_PAYMENTREQUEST_0_ITEMCATEGORY0' => 'Digital',
				'L_PAYMENTREQUEST_0_NAME0' => WmfFramework::formatMessage( 'donate_interface-monthly-donation-description' ),
				'L_PAYMENTREQUEST_0_QTY0' => 1,
				'PAYMENTREQUEST_0_DESC' => WmfFramework::formatMessage( 'donate_interface-monthly-donation-description' ),
				'PAYMENTREQUEST_0_PAYMENTACTION' => 'Sale',
				'PAYMENTREQUEST_0_PAYMENTREASON' => 'None',
				// SOLUTIONTYPE Mark means PayPal account is required
				'SOLUTIONTYPE' => 'Mark',
			],
			'response' => [
				'TOKEN',
			],
		];

		// Incoming parameters after returning from the PayPal workflow
		$this->transactions['ProcessReturn'] = [
			'request' => [
				'token',
				'PayerID',
			],
		];

		// https://developer.paypal.com/docs/classic/api/merchant/GetExpressCheckoutDetails_API_Operation_NVP/
		$this->transactions['GetExpressCheckoutDetails'] = [
			'request' => [
				'USER',
				'PWD',
				'VERSION',
				'METHOD',
				'TOKEN',
			],
			'values' => [
				'USER' => $this->account_config['User'],
				'PWD' => $this->account_config['Password'],
				'VERSION' => self::API_VERSION,
				'METHOD' => 'GetExpressCheckoutDetails',
			],
			'response' => [
				'ACK',
				'TOKEN',
				'CORRELATIONID',
				'TIMESTAMP',
				'CUSTOM',
				'INVNUM',
				'BILLINGAGREEMENTACCEPTEDSTATUS',
				'REDIRECTREQUIRED',
				'CHECKOUTSTATUS',
				'EMAIL',
				'PAYERID',
				'COUNTRYCODE',
				'FIRSTNAME',
				'MIDDLENAME',
				'LASTNAME',
				'SUFFIX',
				// TODO: Don't know if this is the one? 'PAYMENTINFO_0_CURRENCYCODE',
				'PAYMENTREQUEST_0_AMT',
				'PAYMENTREQUEST_0_CURRENCYCODE',
				// Or this one? 'PAYMENTREQUEST_n_ITEMAMT'
				// FIXME: Are we able to override contribution_tracking_id like this?
				'PAYMENTREQUEST_0_INVNUM',
				'PAYMENTREQUEST_0_TRANSACTIONID',
				// Or, the L_ item?
			],
		];

		// https://developer.paypal.com/docs/classic/api/merchant/DoExpressCheckoutPayment_API_Operation_NVP/
		$this->transactions['DoExpressCheckoutPayment'] = [
			'request' => [
				'USER',
				'PWD',
				'VERSION',
				'METHOD',
				'TOKEN',
				'PAYERID',
				// TODO: MSGSUBID
				'PAYMENTREQUEST_0_PAYMENTACTION',
				'PAYMENTREQUEST_0_AMT',
				'PAYMENTREQUEST_0_CURRENCYCODE',
				// FIXME: This should be deprecated, and is only for back-compat.
				'PAYMENTREQUEST_0_CUSTOM',
				'PAYMENTREQUEST_0_DESC',
				'PAYMENTREQUEST_0_INVNUM',
				'PAYMENTREQUEST_0_ITEMAMT', // FIXME: Not clear why this is required.
				'PAYMENTREQUEST_0_PAYMENTACTION',
				'PAYMENTREQUEST_0_PAYMENTREASON',
			],
			'values' => [
				'USER' => $this->account_config['User'],
				'PWD' => $this->account_config['Password'],
				'VERSION' => self::API_VERSION,
				'METHOD' => 'DoExpressCheckoutPayment',
				'PAYMENTREQUEST_0_DESC' => WmfFramework::formatMessage( 'donate_interface-donation-description' ),
				'PAYMENTREQUEST_0_PAYMENTACTION' => 'Sale',
				'PAYMENTREQUEST_0_PAYMENTREASON' => 'None',
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
				if ( isset( $info['request'] ) ) {
					$info['request'][] = 'SIGNATURE';
					$info['values']['SIGNATURE'] = $this->account_config['Signature'];
				}
			}
		}
	}

	protected function getBasedir() {
		return __DIR__;
	}

	public function doPayment() {
		$this->config['transformers'][] = 'PaypalExpressReturnUrl';
		$this->data_transformers[] = new PaypalExpressReturnUrl();
		$this->stageData();
		if ( $this->getData_Unstaged_Escaped( 'recurring' ) ) {
			// Build the billing agreement and get a token to redirect.
			$resultData = $this->do_transaction( 'SetExpressCheckout_recurring' );
		} else {
			// Returns a token which we use to build a redirect URL into the
			// PayPal flow.
			$resultData = $this->do_transaction( 'SetExpressCheckout' );
		}

		return PaymentResult::fromResults(
			$resultData,
			$this->getFinalStatus()
		);
	}

	/**
	 * @return bool false, but we're kinda lying.
	 * We do need to DoExpressCheckoutPayment when donors return, but it's
	 * better to lose a few donations and show the thank you page than to
	 * risk duplicate donations and problems for donor services. We will
	 * eventually handle donors who return with no cookies by running an
	 * orphan rectifier like we do with GlobalCollect.
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
			case 'SetExpressCheckout':
			case 'SetExpressCheckout_recurring':
				$this->checkResponseAck( $response );
				$this->addResponseData( $this->unstageKeys( $response ) );
				$redirectUrl = $this->createRedirectUrl( $response['TOKEN'] );
				$this->transaction_response->setRedirect( $redirectUrl );
				break;
			case 'GetExpressCheckoutDetails':
				$this->checkResponseAck( $response );
				// Merge response into our transaction data.
				// TODO: Use getFormattedData instead.
				// FIXME: We don't want to allow overwriting of ctid, need a
				// list of protected fields.
				$this->addResponseData( $this->unstageKeys( $response ) );

				// Complete if payment already finalized
				if ( $this->isBatchProcessor() && $response['CHECKOUTSTATUS'] && $response['CHECKOUTSTATUS'] === 'PaymentActionCompleted' ) {
					$this->finalizeInternalStatus( FinalStatus::COMPLETE );
					break;
				}

				// PaymentActionNotInitiated means the donor hasn't done the payment at the PayPal side.
				// If we're running under the orphan slayer (batch mode), this is the end of the line.
				// Setting the status to TIMEOUT here will trigger the early return in processDonorReturn
				// so we don't barrel ahead with the SetExpressCheckout call without having a payerID.
				if ( $this->isBatchProcessor() && $response['CHECKOUTSTATUS'] && $response['CHECKOUTSTATUS'] === 'PaymentActionNotInitiated' ) {
					$this->finalizeInternalStatus( FinalStatus::TIMEOUT );
					break;
				}

				$this->runAntifraudFilters();
				if ( $this->getValidationAction() !== ValidationAction::PROCESS ) {
					$this->finalizeInternalStatus( FinalStatus::FAILED );
				}
				break;
			case 'DoExpressCheckoutPayment':
				$this->checkResponseAck( $response );

				$this->addResponseData( $this->unstageKeys( $response ) );
				$status = $this->findCodeAction( 'DoExpressCheckoutPayment',
					'PAYMENTINFO_0_ERRORCODE', $response['PAYMENTINFO_0_ERRORCODE'] );

				$this->finalizeInternalStatus( $status );
				$this->postProcessDonation();
				break;
			case 'ManageRecurringPaymentsProfileStatusCancel':
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
		$requestData = [];
		$requestData['gateway_session_id'] = $requestValues['token'];
		if (
			empty( $requestValues['PayerID'] )
		) {
			$this->logger->info( 'Notice missing PayerID in PaypalExpressAdapater::ProcessDonorReturn' );
		} else {
			$requestData['payer_id'] = $requestValues['PayerID'];
		}
		$this->addRequestData( $requestData );
		$resultData = $this->do_transaction( 'GetExpressCheckoutDetails' );
		// FixMe: What to do outside of batch processing?
		if ( $this->isBatchProcessor() && $this->getFinalStatus() == FinalStatus::TIMEOUT ) {
			return PaymentResult::newSuccess();
		}
		if ( !$resultData->getCommunicationStatus() ) {
			throw new ResponseProcessingException( 'Failed to get customer details',
				ErrorCode::UNKNOWN );
		}

		if ( $this->getFinalStatus() !== FinalStatus::COMPLETE ) {
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
				// One-time payment, or initial payment in a subscription.
				$resultData = $this->do_transaction( 'DoExpressCheckoutPayment' );
				if ( !$resultData->getCommunicationStatus() ) {
					$this->finalizeInternalStatus( FinalStatus::FAILED );
					return PaymentResult::newFailure();
				}
			}
		}
		return PaymentResult::fromResults(
			$this->getTransactionResponse(),
			$this->getFinalStatus()
		);
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
