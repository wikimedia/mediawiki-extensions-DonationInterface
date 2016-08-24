<?php

/**
 * TestingAdyenAdapter
 */
class TestingAdyenAdapter extends AdyenAdapter {

	//@TODO: That minfraud jerk needs its own isolated tests.
	function runAntifraudFilters() {
		//now screw around with the batch settings to trick the fraud filters into triggering
		$is_batch = $this->isBatchProcessor();
		$this->batch = true;

		parent::runAntifraudFilters();

		$this->batch = $is_batch;
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
		$file_path = __DIR__ . '/../';
		$file_path .= 'Responses' . '/' . self::getIdentifier() . '/';
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
