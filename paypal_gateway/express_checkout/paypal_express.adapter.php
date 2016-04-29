<?php

use Psr\Log\LogLevel;

/**
 * PayPal Express Checkout name value pair integration
 *
 * https://developer.paypal.com/docs/classic/express-checkout/overview-ec/
 * https://developer.paypal.com/docs/classic/products/
 * https://developer.paypal.com/docs/classic/express-checkout/ht_ec-singleItemPayment-curl-etc/
 * https://developer.paypal.com/docs/classic/api/gs_PayPalAPIs/
 */
class PaypalExpressAdapter extends GatewayAdapter {
	const GATEWAY_NAME = 'Paypal Express Checkout';
	const IDENTIFIER = 'paypal_ec';
	const GLOBAL_PREFIX = 'wgPaypalExpressGateway';

	const API_VERSION = 124;

	public function getCommunicationType() {
		return 'namevalue';
	}

	public function getResponseType() {
		return 'query_string';
	}

	public function getFormClass() {
		return 'Gateway_Form_Mustache';
	}

	function defineStagedVars() { }

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
	 * Shared snippet to parse and the ACK response field and store it as
	 * communication status.
	 */
	protected function processAckResponse( $response ) {
		if ( isset( $response['ACK'] ) && $response['ACK'] === 'Success' ) {
			$this->transaction_response->setCommunicationStatus( true );
			return true;
		} else {
			return false;
		}
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

	function defineTransactions() {
		$this->transactions = array();

		// https://developer.paypal.com/docs/classic/api/merchant/SetExpressCheckout_API_Operation_NVP/
		$this->transactions['SetExpressCheckout'] = array(
			'request' => array(
				'USER',
				'PWD',
				'SIGNATURE',
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
			),
			'values' => array(
				'USER' => $this->account_config['User'],
				'PWD' => $this->account_config['Password'],
				'SIGNATURE' => $this->account_config['Signature'],
				'VERSION' => self::API_VERSION,
				'METHOD' => 'SetExpressCheckout',
				'CANCELURL' => ResultPages::getCancelPage( $this ),
				'REQCONFIRMSHIPPING' => 0,
				'NOSHIPPING' => 1,
				'L_PAYMENTREQUEST_0_DESC0' => WmfFramework::formatMessage( 'donate_interface-donation-description' ),
				'PAYMENTREQUEST_0_DESC' => WmfFramework::formatMessage( 'donate_interface-donation-description' ),
				'PAYMENTREQUEST_0_PAYMENTACTION' => 'Sale',
				'PAYMENTREQUEST_0_PAYMENTREASON' => 'None',
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
				'SIGNATURE',
				'VERSION',
				'METHOD',
				'TOKEN',
			),
			'values' => array(
				'USER' => $this->account_config['User'],
				'PWD' => $this->account_config['Password'],
				'SIGNATURE' => $this->account_config['Signature'],
				'VERSION' => self::API_VERSION,
				'METHOD' => 'GetExpressCheckoutDetails',
			),
		);

		// https://developer.paypal.com/docs/classic/api/merchant/DoExpressCheckoutPayment_API_Operation_NVP/
		$this->transactions['DoExpressCheckoutPayment'] = array(
			'request' => array(
				'USER',
				'PWD',
				'SIGNATURE',
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
				'SIGNATURE' => $this->account_config['Signature'],
				'VERSION' => self::API_VERSION,
				'METHOD' => 'DoExpressCheckoutPayment',
				'PAYMENTREQUEST_0_DESC' => WmfFramework::formatMessage( 'donate_interface-donation-description' ),
				'PAYMENTREQUEST_0_PAYMENTACTION' => 'Sale',
				'PAYMENTREQUEST_0_PAYMENTREASON' => 'None',
			),
		);
	}

	function getBasedir() {
		return __DIR__;
	}

	public function doPayment() {
		if ( $this->getData_Unstaged_Escaped( 'recurring' ) ) {
			// TODO: implement
			throw new Exception( "Recurring not implemented yet." );
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
			$this->setLimboMessage( 'pending' );
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
		if ( !$response ) {
			throw new ResponseProcessingException(
				'Missing or badly formatted response',
				ResponseCodes::NO_RESPONSE
			);
		}

		switch ( $this->getCurrentTransaction() ) {
		case 'SetExpressCheckout':
			if ( !$this->processAckResponse( $response ) ) {
				// TODO: Here and below, parse the API error fields and log.
				$this->logger->error( "Failed to set up payment, " . json_encode( $response ) );
				$this->finalizeInternalStatus( FinalStatus::FAILED );
				break;
			}
			$this->transaction_response->setRedirect( $this->account_config['RedirectURL'] . $response['TOKEN'] );
			break;
		case 'ProcessReturn':
			// FIXME: Silly that we have to wedge the response controller in here with tail recursion.
			$this->addRequestData( array(
				'ec_token' => $response['token'],
				'donor_id' => $response['PayerID'],
			) );
			$resultData = $this->do_transaction( 'GetExpressCheckoutDetails' );
			if ( $resultData->getCommunicationStatus() ) {
				$this->do_transaction( 'DoExpressCheckoutPayment' );
			}
			break;
		case 'GetExpressCheckoutDetails':
			if ( !$this->processAckResponse( $response ) ) {
				$this->logger->error( "Failed to get details, " . json_encode( $response ) );
				$this->finalizeInternalStatus( FinalStatus::FAILED );
				break;
			}

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
			if ( !$this->processAckResponse( $response ) ) {
				// FIXME: is response already logged?
				$this->logger->error( "Failed to complete payment, " . json_encode( $response ) );
				$this->finalizeInternalStatus( FinalStatus::FAILED );
				break;
			}
			$this->addResponseData( $this->unstageKeys( $response ) );
			// FIXME: Silly.
			$this->transaction_response->setGatewayTransactionId( $this->getData_Unstaged_Escaped( 'gateway_txn_id' ) );
			$status = $this->findCodeAction( 'DoExpressCheckoutPayment',
				'PAYMENTINFO_0_ERRORCODE', $response['PAYMENTINFO_0_ERRORCODE'] );
			$this->finalizeInternalStatus( $status );
			$this->runPostProcessHooks();
			// FIXME: deprecated
			$this->deleteLimboMessage( 'pending' );
			break;
		}

		if ( !$this->transaction_response->getCommunicationStatus() ) {
			// TODO: so much boilerplate...  Just throw an exception subclass.
			$logme = 'Failed response for Order ID ' .  $this->getData_Unstaged_Escaped( 'order_id' );
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

}
