<?php

class WorldPayValidateApi extends ApiBase {
	protected $allowedParams = array(
		'fname', 'lname', 'emailAdd',
		'email-opt',
		'utm_source','utm_medium','utm_campaign','referrer',
		'gateway','payment_method','language','token',
		'order_id','contribution_tracking_id',

		// AVS Countries
		'street','state','zip','country',

		// Scary things
		'cvc'
	);

	public function execute() {
		$adapter = new WorldPayAdapter( array( 'api_request' => true ) );

		// Do some validity checking and what not
		if ( $adapter->checkTokens() ) {
			// TODO: moar checking!

			// Store the CVC into the session
			$adapter->store_cvv_in_session( $this->getParameter( 'cvc' ) );

			// Save everything else into the session
			$adapter->session_addDonorData();
		}
	}

	public function getAllowedParams() {
		$params = array();
		foreach( $this->allowedParams as $param ) {
			$params[$param] = null;
		}
		return $params;
	}

	public function mustBePosted() {
		return true;
	}
}