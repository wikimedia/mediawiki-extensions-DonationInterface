<?php

class WorldpayValidateApi extends ApiBase {
	protected $allowedParams = array(
		'fname', 'lname', 'email',
		'email-opt',
		'utm_source','utm_medium','utm_campaign','referrer',
		'gateway','payment_method', 'payment_submethod', 'language','wmf_token',
		'order_id', 'amount', 'ffname',

		// AVS Countries
		'street','state','zip','country','city',

		// Scary things
		'cvc'
	);

	public function execute() {
		$adapter = new WorldpayAdapter( array( 'api_request' => true ) );

		// FIXME: move this workflow into the adapter class.

		// Do some validity checking and what not
		if ( $adapter->checkTokens() ) {
			$adapter->revalidate();
			if ( $adapter->getAllErrors() ) {
				$this->getResult()->addValue( null, 'errors', $adapter->getAllErrors() );
				return;
			}

			// See if we can get a token
			$adapter->do_transaction( 'GenerateToken' );
			if ( $adapter->getTransactionStatus() ) {
				$this->getResult()->addValue( null, 'ottResult', array(
					'wp_one_time_token' => $adapter->getData_Unstaged_Escaped( 'wp_one_time_token' ),
					'wp_process_url' => $adapter->getData_Unstaged_Escaped( 'wp_process_url' ),
					'wp_redirect_url' => $adapter->getData_Unstaged_Escaped( 'wp_redirect_url' ),
				));
			} else {
				$this->getResult()->addValue( null, 'errors', $adapter->getTransactionErrors() );
				return;
			}

			// Store the CVC into the session
			$adapter->store_cvv_in_session( $this->getParameter( 'cvc' ) );

			// Save everything else into the session
			$adapter->session_addDonorData();
		} else {
			// Don't let people continue if they failed a token check!
			$this->getResult()->addValue(
				null,
				'errors',
				array( 'token-mismatch' => $this->msg( 'donate_interface-token-mismatch' )->text() )
			);
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
