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
	const COMMUNICATION_TYPE = 'xml';
	const GLOBAL_PREFIX = 'wgAmazonGateway';

	function getResponseErrors( $response ) {}
	function getResponseData( $response ) {}
	function defineStagedVars() {}
	function defineErrorMap() {}
	function defineVarMap() {
		$this->var_map = array(
			"amount" => "amount",
			"transactionAmount" => "amount",
			"transactionId" => "gateway_txn_id",
			"status" => "gateway_status",
			"buyerEmail" => "email",
			"transactionDate" => "date_collect",
			"buyerName" => "fname", //TODO unravel mystery in queueconsumer
			//"recipientEmail" => "merchant_email",
			//"recipientName" => "merchant_name",
			//"operation" => e.g. "pay"
		);
	}

	function defineAccountInfo() {}
	function defineReturnValueMap() {}
	function defineDataConstraints() {}

	function defineTransactions() {
		$this->transactions = array();
		$this->transactions[ 'Donate' ] = array(
			'request' => array(
				'amount',
				'cobrandingStyle',
				'collectShippingAddress',
				'description',
				'immediateReturn',
				'returnUrl',
				'isDonationWidget',
				'processImmediate',
				'signatureMethod',
				'signatureVersion',
				'accessKey',
				'amazonPaymentsAccountId',
			),
			'values' => array(
				'cobrandingStyle' => 'logo',
				'collectShippingAddress' => '0',
				'description' => 'Donation to the Wikimedia Foundation',
				'immediateReturn' => '1',
				'isDonationWidget' => '1',
				'processImmediate' => '1',
				'signatureMethod' => 'HmacSHA256',
				'signatureVersion' => '2',
				'accessKey' => $this->getGlobal( 'AccessKey' ),
				'amazonPaymentsAccountId' => $this->getGlobal( 'PaymentsAccountID' ),
			),
			'redirect' => TRUE,
		);
		$this->transactions[ 'VerifySignature' ] = array(
			'request' => array(
				'Action',
				'HttpParameters',
				'UrlEndPoint',
				'Version',
				'SignatureMethod',
				'SignatureVersion',
				'AWSAccessKeyId',
				'Timestamp',
			),
			'values' => array(
				'Action' => "VerifySignature",
				'UrlEndPoint' => $this->getGlobal( "ReturnURL" ),
				'Version' => "2008-09-17",
				'SignatureMethod' => "HmacSHA256",
				'SignatureVersion' => "2",
				'AWSAccessKeyId' => $this->getGlobal( "AccessKey" ),
				'Timestamp' => date( 'c' ),
			),
			'url' => $this->getGlobal( "FpsURL" ),
		);
	}

	protected function buildRequestParams() {
		// Look up the request structure for our current transaction type in the transactions array
		$structure = $this->getTransactionRequestStructure();
		if ( !is_array( $structure ) ) {
			return '';
		}

		$queryparams = array();

		//we are going to assume a flat array, because... namevalue. 
		foreach ( $structure as $fieldname ) {
			$fieldvalue = $this->getTransactionSpecificValue( $fieldname );
			if ( $fieldvalue !== '' && $fieldvalue !== false ) {
				$queryparams[ $fieldname ] = $fieldvalue;
			}
		}

		ksort( $queryparams );
		return $queryparams;
	}

	function do_transaction( $transaction ) {
		global $wgRequest, $wgOut;

		$this->setCurrentTransaction( $transaction );

		$override_url = $this->transaction_option( 'url' );
		if ( !empty( $override_url ) ) {
			$this->url = $override_url;
		}
		else {
			$this->url = $this->getGlobal( "URL" );
		}

		if ( $transaction == 'VerifySignature' ) {
			$request_params = $wgRequest->getValues();
			unset( $request_params[ 'title' ] );
			$incoming = http_build_query( $request_params, '', '&' );
			$this->transactions[ $transaction ][ 'values' ][ 'HttpParameters' ] = $incoming;
		} 
		else {
			//TODO parseurl... in case ReturnURL already has a query string
			$this->transactions[ $transaction ][ 'values' ][ 'returnUrl' ] = "{$this->getGlobal( 'ReturnURL' )}?order_id={$this->getData_Unstaged_Escaped( 'order_id' )}";
		}

		$query = $this->buildRequestParams();
		$parsed_uri = parse_url( $this->url );
		$signature = $this->signRequest( $parsed_uri[ 'host' ], $parsed_uri[ 'path' ], $query );

		if ( $this->transaction_option( 'redirect' ) ) {
			$this->doLimboStompTransaction();
			$this->addDonorDataToSession();
			$query_str = $this->encodeQuery( $query );
			$wgOut->redirect("{$this->getGlobal( "URL" )}?{$query_str}&signature={$signature}");
		}
		else {
			$query_str = $this->encodeQuery( $query );
			$this->url .= "?{$query_str}&Signature={$signature}";

			parent::do_transaction( $transaction );
		}

		// This is actually the final step of a redirected call.
		// At the moment we only have one case, in the future we can
		// check the 'operation' param to determine the originating call.
		if ( $transaction == 'VerifySignature' ) {
			if ( $this->getTransactionWMFStatus() == 'complete' ) {
				$this->unstaged_data = $this->dataObj->getDataEscaped(); // XXX not cool.
				$this->runPostProcessHooks();
				$this->doLimboStompTransaction( true );
			}
			$this->unsetAllSessionData();
		}
	}

	function getCurrencies() {
		return array(
			'USD',
		);
	}

	function processResponse( $response ) {
		global $wgRequest;

		if ( $this->getCurrentTransaction() == 'VerifySignature' ) {
			//n.b. these request vars were from the _previous_ api call
			$add_data = array();
			foreach ( $this->var_map as $gateway_key => $normal_key ) {
				$value = $wgRequest->getVal( $gateway_key, null );
				if ( !empty( $value ) ) {
					$add_data[ $normal_key ] = $value;
					if ( $normal_key == 'amount' ) {
						list ($currency, $amount) = explode( ' ', $value );
						$add_data[ 'currency' ] = $currency;
						$add_data[ 'amount' ] = $amount;
					}
				}
			}
			//TODO: consider prioritizing the session vars
			$this->dataObj->addData( $add_data );

			//todo: lots of other statuses we can interpret
			$success_statuses = array( 'PS' );
			$status = $this->dataObj->getVal_Escaped( 'gateway_status' );
			if ( in_array( $status, $success_statuses ) ) {
				$this->setTransactionWMFStatus( 'complete' );
				$this->setTransactionResult( $this->dataObj->getVal_Escaped( 'gateway_txn_id' ), 'gateway_txn_id' );
			}
			else {
				$this->setTransactionWMFStatus( 'failed' );
			}
		}
	}

	function encodeQuery( $params ) {
		ksort( $params );
		foreach ( $params as $key => $value ) {
			$encoded = str_replace( "%7E", "~", rawurlencode( $value ) );
			$query[] = $key . "=" . $encoded;
		}
		return implode( "&", $query );
	}

	function signRequest( $host, $path, &$params ) {
		unset( $params['signature'] );

		$secret_key = $this->getGlobal( "SecretKey" );

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
			'X-VPS-Client-Timeout: 45',
			'X-VPS-Request-ID:' . $this->postdatadefaults[ 'order_id' ],
		);
		return $headers;
	}

	public function getResponseStatus( $response ) {
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
}
