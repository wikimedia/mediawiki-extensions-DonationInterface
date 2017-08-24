<?php

/**
 * TestingAstroPayAdapter
 *
 * TODO: Add dependency injection to the base class so we don't have to repeat
 * code (or this comment) here.
 */
class TestingAstroPayAdapter extends AstroPayAdapter {

	public $curled = array();

	protected function curl_transaction( $data ) {
		$this->curled[] = $data;
		return parent::curl_transaction( $data );
	}

	/**
	 * Load in some dummy response JSON so we can test proper response processing
	 * @throws RuntimeException
	 */
	protected function curl_exec( $ch ) {
		$code = '';
		if ( DonationInterfaceTestCase::$dummyGatewayResponseCode ) {
			$code = '_' . DonationInterfaceTestCase::$dummyGatewayResponseCode;
			if ( DonationInterfaceTestCase::$dummyGatewayResponseCode === 'Exception' ) {
				throw new RuntimeException( 'blah!' );
			}
		}

		//could start stashing these in a further-down subdir if payment type starts getting in the way,
		//but frankly I don't want to write tests that test our dummy responses.
		$file_path = __DIR__
			. '/../Responses/'
			. self::getIdentifier()
			. '/'
			. $this->getCurrentTransaction()
			. $code
			. '.testresponse';

		//these are all going to be short, so...
		if ( file_exists( $file_path ) ) {
			return file_get_contents( $file_path );
		}
		throw new RuntimeException( "File $file_path does not exist." );
	}

	/**
	 * Load in some dummy curl response info so we can test proper response processing
	 */
	protected function curl_getinfo( $ch, $opt = null ) {
		$code = 200; //response OK
		if ( DonationInterfaceTestCase::$dummyCurlResponseCode ) {
			$code = ( int ) DonationInterfaceTestCase::$dummyCurlResponseCode;
		}

		//put more here if it ever turns out that we care about it.
		return array (
			'http_code' => $code,
		);
	}
}
