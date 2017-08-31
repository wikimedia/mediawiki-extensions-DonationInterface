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

trait TTestingAdapter {
	public static $fakeGlobals = array();

	public static $fakeIdentifier;

	public static $dummyGatewayResponseCode = null;

	public $curled = array();

	public static function getIdentifier() {
		if ( static::$fakeIdentifier ) {
			return static::$fakeIdentifier;
		}
		return parent::getIdentifier();
	}

	public static function getGlobal( $name ) {
		if ( array_key_exists( $name, static::$fakeGlobals ) ) {
			return static::$fakeGlobals[$name];
		}
		return parent::getGlobal( $name );
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

	public function _buildRequestParams() {
		return $this->buildRequestParams();
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
	 */
	public function defineOrderIDMeta() {
		if ( isset( $this->order_id_meta ) ) {
			return;
		}
		parent::defineOrderIDMeta();
	}

	//@TODO: That minfraud jerk needs its own isolated tests.
	function runAntifraudFilters() {
		//now screw around with the batch settings to trick the fraud filters into triggering
		$is_batch = $this->isBatchProcessor();
		$this->batch = true;

		parent::runAntifraudFilters();

		$this->batch = $is_batch;
	}

	public function getRiskScore() {
		return $this->risk_score;
	}

	/**
	 * Set the error code you want the dummy response to return
	 * @param $code
	 */
	public static function setDummyGatewayResponseCode( $code ) {
		static::$dummyGatewayResponseCode = $code;
	}

	protected function curl_transaction( $data ) {
		$this->curled[] = $data;
		return parent::curl_transaction( $data );
	}

	/**
	 * Load in some dummy response XML so we can test proper response processing
	 */
	protected function curl_exec( $ch ) {
		$code = '';
		if ( static::$dummyGatewayResponseCode !== null ) {
			if ( is_array( static::$dummyGatewayResponseCode ) ) {
				$code = array_shift( static::$dummyGatewayResponseCode );
			} elseif ( is_callable( static::$dummyGatewayResponseCode ) ) {
				$code = call_user_func( static::$dummyGatewayResponseCode, $this );
			} else {
				$code = static::$dummyGatewayResponseCode;
			}
		}
		if ( $code ) {
			if ( $code === 'Exception' ) {
				throw new RuntimeException('blah!');
			}
			$code = '_' . $code;
		}

		//could start stashing these in a further-down subdir if payment type starts getting in the way,
		//but frankly I don't want to write tests that test our dummy responses.
		$file_path = __DIR__ . '/../';
		$file_path .= 'Responses/' . static::getIdentifier() . '/';
		$file_path .= $this->getCurrentTransaction() . $code . '.testresponse';

		//these are all going to be short, so...
		if ( file_exists( $file_path ) ) {
			return file_get_contents( $file_path );
		} else {
			// FIXME: Throw an assertion instead.
			echo "File $file_path does not exist.\n"; //<-That will deliberately break the test.
			return false;
		}
	}

	/**
	 * Load in some dummy curl response info so we can test proper response processing
	 */
	protected function curl_getinfo( $ch, $opt = null ) {
		$code = 200;

		//put more here if it ever turns out that we care about it.
		return array (
			'http_code' => $code,
		);
	}

}
