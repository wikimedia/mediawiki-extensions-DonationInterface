<?php

class AmazonBillingApi extends ApiBase {
	protected $allowedParams = array(
		'amount',
		'currency_code',
		'orderReferenceId',
		'token',
	);

	public function execute() {
		$orderReferenceId = $this->getParameter( 'orderReferenceId' );
		$adapterParams = array(
			'api_request' => true,
			'external_data' => array(
				'amount' => $this->getParameter( 'amount' ),
				'currency_code' => $this->getParameter( 'currency_code' ),
			),
		);

		$adapter = new AmazonAdapter( $adapterParams );

		if ( $adapter->checkTokens() ) {
			$adapter->addRequestData( array(
				'order_reference_id' => $orderReferenceId,
			) );
			$result = $adapter->doPayment();
			if ( $result->getRefresh() ) {
				$this->getResult()->addValue(
					null,
					'errors',
					$result->getErrors()
				);
			} else {
				$this->getResult()->addValue(
					null,
					'success',
					!$result->isFailed()
				);
			}
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
		return true;
	}

	public function isReadMode() {
		return false;
	}
}
