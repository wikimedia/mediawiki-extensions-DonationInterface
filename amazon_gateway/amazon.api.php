<?php

class AmazonBillingApi extends ApiBase {
	protected $allowedParams = array(
		'amount',
		'billingAgreementId',
		'currency_code',
		'orderReferenceId',
		'recurring',
		'token',
	);

	public function execute() {
		$output = $this->getResult();
		$recurring = $this->getParameter( 'recurring');
		$token = $this->getParameter( 'token' );
		$adapterParams = array(
			'api_request' => true,
			'external_data' => array(
				'amount' => $this->getParameter( 'amount' ),
				'currency_code' => $this->getParameter( 'currency_code' ),
				'recurring' => $recurring,
				'token' => $token,
			),
		);

		$adapter = new AmazonAdapter( $adapterParams );

		if ( !$adapter->validatedOK() ) {
			$output->addValue(
				null,
				'errors',
				$adapter->getValidationErrors()
			);
		} else if ( $token && $adapter->checkTokens() ) {
			if ( $recurring ) {
				$adapter->addRequestData( array(
					'subscr_id' => $this->getParameter( 'billingAgreementId' ),
				) );
			} else {
				$adapter->addRequestData( array(
					'order_reference_id' => $this->getParameter( 'orderReferenceId' ),
				) );
			}
			$result = $adapter->doPayment();
			if ( $result->isFailed() ) {
				$output->addvalue(
					null,
					'redirect',
					$adapter->getFailPage()
				);
			} else if ( $result->getRefresh() ) {
				$output->addValue(
					null,
					'errors',
					$result->getErrors()
				);
			} else {
				$output->addValue(
					null,
					'redirect',
					$adapter->getThankYouPage()
				);
			}
		} else {
			// Don't let people continue if they failed a token check!
			$output->addValue(
				null,
				'errors',
				array( 'token-mismatch' => $this->msg( 'donate_interface-cc-token-expired' )->text() )
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
		return true;
	}

	public function isReadMode() {
		return false;
	}
}
