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
 * A really dumb adapter.
 */
class TestingGenericAdapter extends GatewayAdapter {

	public $errorsForRevalidate = array();
	public $revalidateCount = 0;
	public static $fakeGlobals = array();

	public function revalidate($check_not_empty = array()) {
		$fakeErrors = $this->errorsForRevalidate[$this->revalidateCount];
		if ( $fakeErrors !== null ) {
			$this->revalidateCount++;
			$this->setValidationErrors( $fakeErrors );
			return empty( $fakeErrors );
		}
		return parent::revalidate($check_not_empty);
	}

	public function normalizeOrderID( $override = null, $dataObj = null ) {
		return '12345';
	}

	public static function getGlobal( $name ) {
		if ( array_key_exists( $name, TestingGenericAdapter::$fakeGlobals ) ) {
			return TestingGenericAdapter::$fakeGlobals[$name];
		}
		return parent::getGlobal( $name );
	}

	public function defineAccountInfo() {
	}

	public function defineDataConstraints() {
	}

	public function defineErrorMap() {
	}

	public function defineOrderIDMeta() {
	}

	public function definePaymentMethods() {
	}

	public function defineReturnValueMap() {
	}

	public function defineStagedVars() {
	}

	public function defineTransactions() {
	}

	public function defineVarMap() {
	}

	public function getResponseData($response) {
	}

	public function getResponseErrors($response) {
	}

	public function getResponseStatus($response) {
	}

	public function processResponse($response, &$retryVars = null) {
	}

	public function setGatewayDefaults() {
	}

	public static function getCurrencies() {
	}

}

/**
 * TestingGlobalCollectAdapter
 *
 * TODO: Add dependency injection to the base class so we don't have to repeat code here.
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

		$this->options = $options;

		parent::__construct( $this->options );
	}

	/**
	 * Clear the static globals cache.
	 */
	public static function clearGlobalsCache() {
		self::$globalsCache = array ( );
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
		//now screw around with the batch settings to trick the fraud filters into triggering
		$is_batch = $this->isBatchProcessor();
		$this->batch = true;

		parent::runAntifraudHooks();

		$this->batch = $is_batch;
	}

	public function getRiskScore() {
		return $this->risk_score;
	}

	/**
	 * Set the error code you want the dummy response to return
	 */
	public function setDummyGatewayResponseCode( $code ) {
		$this->dummyGatewayResponseCode = $code;
	}

	/**
	 * Set the error code you want the dummy response to return
	 */
	public function setDummyCurlResponseCode( $code ) {
		$this->dummyCurlResponseCode = $code;
	}

	/**
	 * Load in some dummy response XML so we can test proper response processing
	 */
	protected function curl_exec( $ch ) {
		$code = '';
		if ( property_exists( $this, 'dummyGatewayResponseCode' ) ) {
			$code = '_' . $this->dummyGatewayResponseCode;
		}

		//could start stashing these in a further-down subdir if payment type starts getting in the way,
		//but frankly I don't want to write tests that test our dummy responses.
		$file_path = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;
		$file_path .= 'Responses' . DIRECTORY_SEPARATOR . self::getIdentifier() . DIRECTORY_SEPARATOR;
		$file_path .= $this->getCurrentTransaction() . $code . '.testresponse';

		//these are all going to be short, so...
		if ( file_exists( $file_path ) ) {
			return file_get_contents( $file_path );
		} else {
			echo "File $file_path does not exist.\n"; //<-That will deliberately break the test.
			return false;
		}
	}

	/**
	 * Load in some dummy curl response info so we can test proper response processing
	 */
	protected function curl_getinfo( $ch, $opt = null ) {
		$code = 200; //response OK
		if ( property_exists( $this, 'dummyCurlResponseCode' ) ) {
			$code = ( int ) $this->dummyCurlResponseCode;
		}

		//put more here if it ever turns out that we care about it.
		return array (
			'http_code' => $code,
		);
	}

}



/**
 * TestingPaypalAdapter
 * @TODO: Extend/damage things here. I'm sure we'll need it eventually...
 */
class TestingPaypalAdapter extends PaypalAdapter {
	/**
	 * Set the error code you want the dummy response to return
	 */
	public function setDummyGatewayResponseCode( $code ) {
		$this->dummyGatewayResponseCode = $code;
	}

	/**
	 * Set the error code you want the dummy response to return
	 */
	public function setDummyCurlResponseCode( $code ) {
		$this->dummyCurlResponseCode = $code;
	}

	/**
	 * Load in some dummy response XML so we can test proper response processing
	 */
	protected function curl_exec( $ch ) {
		$code = '';
		if ( property_exists( $this, 'dummyGatewayResponseCode' ) ) {
			$code = '_' . $this->dummyGatewayResponseCode;
		}

		//could start stashing these in a further-down subdir if payment type starts getting in the way,
		//but frankly I don't want to write tests that test our dummy responses.
		$file_path = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;
		$file_path .= 'Responses' . DIRECTORY_SEPARATOR . self::getIdentifier() . DIRECTORY_SEPARATOR;
		$file_path .= $this->getCurrentTransaction() . $code . '.testresponse';

		//these are all going to be short, so...
		if ( file_exists( $file_path ) ) {
			return file_get_contents( $file_path );
		} else {
			echo "File $file_path does not exist.\n"; //<-That will deliberately break the test.
			return false;
		}
	}

	/**
	 * Load in some dummy curl response info so we can test proper response processing
	 */
	protected function curl_getinfo( $ch, $opt = null ) {
		$code = 200; //response OK
		if ( property_exists( $this, 'dummyCurlResponseCode' ) ) {
			$code = ( int ) $this->dummyCurlResponseCode;
		}

		//put more here if it ever turns out that we care about it.
		return array (
			'http_code' => $code,
		);
	}

}

/**
 * TestingAmazonAdapter
 */
class TestingAmazonAdapter extends AmazonAdapter {

	public static $fakeGlobals = array();

	public static function getGlobal( $name ) {
		if ( array_key_exists( $name, TestingAmazonAdapter::$fakeGlobals ) ) {
			return TestingAmazonAdapter::$fakeGlobals[$name];
		}
		return parent::getGlobal( $name );
	}

	public function _buildRequestParams() {
		return $this->buildRequestParams();
	}
	/**
	 * Set the error code you want the dummy response to return
	 */
	public function setDummyGatewayResponseCode( $code ) {
		$this->dummyGatewayResponseCode = $code;
	}

	/**
	 * Set the error code you want the dummy response to return
	 */
	public function setDummyCurlResponseCode( $code ) {
		$this->dummyCurlResponseCode = $code;
	}

	/**
	 * Load in some dummy response XML so we can test proper response processing
	 */
	protected function curl_exec( $ch ) {
		$code = '';
		if ( property_exists( $this, 'dummyGatewayResponseCode' ) ) {
			$code = '_' . $this->dummyGatewayResponseCode;
		}

		//could start stashing these in a further-down subdir if payment type starts getting in the way,
		//but frankly I don't want to write tests that test our dummy responses.
		$file_path = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;
		$file_path .= 'Responses' . DIRECTORY_SEPARATOR . self::getIdentifier() . DIRECTORY_SEPARATOR;
		$file_path .= $this->getCurrentTransaction() . $code . '.testresponse';

		//these are all going to be short, so...
		if ( file_exists( $file_path ) ) {
			return file_get_contents( $file_path );
		} else {
			echo "File $file_path does not exist.\n"; //<-That will deliberately break the test.
			return false;
		}
	}

	/**
	 * Load in some dummy curl response info so we can test proper response processing
	 */
	protected function curl_getinfo( $ch, $opt = null ) {
		$code = 200; //response OK
		if ( property_exists( $this, 'dummyCurlResponseCode' ) ) {
			$code = ( int ) $this->dummyCurlResponseCode;
		}

		//put more here if it ever turns out that we care about it.
		return array (
			'http_code' => $code,
		);
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
		//now screw around with the batch settings to trick the fraud filters into triggering
		$is_batch = $this->isBatchProcessor();
		$this->batch = true;

		parent::runAntifraudHooks();

		$this->batch = $is_batch;
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

	/**
	 * Set the error code you want the dummy response to return
	 */
	public function setDummyGatewayResponseCode( $code ) {
		$this->dummyGatewayResponseCode = $code;
	}

	/**
	 * Set the error code you want the dummy response to return
	 */
	public function setDummyCurlResponseCode( $code ) {
		$this->dummyCurlResponseCode = $code;
	}

	/**
	 * Load in some dummy response XML so we can test proper response processing
	 */
	protected function curl_exec( $ch ) {
		$code = '';
		if ( property_exists( $this, 'dummyGatewayResponseCode' ) ) {
			$code = '_' . $this->dummyGatewayResponseCode;
		}

		//could start stashing these in a further-down subdir if payment type starts getting in the way,
		//but frankly I don't want to write tests that test our dummy responses.
		$file_path = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;
		$file_path .= 'Responses' . DIRECTORY_SEPARATOR . self::getIdentifier() . DIRECTORY_SEPARATOR;
		$file_path .= $this->getCurrentTransaction() . $code . '.testresponse';

		//these are all going to be short, so...
		if ( file_exists( $file_path ) ) {
			return file_get_contents( $file_path );
		} else {
			echo "File $file_path does not exist.\n"; //<-That will deliberately break the test.
			return false;
		}
	}

	/**
	 * Load in some dummy curl response info so we can test proper response processing
	 */
	protected function curl_getinfo( $ch, $opt = null ) {
		$code = 200; //response OK
		if ( property_exists( $this, 'dummyCurlResponseCode' ) ) {
			$code = ( int ) $this->dummyCurlResponseCode;
		}

		//put more here if it ever turns out that we care about it.
		return array (
			'http_code' => $code,
		);
	}

}

/**
 * TestingWorldPayAdapter
 */
class TestingWorldPayAdapter extends WorldPayAdapter {

	public $testlog = array ( );

	//@TODO: That minfraud jerk needs its own isolated tests.
	function runAntifraudHooks() {
		//now screw around with the batch settings to trick the fraud filters into triggering
		$is_batch = $this->isBatchProcessor();
		$this->batch = true;

		parent::runAntifraudHooks();

		$this->batch = $is_batch;
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

	/**
	 * Set the error code you want the dummy response to return
	 */
	public function setDummyGatewayResponseCode( $code ) {
		$this->dummyGatewayResponseCode = $code;
	}

	/**
	 * Set the error code you want the dummy response to return
	 */
	public function setDummyCurlResponseCode( $code ) {
		$this->dummyCurlResponseCode = $code;
	}

	/**
	 * Load in some dummy response XML so we can test proper response processing
	 */
	protected function curl_exec( $ch ) {
		$code = '';
		if ( property_exists( $this, 'dummyGatewayResponseCode' ) ) {
			$code = '_' . $this->dummyGatewayResponseCode;
		}

		//could start stashing these in a further-down subdir if payment type starts getting in the way,
		//but frankly I don't want to write tests that test our dummy responses.
		$file_path = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;
		$file_path .= 'Responses' . DIRECTORY_SEPARATOR . self::getIdentifier() . DIRECTORY_SEPARATOR;
		$file_path .= $this->getCurrentTransaction() . $code . '.testresponse';

		//these are all going to be short, so...
		if ( file_exists( $file_path ) ) {
			return file_get_contents( $file_path );
		} else {
			echo "File $file_path does not exist.\n"; //<-That will deliberately break the test.
			return false;
		}
	}

	/**
	 * Load in some dummy curl response info so we can test proper response processing
	 */
	protected function curl_getinfo( $ch, $opt = null ) {
		$code = 200; //response OK
		if ( property_exists( $this, 'dummyCurlResponseCode' ) ) {
			$code = ( int ) $this->dummyCurlResponseCode;
		}

		//put more here if it ever turns out that we care about it.
		return array (
			'http_code' => $code,
		);
	}

}

/**
 * TestingGlobalCollectOrphanAdapter
 *
 * This is officially truly silly now.
 * TODO: Poke own eye out before you notice that multiple inheritance would, if
 * available, cause this file to deflate by 4/5ths. 
 */
require_once dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . DIRECTORY_SEPARATOR . 'globalcollect_gateway/scripts/orphan_adapter.php';

class TestingGlobalCollectOrphanAdapter extends GlobalCollectOrphanAdapter {

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

		$this->options = $options;

		parent::__construct( $this->options );
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
		return call_user_func_array( array ( $this, 'addCodeRange' ), func_get_args() );
	}

	public function _findCodeAction() {
		return call_user_func_array( array ( $this, 'findCodeAction' ), func_get_args() );
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
	public function log( $msg, $log_level = LOG_INFO, $log_id_suffix = '' ) {
		//I don't care about the suffix right now, particularly.
		$this->testlog[$log_level][] = $msg;
	}

	//@TODO: That minfraud jerk needs its own isolated tests.
	function runAntifraudHooks() {
		//now screw around with the batch settings to trick the fraud filters into triggering
		$is_batch = $this->isBatchProcessor();
		$this->batch = true;

		parent::runAntifraudHooks();

		$this->batch = $is_batch;
	}

	public function getRiskScore() {
		return $this->risk_score;
	}

	/**
	 * Set the error code you want the dummy response to return
	 */
	public function setDummyGatewayResponseCode( $code ) {
		$this->dummyGatewayResponseCode = $code;
	}

	/**
	 * Set the error code you want the dummy response to return
	 */
	public function setDummyCurlResponseCode( $code ) {
		$this->dummyCurlResponseCode = $code;
	}

	/**
	 * Load in some dummy response XML so we can test proper response processing
	 */
	protected function curl_exec( $ch ) {
		$code = '';
		if ( property_exists( $this, 'dummyGatewayResponseCode' ) ) {
			$code = '_' . $this->dummyGatewayResponseCode;
		}

		//could start stashing these in a further-down subdir if payment type starts getting in the way,
		//but frankly I don't want to write tests that test our dummy responses.
		$file_path = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;
		$file_path .= 'Responses' . DIRECTORY_SEPARATOR . self::getIdentifier() . DIRECTORY_SEPARATOR;
		$file_path .= $this->getCurrentTransaction() . $code . '.testresponse';

		//these are all going to be short, so...
		if ( file_exists( $file_path ) ) {
			return file_get_contents( $file_path );
		} else {
			echo "File $file_path does not exist.\n"; //<-That will deliberately break the test.
			return false;
		}
	}

	/**
	 * Load in some dummy curl response info so we can test proper response processing
	 */
	protected function curl_getinfo( $ch, $opt = null ) {
		$code = 200; //response OK
		if ( property_exists( $this, 'dummyCurlResponseCode' ) ) {
			$code = ( int ) $this->dummyCurlResponseCode;
		}

		//put more here if it ever turns out that we care about it.
		return array (
			'http_code' => $code,
		);
	}

}

