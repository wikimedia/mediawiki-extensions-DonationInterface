<?php

/**
 * TestingGlobalCollectAdapter
 *
 * TODO: Add dependency injection to the base class so we don't have to repeat code here.
 */
class TestingGlobalCollectAdapter extends GlobalCollectAdapter {

	public $curled = array ( );

	public $limbo_messages = array();

	public $dummyGatewayResponseCode;

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

	// TODO: Store and test the actual messages.
	public function setLimboMessage( $queue = 'limbo' ) {
		$this->limbo_messages[] = false;
	}

	/**
	 * Stub out the limboStomp fn and record the calls
	 */
	public function deleteLimboMessage( $queue = 'limbo' ) {
		$this->limbo_messages[] = true;
	}

	//@TODO: That minfraud jerk needs its own isolated tests.
	function runAntifraudHooks() {
		//now screw around with the batch settings to trick the fraud filters into triggering
		$is_batch = $this->isBatchProcessor();
		$this->batch = true;

		parent::runAntifraudHooks();

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

	protected function curl_transaction( $data ) {
		$this->curled[] = $data;
		return parent::curl_transaction( $data );
	}

	/**
	 * Load in some dummy response XML so we can test proper response processing
	 * @throws RuntimeException
	 */
	protected function curl_exec( $ch ) {
		$code = '';
		if ( $this->dummyGatewayResponseCode ) {
			if ( is_array( $this->dummyGatewayResponseCode ) ) {
				$code = array_shift( $this->dummyGatewayResponseCode );
			} elseif ( is_callable( $this->dummyGatewayResponseCode ) ) {
				$code = call_user_func( $this->dummyGatewayResponseCode, $this );
			} else {
				$code = $this->dummyGatewayResponseCode;
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
