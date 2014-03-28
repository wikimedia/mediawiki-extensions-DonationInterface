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
 * TestingGlobalCollectAdapter
 *
 */
class TestingGlobalCollectAdapter extends GlobalCollectAdapter {
	public $testlog = array ( );

	/**
	 * Also set a useful MerchantID.
	 */
	public function __construct( $options = array ( ) ) {
		if ( is_null( $options ) ) {
			$options = array ( );
		}

		//I hate myself for this part, and so do you.
		//Deliberately not fixing the actual problem for this patchset.
		//@TODO: Change the way the constructor works in all adapter
		//objects, such that the mess I am about to make is no longer
		//necessary. A patchset may already be near-ready for this...
		if ( array_key_exists( 'order_id_meta', $options ) ) {
			$this->order_id_meta = $options['order_id_meta'];
			unset( $options['order_id_meta'] );
		}
		if ( array_key_exists( 'batch_mode', $options ) ) {
			$this->batch = $options['batch_mode'];
			unset( $options['batch_mode'] );
		}

		$this->options = $options;

		parent::__construct( $this->options );
	}

	/**
	 * Override the curl_transaction to use a local copy of a fake payment
	 * instead of actually contacting GlobalCollect
	 *
	 * @param string $data The data we're trying to send to a server
	 * @return boolean Whether the communication was successful.
	 */
	protected function curl_transaction( $data ) {
		$retval = false;
		$email = $this->getData_Unstaged_Escaped( 'email' );
		$this->log( "Initiating fake cURL request for donor $email" );

		// Run hooks
		$hookResult = wfRunHooks( 'DonationInterfaceCurlInit', array( &$this ) );
		if ( $hookResult === false ) {
			$this->log( "fake cURL transaction aborted on hook
				DonationInterfaceCurlInit", LOG_INFO );
			$this->setValidationAction( 'reject' );
			return false;
		}

		// Construct fake response
		$results = array ( );

		// Get some DOM-looking things for the request body
		$dom = new SimpleXMLElement( $data );
		$request = $dom->xpath( '/XML/REQUEST' );
		$request = $request[0];

		// Figure out the request type
		$action = $request->xpath( 'ACTION' );
		$action = $action[0]->asXML();
		$action = preg_replace( '#^<ACTION>(.*)</ACTION>$#', '\\1', $action );

		if ( $action === 'INSERT_ORDERWITHPAYMENT' ||
				$action === 'GET_ORDERSTATUS' ||
				$action === 'DO_FINISHPAYMENT' ) {
			if ( $action === 'INSERT_ORDERWITHPAYMENT' ||
					$action === 'GET_ORDERSTATUS' ) {
				// Why can't we use absolute paths here? No real reason to
				// spend time figuring it out.
				$order = $request->xpath( 'PARAMS/ORDER' );
				$order = $order[0];
				$orderid = $order->xpath( 'ORDERID' );
				$merchant_ref = $order->xpath( 'MERCHANTREFERENCE' );
			} else if ( $action === 'DO_FINISHPAYMENT' ) {
				$orderid = $request->xpath( 'PARAMS/PAYMENT/ORDERID' );
			}

			if ( $orderid ) {
				$orderid = $orderid[0]->asXML();
				$orderid = preg_replace( '#^<ORDERID>(.*)</ORDERID>$#', '\\1', $orderid );
			}

			if ( $merchant_ref ) {
				$merchant_ref = $merchant_ref[0]->asXML();
				$merchant_ref = preg_replace( '#^<MERCHANTREFERENCE>(.*)</MERCHANTREFERENCE>$#', '\\1', $merchant_ref );
			}

			if ( $action === 'INSERT_ORDERWITHPAYMENT' ) {
				$amount = $order->xpath( 'AMOUNT' );
				$amount = $amount[0]->asXML();
				$amount = preg_replace( '#^<AMOUNT>(.*)</AMOUNT>$#', '\\1', $amount );
				$currency = $order->xpath( 'CURRENCYCODE' );
				$currency = $currency[0]->asXML();
				$currency = preg_replace( '#^<CURRENCY>(.*)</CURRENCY>$#', '\\1', $currency );
			}

			// Constants
			$refnum = '000000000000000000000000000000';

			switch ( $action ) {
				case 'INSERT_ORDERWITHPAYMENT':
					// Status pending
					$statusid = '20';
					break;

				case 'GET_ORDERSTATUS':
					// Status pending-poke
					$statusid = '200';
					break;

				case 'DO_FINISHPAYMENT':
					// Status complete
					$statusid = '1000';
					break;
			}

			$mac = 'maQKu1wA3aLG11UymxkvFHV2LbqLxZH12COp/JEZ/uo=';
			$datetime = date( 'YmdHis' );
			$mercid = 'test';

			//@TODO: Something better here.
			//I'm not too worried about it right now, though, so
			//long as this placehilder comes out the other end.
			$formURI = $this->getData_Unstaged_Escaped( 'payment_submethod' ) . '_url_placeholder';

			$response = $request->addChild( 'RESPONSE' );
			$response->addChild( 'RESULT', 'OK' );

			$meta = $response->addChild( 'META' );
			$meta->addChild( 'REQUESTID', '1891851' );
			$meta->addChild( 'RESPONSEDATETIME', $datetime );

			if ( $action === 'INSERT_ORDERWITHPAYMENT' ||
					$action === 'DO_FINISHPAYMENT' ) {
				$row = $response->addChild( 'ROW' );
			} else {
				$row = $response->addChild( 'STATUS' );
			}

			$row->addChild( 'STATUSDATE', $datetime );
			$row->addChild( 'PAYMENTREFERENCE', '0' );
			$row->addChild( 'EXTERNALREFERENCE', $merchant_ref );
			$row->addChild( 'ADDITIONALREFERENCE', $merchant_ref );
			if ( $orderid ) {
				$row->addChild( 'ORDERID', $orderid );
			} else {
				$row->addChild( 'ORDERID', $this->generateOrderID() );
			}
			$row->addChild( 'EFFORTID', '1' );
			$row->addChild( 'REF', $refnum );
			$row->addChild( 'FORMACTION', $formURI );
			$row->addChild( 'FORMMETHOD', 'GET' );
			$row->addChild( 'ATTEMPTID', '1' );
			$row->addChild( 'MERCHANTID', $mercid );
			$row->addChild( 'STATUSID', $statusid );
			$row->addChild( 'RETURNMAC', 's1h645HHsQRpCEMpOa8IyfAEHtPig+N0cEYmt08LSrw=' );
			$row->addChild( 'MAC', $mac );

			if ( $action === 'GET_ORDERSTATUS' ) {
				// It turns out that we're expected to send back some form of CVV.
				$row->addChild( 'CVVRESULT', '123' );
			}

			$xmlresponse = $dom->asXML();
		} else {
			$xmlresponse = '<XML></XML>';
		}

		$results['result'] = (
			'HTTP/1.1 100 Continue\n\n' .

			'HTTP/1.1 200 OK\n' .
			'Server: Sun-ONE-Web-Server/6.1\n' .
			'Date: ' . date( 'r' ) . '\n' .
			'Content-length: ' . strlen( $xmlresponse ) . '\n' .
			'Content-type: text/xml; charset=utf-8\n' .
			'P3p: policyref="https://ps.gcsip.nl/w3c/policy.xml",CP="NON DSP CURa ADMa OUR NOR BUS IND PHY ONL UNI FIN COM NAV STA"\n' .
			'Cache-control: no-cache, no-store, must-revalidate, max-age=0, proxy-revalidate, no-transform, pre-check=0, post-check=0, private\n' .
			'Expires: Thu, Jan 01 1970 00:00:00 GMT\n' .
			'Pragma: no-cache\n\n' .

			$xmlresponse

		);

		// This assumes some things, and maybe bits aren't necessary.
		// Remove as needed.
		$results['headers'] = array(
			'url' => '',
			'content_type' => 'text/xml; charset=utf-8',
			'http_code' => 200,
			'header_size' => 483,
			'request_size' => 234,
			'filetime' => -1,
			'ssl_verify_result' => 0,
			'redirect_count' => 0,
			'total_time' => 1.439627,
			'namelookup_time' => 0.072232,
			'connect_time' => 0.219967,
			'pretransfer_time' => 0.685452,
			'size_upload' => strlen( $data ),
			'size_download' => strlen( $xmlresponse ),
			'speed_download' => 1307,
			'speed_upload' => 712,
			'download_content_length' => strlen( $xmlresponse ),
			'upload_content_length' => strlen( $data ),
			'starttransfer_time' => 0.835144,
			'redirect_time' => 0,
			'certinfo' => array()
		);

		$this->setTransactionResult( $results );
		return ( $results['headers']['http_code'] === 200 &&
			$results['result'] !== false );
	}

	/**
	 * Returns the variable $this->dataObj which should be an instance of
	 * DonationData.
	 *
	 * @returns DonationData
	 */
	public function getDonationData() {
		return $this->dataObj;
	}

	public function _addCodeRange() {
		return call_user_func_array(array($this, 'addCodeRange'), func_get_args());
	}

	public function _findCodeAction() {
		return call_user_func_array(array($this, 'findCodeAction'), func_get_args());
	}

	public function _buildRequestXML() {
		return call_user_func_array( array ( $this, 'buildRequestXML' ), func_get_args() );
	}

	public function _getData_Staged() {
		return call_user_func_array( array ( $this, 'getData_Staged' ), func_get_args() );
	}

	public function _stageData() {
		$this->stageData();
	}

	/**
	 * @TODO: Get rid of this and the override mechanism as soon as you
	 * refactor the constructor into something reasonable.
	 * @return type
	 */
	public function defineOrderIDMeta() {
		if ( isset( $this->order_id_meta ) ) {
			return;
		}
		parent::defineOrderIDMeta();
	}

	/**
	* Trap the error log so we can use it in testing
	* @param type $msg
	* @param type $log_level
	* @param type $log_id_suffix
	*/
	public function log( $msg, $log_level = LOG_INFO, $log_id_suffix = ''){
		//I don't care about the suffix right now, particularly.
		$this->testlog[$log_level][] = $msg;
	}

	//@TODO: That minfraud jerk needs its own isolated tests.
	function runAntifraudHooks() {
		//grabbing the output buffer to prevent minfraud being stupid from ruining my test.
		ob_start();

		//now screw around with the batch settings to trick the fraud filters into triggering
		$is_batch = $this->isBatchProcessor();
		$this->batch = true;

		parent::runAntifraudHooks();

		$this->batch = $is_batch;
		ob_end_clean();
	}

	public function getRiskScore() {
		return $this->risk_score;
	}

}



/**
 * TestingPaypalAdapter
 * @TODO: Extend/damage things here. I'm sure we'll need it eventually...
 */
class TestingPaypalAdapter extends PaypalAdapter {

}

/**
 * TestingAmazonAdapter
 */
class TestingAmazonAdapter extends AmazonAdapter {
	public function _buildRequestParams() {
		return $this->buildRequestParams();
	}

}

/**
 * TestingAdyenAdapter
 */
class TestingAdyenAdapter extends AdyenAdapter {

	public $testlog = array ( );

	public function _buildRequestParams() {
		return $this->buildRequestParams();
	}

	//@TODO: That minfraud jerk needs its own isolated tests.
	function runAntifraudHooks() {
		//grabbing the output buffer to prevent minfraud being stupid from ruining my test.
		ob_start();

		//now screw around with the batch settings to trick the fraud filters into triggering
		$is_batch = $this->isBatchProcessor();
		$this->batch = true;

		parent::runAntifraudHooks();

		$this->batch = $is_batch;
		ob_end_clean();
	}

	public function _getData_Staged() {
		return call_user_func_array( array ( $this, 'getData_Staged' ), func_get_args() );
	}

	/**
	 * So we can fake a risk score
	 */
	public function setRiskScore( $score ) {
		$this->risk_score = $score;
	}

	/**
	 * Trap the error log so we can use it in testing
	 * @param type $msg
	 * @param type $log_level
	 * @param type $log_id_suffix
	 */
	public function log( $msg, $log_level = LOG_INFO, $log_id_suffix = '' ) {
		//I don't care about the suffix right now, particularly.
		$this->testlog[$log_level][] = $msg;
	}

}

/**
 * TestingWorldPayAdapter
 */
class TestingWorldPayAdapter extends WorldPayAdapter {

	public $testlog = array ( );

	//@TODO: That minfraud jerk needs its own isolated tests.
	function runAntifraudHooks() {
		//grabbing the output buffer to prevent minfraud being stupid from ruining my test.
		ob_start();

		//now screw around with the batch settings to trick the fraud filters into triggering
		$is_batch = $this->isBatchProcessor();
		$this->batch = true;

		parent::runAntifraudHooks();

		$this->batch = $is_batch;
		ob_end_clean();
	}

	/**
	 * Trap the error log so we can use it in testing
	 * @param type $msg
	 * @param type $log_level
	 * @param type $log_id_suffix
	 */
	public function log( $msg, $log_level = LOG_INFO, $log_id_suffix = '' ) {
		//I don't care about the suffix right now, particularly.
		$this->testlog[$log_level][] = $msg;
	}

	public function getRiskScore() {
		return $this->risk_score;
	}

}

