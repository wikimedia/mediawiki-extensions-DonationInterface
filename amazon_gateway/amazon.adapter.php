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

class AmazonAdapter extends GatewayAdapter {
	const GATEWAY_NAME = 'Amazon';
	const IDENTIFIER = 'amazon';
	public $communication_type = 'xml';
	const GLOBAL_PREFIX = 'wgAmazonGateway';

	function __construct( $options = array() ) {
		parent::__construct( $options );

		if ($this->getData_Unstaged_Escaped( 'payment_method' ) == null ) {
			$this->addRequestData(
				array( 'payment_method' => 'amazon' )
			);
		}
	}

	function defineStagedVars() {}
	function defineVarMap() {
		$this->var_map = array(
			"amount" => "amount",
			"transactionAmount" => "amount",
			"transactionId" => "gateway_txn_id",
			"status" => "gateway_status",
			"buyerEmail" => "email",
			"transactionDate" => "date_collect",
			"buyerName" => "fname", // This is dealt with in processResponse()
			"errorMessage" => "error_message",
			"paymentMethod" => "payment_submethod",
			"referenceId" => "contribution_tracking_id",
		);
	}

	function defineAccountInfo() {
		//XXX since this class actually accesses two different endpoints,
		// the usefulness of this function is uncertain.  In other words,
		// account info is transaction-specific.  We use account_config
		// instead
		$this->accountInfo = array();
	}
	function defineReturnValueMap() {}
	function defineDataConstraints() {}
	function defineOrderIDMeta() {
		$this->order_id_meta = array (
			'generate' => TRUE,
		);
	}
	function setGatewayDefaults() {}

	public function defineErrorMap() {

		$this->error_map = array(
			// Internal messages
			'internal-0000' => 'donate_interface-processing-error', // Failed failed pre-process checks.
			'internal-0001' => 'donate_interface-processing-error', // Transaction could not be processed due to an internal error.
			'internal-0002' => 'donate_interface-processing-error', // Communication failure
		);
	}

	function defineTransactions() {
		$this->transactions = array();
		$this->transactions[ 'Donate' ] = array(
			'request' => array(
				'accessKey',
				'amount',
				'collectShippingAddress',
				'description',
				'immediateReturn',
				'ipnUrl',
				'returnUrl',
				'isDonationWidget',
				'processImmediate',
				'referenceId',
				//'signature',
				'signatureMethod',
				'signatureVersion',
			),
			'values' => array(
				'accessKey' => $this->account_config[ 'AccessKey' ],
				'collectShippingAddress' => '0',
				'description' => WmfFramework::formatMessage( 'donate_interface-donation-description' ),
				'immediateReturn' => '1',
				'ipnUrl' => $this->account_config['IpnOverride'],
				'isDonationWidget' => '1',
				'processImmediate' => '1',
				'signatureMethod' => 'HmacSHA256',
				'signatureVersion' => '2',
			),
			'redirect' => TRUE,
		);

		$this->transactions[ 'DonateMonthly' ] = array(
			'request' => array(
				'accessKey',
				'amount',
				'collectShippingAddress',
				'description',
				'immediateReturn',
				'ipnUrl',
				'processImmediate',
				'recurringFrequency',
				'referenceId',
				'returnUrl',
				//'signature',
				'signatureMethod',
				'signatureVersion',
				//'subscriptionPeriod',
			),
			'values' => array(
				// FIXME: There is magick available if the names match.
				'accessKey' => $this->account_config[ 'AccessKey' ],
				'collectShippingAddress' => '0',
				'description' => WmfFramework::formatMessage( 'donate_interface-monthly-donation-description' ),
				'immediateReturn' => '1',
				'ipnUrl' => $this->account_config['IpnOverride'],
				'processImmediate' => '1',
				'recurringFrequency' => "1 month",
				'signatureMethod' => "HmacSHA256",
				'signatureVersion' => "2",
				// FIXME: this is the documented default, but passing it explicitly is buggy
				//'subscriptionPeriod' => "forever",
			),
			'redirect' => TRUE,
		);

		$this->transactions[ 'VerifySignature' ] = array(
			'request' => array(
				'Action',
				'HttpParameters',
				'UrlEndPoint',
				'Version',
				//'Signature',
				'SignatureMethod',
				'SignatureVersion',
				'AWSAccessKeyId',
				'Timestamp',
			),
			'values' => array(
				'Action' => "VerifySignature",
				'AWSAccessKeyId' => $this->account_config[ 'AccessKey' ],
				'UrlEndPoint' => $this->getGlobal( "ReturnURL" ),
				'Version' => "2010-08-28",
				'SignatureMethod' => "HmacSHA256",
				'SignatureVersion' => "2",
				'Timestamp' => date( 'c' ),
			),
			'url' => $this->getGlobal( "FpsURL" ),
		);

		$this->transactions[ 'ProcessAmazonReturn' ] = array(
			'request' => array(),
			'values' => array(),
		);
	}

	public function definePaymentMethods() {
		$this->payment_methods = array(
			'amazon' => array(),
		);

		$this->payment_submethods = array(
			'amazon_cc' => array(),
			'amazon_wallet' => array(),
		);
	}

	protected function buildRequestParams() {
		$queryparams = parent::buildRequestParams();
		ksort( $queryparams );

		return $queryparams;
	}

	function do_transaction( $transaction ) {
		global $wgRequest, $wgOut;
		$this->session_addDonorData();

		$this->setCurrentTransaction( $transaction );

		$override_url = $this->transaction_option( 'url' );
		if ( !empty( $override_url ) ) {
			$this->url = $override_url;
		}
		else {
			$this->url = $this->getGlobal( "URL" );
		}

		switch ( $transaction ) {
		case 'Donate':
		case 'DonateMonthly':
			$return_url = $this->getGlobal( 'ReturnURL' );
			//check if ReturnURL already has a query string			
			$return_query = parse_url( $return_url, PHP_URL_QUERY );
			$return_url .= ( $return_query ? '&' : '?' );
			$return_url .= "ffname=amazon&order_id={$this->getData_Unstaged_Escaped( 'order_id' )}";
			$this->transactions[ $transaction ][ 'values' ][ 'returnUrl' ] = $return_url;
			break;
		case 'VerifySignature':
			$request_params = $wgRequest->getValues();
			unset( $request_params[ 'title' ] );
			$incoming = http_build_query( $request_params, '', '&' );
			$this->transactions[ $transaction ][ 'values' ][ 'HttpParameters' ] = $incoming;
			$this->log( "received callback from amazon with: $incoming", LOG_DEBUG );
			break;
		}

		// TODO this will move to a staging function once FR#507 is deployed
		$query = $this->buildRequestParams();
		$parsed_uri = parse_url( $this->url );
		$signature = $this->signRequest( $parsed_uri[ 'host' ], $parsed_uri[ 'path' ], $query );

		switch ( $transaction ) {
			case 'Donate':
			case 'DonateMonthly':
				$query_str = $this->encodeQuery( $query );
				$this->log( "At $transaction, redirecting with query string: $query_str", LOG_DEBUG );
				
				//always have to do this before a redirect. 
				$this->dataObj->saveContributionTrackingData();

				//@TODO: This shouldn't be happening here. Oh Amazon... Why can't you be more like PayPalAdapter?
				$wgOut->redirect("{$this->getGlobal( "URL" )}?{$query_str}&signature={$signature}");
				return;

			case 'VerifySignature':
				// We don't currently use this. In fact we just ignore the return URL signature.
				// However, it's perfectly good code and we may go back to using it at some point
				// so I didn't want to remove it.
				$query_str = $this->encodeQuery( $query );
				$this->url .= "?{$query_str}&Signature={$signature}";

				$this->log( "At $transaction, query string: $query_str", LOG_DEBUG );

				parent::do_transaction( $transaction );

				if ( $this->getFinalStatus() == 'complete' ) {
					$this->unstaged_data = $this->dataObj->getDataEscaped(); // XXX not cool.
					$this->runPostProcessHooks();
					$this->doLimboStompTransaction( true );
				}
				return;

			case 'ProcessAmazonReturn':
				// What we need to do here is make sure
				$this->addDataFromURI();
				$this->analyzeReturnStatus();
				return;

			default:
				$this->log( "At $transaction; THIS IS NOT DEFINED!", LOG_CRIT );
				$this->finalizeInternalStatus( 'failed' );
				return;
		}
	}

	static function getCurrencies() {
		// See https://payments.amazon.com/sdui/sdui/about?nodeId=73479#feat_countries
		return array(
			'USD',
		);
	}

	/**
	 * Looks at the 'status' variable in the amazon return URL get string and places the data
	 * in the appropriate Final Status and sends to STOMP.
	 */
	protected function analyzeReturnStatus() {
		// We only want to analyze this if we don't already have a Final Status... Therefore we
		// won't overwrite things.
		if ( $this->getFinalStatus() === false ) {

			$txnid = $this->dataObj->getVal_Escaped( 'gateway_txn_id' );
			$this->setTransactionResult( $txnid, 'gateway_txn_id' );

			// Second make sure that the inbound request had a matching outbound session. If it
			// doesn't we drop it.
			if ( !self::session_hasDonorData( 'order_id', $this->getData_Unstaged_Escaped( 'order_id' ) ) ) {

				// We will however log it if we have a seemingly valid transaction id
				if ( $txnid != null ) {
					$ctid = $this->getData_Unstaged_Escaped( 'contribution_tracking_id' );
					$this->log( "$ctid failed orderid verification but has txnid '$txnid'. Investigation required.", LOG_ALERT );
					if ( $this->getGlobal( 'UseOrderIdValidation' ) ) {
						$this->finalizeInternalStatus( 'failed' );
						return;
					}
				} else {
					$this->finalizeInternalStatus( 'failed' );
					return;
				}
			}

			// Third: we did have an outbound request; so let's look at what amazon is telling us
			// about the transaction.
			// todo: lots of other statuses we can interpret
			// see: http://docs.amazonwebservices.com/AmazonSimplePay/latest/ASPAdvancedUserGuide/ReturnValueStatusCodes.html
			$this->log( "Transaction $txnid returned with status " . $this->dataObj->getVal_Escaped( 'gateway_status' ), LOG_INFO );
			switch ( $this->dataObj->getVal_Escaped( 'gateway_status' ) ) {
				case 'PS':  // Payment success
					$this->finalizeInternalStatus( 'complete' );
					$this->doStompTransaction();
					break;

				case 'PI':  // Payment initiated, it will complete later
					$this->finalizeInternalStatus( 'pending' );
					$this->doStompTransaction();
					break;

				case 'SS':  // Subscription success -- processing handled by the IPN listener
					$this->finalizeInternalStatus('complete');
					break;

				case 'SI':  // Subscription initiated -- processing handled by the IPN listener
					$this->finalizeInternalStatus('pending');
					break;

				case 'PF':  // Payment failed
				case 'SF':  // Subscription failed
				case 'SE':  // This one is interesting; service failure... can we do something here?
				default:	// All other errorz
					$status = $this->dataObj->getVal_Escaped( 'gateway_status' );
					$errString = $this->dataObj->getVal_Escaped( 'error_message' );
					$this->log( "Transaction $txnid failed with ($status) $errString", LOG_INFO );
					$this->finalizeInternalStatus( 'failed' );
					break;
			}
		} else {
			$this->log( 'Apparently we attempted to process a transaction that already had a final status... Odd', LOG_ERR );
		}
	}

	/**
	 * Adds translated data from the URI string into donation data
	 */
	function addDataFromURI() {
		global $wgRequest;

		// Obtain data parameters for STOMP message injection
		//n.b. these request vars were from the _previous_ api call
		$add_data = array();
		foreach ( $this->var_map as $gateway_key => $normal_key ) {
			$value = $wgRequest->getVal( $gateway_key, null );
			if ( !empty( $value ) ) {
				// Deal with some fun special cases
				switch ( $gateway_key ) {
					case 'transactionAmount':
						list ($currency, $amount) = explode( ' ', $value );
						$add_data['currency'] = $currency;
						$add_data['amount'] = $amount;
						break;

					case 'buyerName':
						list ($fname, $lname) = explode( ' ', $value, 2 );
						$add_data['fname'] = $fname;
						$add_data['lname'] = $lname;
						break;
					case 'paymentMethod':
						$submethods = array (
							'Credit Card' => 'amazon_cc',
							'Amazon Payments Balance' => 'amazon_wallet',
						);
						if ( array_key_exists( $value, $submethods ) ) {
							$add_data['payment_submethod'] = $submethods[$value];
						} else {
							//We don't rely on this anywhere serious, but I want to know about it anyway.
							$this->log( "Amazon just coughed up a surprise payment submethod of '$value'.", LOG_ERR );
							$add_data['payment_submethod'] = 'unknown';
						}
						break;
					default:
						$add_data[ $normal_key ] = $value;
						break;
				}
			}
		}
		//TODO: consider prioritizing the session vars
		$this->addResponseData( $add_data ); //using the gateway's addData function restages everything

		$txnid = $this->dataObj->getVal_Escaped( 'gateway_txn_id' );
		$email = $this->dataObj->getVal_Escaped( 'email' );

		$this->log( "Added data to session for txnid $txnid. Now serving email $email.", LOG_INFO );
	}

	function processResponse( $response, &$retryVars = null ) {
		if ( ( $this->getCurrentTransaction() == 'VerifySignature' ) && ( $response['data'] == true ) ) {
			$this->log( "Transaction failed in response data verification.", LOG_INFO );
			$this->finalizeInternalStatus( 'failed' );
		}
	}

	function encodeQuery( $params ) {
		ksort( $params );
		$query = array();
		foreach ( $params as $key => $value ) {
			$encoded = str_replace( "%7E", "~", rawurlencode( $value ) );
			$query[] = $key . "=" . $encoded;
		}
		return implode( "&", $query );
	}

	function signRequest( $host, $path, &$params ) {
		unset( $params['signature'] );

		$secret_key = $this->account_config[ "SecretKey" ];

		$query_str = $this->encodeQuery( $params );
		$path_encoded = str_replace( "%2F", "/", rawurlencode( $path ) );

		$message = "GET\n{$host}\n{$path_encoded}\n{$query_str}";

		return rawurlencode( base64_encode( hash_hmac( 'sha256', $message, $secret_key, TRUE ) ) );
	}

	/**
	 * We're never POST'ing, just send a Content-type that won't confuse Amazon.
	 */
	function getCurlBaseHeaders() {
		$headers = array(
			'Content-Type: text/html; charset=utf-8',
		);
		return $headers;
	}

	function getCurlBaseOpts() {
		$opts = parent::getCurlBaseOpts();

        $opts[CURLOPT_SSL_VERIFYPEER] = true;
        $opts[CURLOPT_SSL_VERIFYHOST] = 2;
        $opts[CURLOPT_CAINFO] = __DIR__ . "/ca-bundle.crt";
        $opts[CURLOPT_CAPATH] = __DIR__ . "/ca-bundle.crt";

		return $opts;
	}

	function getResponseData( $response ) {
		// The XML string isn't really all that useful, so just return TRUE if the signature
		// was verified
		if ( $this->getCurrentTransaction() == 'VerifySignature' ) {
			$statuses = $response->getElementsByTagName( 'VerificationStatus' );
			foreach ( $statuses as $node ) {
				if ( strtolower( $node->nodeValue ) == 'success' ) {
					return TRUE;
				}
			}
		}

		return FALSE;
	}

	public function getResponseStatus( $response ) {
		$aok = false;

		if ( $this->getCurrentTransaction() == 'VerifySignature' ) {

			foreach ( $response->getElementsByTagName( 'VerifySignatureResult' ) as $node ) {
				// All we care about is that the node exists
				$aok = true;
			}
		}

		return $aok;
	}

	// @todo FIXME: This doesn't go anywhere.
	function getResponseErrors( $response ) {
		$errors = array( );
		foreach ( $response->getElementsByTagName( 'Error' ) as $node ) {
			$code = '';
			$message = '';
			foreach ( $node->childNodes as $childnode ) {
				if ( $childnode->nodeName === "Code" ) {
					$code = $childnode->nodeValue;
				}
				if ( $childnode->nodeName === "Message" ) {
					$message = $childnode->nodeValue;
				}
			}
		}
		return $errors;
	}

	/**
	 * For the Amazon adapter this is a huge hack! Because we build the transaction differently.
	 * Amazon expectings things to them in the query string, and back via XML. Go figure.
	 *
	 * In any case; do_transaction() does the heavy lifting. And this does nothing; which is
	 * required because otherwise we throw a bunch of silly XML at Amazon that it just ignores.
	 *
	 * @return string|void Nothing :)
	 */
	protected function buildRequestXML( $rootElement = 'XML', $encoding = 'UTF-8' ) {
		return '';
	}
}
