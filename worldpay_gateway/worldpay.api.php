<?php

class WorldPayValidateApi extends ApiBase {
	protected $allowedParams = array(
		'fname', 'lname', 'emailAdd',
		'email-opt',
		'utm_source','utm_medium','utm_campaign','referrer',
		'gateway','payment_method','language','token',
		'order_id','contribution_tracking_id',

		// AVS Countries
		'street','state','zip','country','city',

		// Scary things
		'cvc'
	);

	public function execute() {
		$adapter = new WorldPayAdapter( array( 'api_request' => true ) );

		// Do some validity checking and what not
		if ( $adapter->checkTokens() ) {
			$form_errors = $adapter->validateSubmethodData();
			$this->getResult()->addValue( null, 'result', $adapter->getAllErrors() );
			if ( $form_errors ) {
				return;
			}

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
		// This API must be posted because we submit the CVV inside it. If it
		// was allowed to be in the GET request, that would be caught inside
		// URL logging / analytics which we cannot have.
		return true;
	}

	public function isReadMode() {
		return false;
	}
}