<?php

/**
 * TestingPaypalExpressAdapter
 */
class TestingPaypalExpressAdapter extends PaypalExpressAdapter {
	use TTestingAdapter;
	public function __construct( array $options = array() ) {
		$this->setDummyGatewayResponseCode( 'OK' );
		parent::__construct( $options );
	}
}
