<?php

class AmazonBillingApi extends ApiBase {
	protected $allowedParams = array(
		'amount',
		'currency_code',
		'orderReferenceId',
		'token',
	);

	public function execute() {
		$output = $this->getResult();
		$orderReferenceId = $this->getParameter( 'orderReferenceId' );
		$adapterParams = array(
			'api_request' => true,
			'external_data' => array(
				'amount' => $this->getParameter( 'amount' ),
				'currency_code' => $this->getParameter( 'currency_code' ),
			),
		);

		$adapter = new AmazonAdapter( $adapterParams );

		if ( !$adapter->validatedOK() ) {
			$output->addValue(
				null,
				'errors',
				$adapter->getValidationErrors()
			);
		} else if ( $adapter->checkTokens() ) {
			$adapter->addRequestData( array(
				'order_reference_id' => $orderReferenceId,
			) );
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
		return true;
	}

	public function isReadMode() {
		return false;
	}
}
