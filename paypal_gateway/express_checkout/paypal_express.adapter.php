<?php

use Psr\Log\LogLevel;

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

	function defineAccountInfo() {
		$this->accountInfo = array();
	}

	// TODO: Get L_SHORTMESSAGE0 and L_LONGMESSAGE0
	function defineReturnValueMap() {
		$this->return_value_map = array();
		// 0: No errors
		$this->addCodeRange( 'DoExpressCheckoutPayment', 'PAYMENTINFO_0_ERRORCODE', FinalStatus::COMPLETE, 0 );
		// 10412: Payment has already been made for this InvoiceID.
		$this->addCodeRange( 'DoExpressCheckoutPayment', 'PAYMENTINFO_0_ERRORCODE', FinalStatus::FAILED, 10412 );
	}

	/**
	 * Use our own Order ID sequence.
	 */
	function defineOrderIDMeta() {
		$this->order_id_meta = array(
			'generate' => true,
			'ct_id' => true,
		);
	}

	function setGatewayDefaults() {}

	public function getCurlBaseOpts() {
		$opts = parent::getCurlBaseOpts();

		if ( $this->isCertificateAuthentication() ) {
			$opts[CURLOPT_SSLCERTTYPE] = 'PEM';
			$opts[CURLOPT_SSLCERT] = $this->account_config['CertificatePath'];
		}

		return $opts;
	}

	// TODO: Support "response" specification.
	function defineTransactions() {
		$this->transactions = array();

		// https://developer.paypal.com/docs/classic/api/merchant/SetExpressCheckout_API_Operation_NVP/
		$this->transactions['SetExpressCheckout'] = array(
			'request' => array(
				'USER',
				'PWD',
				//'SIGNATURE', // See below.
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
				//'L_PAYMENTREQUEST_0_ITEMCATEGORY0',
				'PAYMENTREQUEST_0_AMT',
				'PAYMENTREQUEST_0_CURRENCYCODE',
				// FIXME: This should be deprecated, and is only for back-compat.
				'PAYMENTREQUEST_0_CUSTOM',
				'PAYMENTREQUEST_0_DESC',
				'PAYMENTREQUEST_0_INVNUM',
				'PAYMENTREQUEST_0_ITEMAMT', // FIXME: Not clear why this is required.
				'PAYMENTREQUEST_0_PAYMENTACTION',
				'PAYMENTREQUEST_0_PAYMENTREASON',
				// TODO: Investigate why can we give this as an input:
				// PAYMENTREQUEST_n_TRANSACTIONID
				// TODO: BUYEREMAILOPTINENABLE=1
			),
			'values' => array(
				'USER' => $this->account_config['User'],
				'PWD' => $this->account_config['Password'],
				'VERSION' => self::API_VERSION,
				'METHOD' => 'SetExpressCheckout',
				'CANCELURL' => ResultPages::getCancelPage( $this ),
				'REQCONFIRMSHIPPING' => 0,
				'NOSHIPPING' => 1,
				'L_PAYMENTREQUEST_0_DESC0' => WmfFramework::formatMessage( 'donate_interface-donation-description' ),
				//'L_PAYMENTREQUEST_0_ITEMCATEGORY0' => 'Digital',
				'PAYMENTREQUEST_0_DESC' => WmfFramework::formatMessage( 'donate_interface-donation-description' ),
				'PAYMENTREQUEST_0_PAYMENTACTION' => 'Sale',
				'PAYMENTREQUEST_0_PAYMENTREASON' => 'None',
			),
			'response' => array(
				'TOKEN',
			),
		);

		// https://developer.paypal.com/docs/classic/api/merchant/SetExpressCheckout_API_Operation_NVP/
		$this->transactions['SetExpressCheckout_recurring'] = array(
			'request' => array(
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
				//'L_PAYMENTREQUEST_0_DESC0',
				'L_PAYMENTREQUEST_0_NAME0',
				'L_PAYMENTREQUEST_0_QTY0',
				'MAXAMT',
				'PAYMENTREQUEST_0_AMT',
				'PAYMENTREQUEST_0_CURRENCYCODE',
				// // FIXME: This should be deprecated, and is only for back-compat.
				// 'PAYMENTREQUEST_0_CUSTOM',
				//'PAYMENTREQUEST_0_DESC',
				//'PAYMENTREQUEST_0_INVNUM',
				'PAYMENTREQUEST_0_ITEMAMT',
				//'PAYMENTREQUEST_0_PAYMENTACTION',
				//'PAYMENTREQUEST_0_PAYMENTREASON',
				// // TODO: Investigate why would give this as an input:
				// // PAYMENTREQUEST_n_TRANSACTIONID
			),
			'values' => array(
				'USER' => $this->account_config['User'],
				'PWD' => $this->account_config['Password'],
				'VERSION' => self::API_VERSION,
				'METHOD' => 'SetExpressCheckout',
				'CANCELURL' => ResultPages::getCancelPage( $this ),
				'REQCONFIRMSHIPPING' => 0,
				'NOSHIPPING' => 1,
				'L_BILLINGTYPE0' => 'RecurringPayments',
				// FIXME: Sad!  The thank-you message would be perfect here,
				// but it seems the exlamation mark is not supported, even when
				// urlencoded properly.
				//'L_BILLINGAGREEMENTDESCRIPTION0' => WmfFramework::formatMessage( 'donate_interface-donate-error-thank-you-for-your-support' ),
				'L_BILLINGAGREEMENTDESCRIPTION0' => WmfFramework::formatMessage( 'donate_interface-monthly-donation-description' ),
				'L_PAYMENTREQUEST_0_DESC0' => WmfFramework::formatMessage( 'donate_interface-monthly-donation-description' ),
				//'L_PAYMENTREQUEST_0_ITEMCATEGORY0' => 'Digital',
				'L_PAYMENTREQUEST_0_NAME0' => WmfFramework::formatMessage( 'donate_interface-monthly-donation-description' ),
				'L_PAYMENTREQUEST_0_QTY0' => 1,
				'PAYMENTREQUEST_0_DESC' => WmfFramework::formatMessage( 'donate_interface-monthly-donation-description' ),
				'PAYMENTREQUEST_0_PAYMENTACTION' => 'Sale',
				'PAYMENTREQUEST_0_PAYMENTREASON' => 'None',
			),
			'response' => array(
				'TOKEN',
			),
		);

		// Incoming parameters after returning from the PayPal workflow
		$this->transactions['ProcessReturn'] = array(
			'request' => array(
				'token',
				'PayerID',
			),
		);

		// https://developer.paypal.com/docs/classic/api/merchant/GetExpressCheckoutDetails_API_Operation_NVP/
		$this->transactions['GetExpressCheckoutDetails'] = array(
			'request' => array(
				'USER',
				'PWD',
				'VERSION',
				'METHOD',
				'TOKEN',
			),
			'values' => array(
				'USER' => $this->account_config['User'],
				'PWD' => $this->account_config['Password'],
				'VERSION' => self::API_VERSION,
				'METHOD' => 'GetExpressCheckoutDetails',
			),
			'response' => array(
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
			),
		);

		// https://developer.paypal.com/docs/classic/api/merchant/DoExpressCheckoutPayment_API_Operation_NVP/
		$this->transactions['DoExpressCheckoutPayment'] = array(
			'request' => array(
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
			),
			'values' => array(
				'USER' => $this->account_config['User'],
				'PWD' => $this->account_config['Password'],
				'VERSION' => self::API_VERSION,
				'METHOD' => 'DoExpressCheckoutPayment',
				'PAYMENTREQUEST_0_DESC' => WmfFramework::formatMessage( 'donate_interface-donation-description' ),
				'PAYMENTREQUEST_0_PAYMENTACTION' => 'Sale',
				'PAYMENTREQUEST_0_PAYMENTREASON' => 'None',
			),
		);

		// https://developer.paypal.com/docs/classic/api/merchant/CreateRecurringPaymentsProfile_API_Operation_NVP/
		$this->transactions['CreateRecurringPaymentsProfile'] = array(
			'request' => array(
				'USER',
				'PWD',
				'VERSION',
				'METHOD',
				'TOKEN',
				'DESC',
				//'L_PAYMENTREQUEST_0_AMT0',
				//'L_PAYMENTREQUEST_0_DESC0',
				//'L_PAYMENTREQUEST_n_NAME0',
				//'L_PAYMENTREQUEST_0_ITEMCATEGORY0',
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
			),
			'values' => array(
				'USER' => $this->account_config['User'],
				'PWD' => $this->account_config['Password'],
				'VERSION' => self::API_VERSION,
				'METHOD' => 'CreateRecurringPaymentsProfile',
				'DESC' => WmfFramework::formatMessage( 'donate_interface-monthly-donation-description' ),
				//'L_PAYMENTREQUEST_0_DESC0' => WmfFramework::formatMessage( 'donate_interface-monthly-donation-description' ),
				//'L_PAYMENTREQUEST_0_ITEMCATEGORY0' => 'Digital',
				//'L_PAYMENTREQUEST_n_NAME0' => WmfFramework::formatMessage( 'donate_interface-monthly-donation-description' ),
				// Do not charge for the balance if payments fail.
				'AUTOBILLOUTAMT' => 'NoAutoBill',
				'BILLINGPERIOD' => 'Month',
				'BILLINGFREQUENCY' => 1,
				'TOTALBILLINGCYCLES' => 0, // Forever.
				'MAXFAILEDPAYMENTS' => 3,
			),
			'response' => array(
				# FIXME: Make sure this gets passed as subscription_id in the message
				'PROFILEID',
				'PROFILESTATUS',
				'TRANSACTIONID',
			),
		);

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

	function getBasedir() {
		return __DIR__;
	}

	public function doPayment() {
		if ( $this->getData_Unstaged_Escaped( 'recurring' ) ) {
			// Build the billing agreement and get a token to redirect.
			$resultData = $this->do_transaction( 'SetExpressCheckout_recurring' );
		} else {
			// Returns a token which we use to build a redirect URL into the
			// PayPal flow.
			$resultData = $this->do_transaction( 'SetExpressCheckout' );
		}

		$resultAction = PaymentResult::fromResults(
			$resultData,
			$this->getFinalStatus()
		);

		if ( $resultAction->getRedirect() ) {
			// FIXME: This stuff should be base behavior for handling redirect responses.
			$this->logPaymentDetails();
			// Don't worry about saving a limbo message, we know next to nothing yet.
			// TODO: need ffname hack?
		}

		return $resultAction;
	}

	/**
	 * TODO: DRY with AstroPay; handle ProcessReturn like other transactions
	 */
	public function processResponse( $response ) {
		// May need to initialize transaction_response, as we can be called by
		// GatewayPage to process responses outside of do_transaction
		if ( !$this->transaction_response ) {
			$this->transaction_response = new PaymentTransactionResponse();
		}
		$this->transaction_response->setData( $response );
		// FIXME: I'm not sure why we're responsible for failing the
		// transaction.  If not, we can omit the try/catch here.
		try {
			if ( !$response ) {
				throw new ResponseProcessingException(
					'Missing or badly formatted response',
					ResponseCodes::NO_RESPONSE
				);
			}

			switch ( $this->getCurrentTransaction() ) {
			case 'CreateRecurringPaymentsProfile':
				$this->checkResponseAck( $response );

				// Grab the subscription ID.
				$this->addResponseData( $this->unstageKeys( $response ) );

				// FIXME: Not a satisfying ending.  Parse the PROFILESTATUS
				// response and sort it into complete or pending.
				$this->finalizeInternalStatus( FinalStatus::COMPLETE );
				$this->runPostProcessHooks();
				// FIXME: deprecated
				$this->deleteLimboMessage( 'pending' );
				break;
			case 'SetExpressCheckout':
			case 'SetExpressCheckout_recurring':
				$this->checkResponseAck( $response );
				$this->transaction_response->setRedirect(
					$this->account_config['RedirectURL'] . $response['TOKEN'] );
				break;
			case 'ProcessReturn':
				// FIXME: Silly that we have to wedge the response controller in
				// here with tail recursion.  And fragile, because we have to
				// remember about reentry.
				$this->addRequestData( array(
					'ec_token' => $response['token'],
					'payer_id' => $response['PayerID'],
				) );
				$resultData = $this->do_transaction( 'GetExpressCheckoutDetails' );
				if ( !$resultData->getCommunicationStatus() ) {
					throw new ResponseProcessingException( 'Failed to get customer details',
						ResponseCodes::UNKNOWN );
				}

				// One-time payment, or initial payment in a subscription.
				// XXX: This shouldn't finalize the transaction.
				$resultData = $this->do_transaction( 'DoExpressCheckoutPayment' );
				if ( !$resultData->getCommunicationStatus() ) {
					$this->finalizeInternalStatus( FinalStatus::FAILED );
					break;
				}

				if ( $this->getData_Unstaged_Escaped( 'recurring' ) ) {
					// Set up recurring billing agreement.
					$this->addRequestData( array(
						// Start in a month; we're making today's payment as an one-time charge.
						'date' => time() + 30 * 24 * 3600, // FIXME: calendar month
					) );
					$resultData = $this->do_transaction( 'CreateRecurringPaymentsProfile' );
					if ( !$resultData->getCommunicationStatus() ) {
						throw new ResponseProcessingException(
							'Failed to create a recurring profile', ResponseCodes::UNKNOWN );
					}
				}
				break;
			case 'GetExpressCheckoutDetails':
				$this->checkResponseAck( $response );

				// Merge response into our transaction data.
				// TODO: Use getFormattedData instead.
				// FIXME: We don't want to allow overwriting of ctid, need a
				// blacklist of protected fields.
				$this->addResponseData( $this->unstageKeys( $response ) );

				$this->runAntifraudHooks();
				if ( $this->getValidationAction() !== 'process' ) {
					$this->finalizeInternalStatus( FinalStatus::FAILED );
				}
				break;
			case 'DoExpressCheckoutPayment':
				$this->checkResponseAck( $response );

				$this->addResponseData( $this->unstageKeys( $response ) );
				// FIXME: Silly.
				$this->transaction_response->setGatewayTransactionId(
					$this->getData_Unstaged_Escaped( 'gateway_txn_id' ) );
				$status = $this->findCodeAction( 'DoExpressCheckoutPayment',
					'PAYMENTINFO_0_ERRORCODE', $response['PAYMENTINFO_0_ERRORCODE'] );
				// TODO: Can we do this from do_transaction instead, or at least protect with !recurring...
				$this->finalizeInternalStatus( $status );
				$this->runPostProcessHooks();
				// FIXME: deprecated
				$this->deleteLimboMessage( 'pending' );
				break;
			}

			if ( !$this->transaction_response->getCommunicationStatus() ) {
				// TODO: so much boilerplate...  Just throw an exception subclass.
				$logme = 'Failed response for Order ID ' . $this->getData_Unstaged_Escaped( 'order_id' );
				$this->logger->error( $logme );
				$this->transaction_response->setErrors( array(
					'internal-0000' => array (
						'message' => $this->getErrorMapByCodeAndTranslate( 'internal-0000' ),
						'debugInfo' => $logme,
						'logLevel' => LogLevel::ERROR
					)
				) );
			}
		} catch ( Exception $ex ) {
			// TODO: Parse the API error fields and log them.
			$this->logger->error( "Failure detected in " . json_encode( $response ) );
			$this->finalizeInternalStatus( FinalStatus::FAILED );
			throw $ex;
		}
	}

	/**
	 * Shared snippet to parse the ACK response field and store it as
	 * communication status.
	 *
	 * @throws ResponseProcessingException
	 */
	protected function checkResponseAck( $response ) {
		if ( isset( $response['ACK'] ) && $response['ACK'] === 'Success' ) {
			$this->transaction_response->setCommunicationStatus( true );
		} else {
			throw new ResponseProcessingException( "Failure response", $response['ACK'] );
		}
	}
}
