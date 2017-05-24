<?php

class AmazonBillingApi extends ApiBase {
	protected $allowedParams = array(
		'amount',
		'billingAgreementId',
		'currency',
		'orderReferenceId',
		'recurring',
		'wmf_token',
	);

	public function execute() {
		$output = $this->getResult();
		$recurring = $this->getParameter( 'recurring');
		$token = $this->getParameter( 'wmf_token' );
		$adapterParams = array(
			'external_data' => array(
				'amount' => $this->getParameter( 'amount' ),
				'currency' => $this->getParameter( 'currency' ),
				'recurring' => $recurring,
				'wmf_token' => $token,
			),
		);

		$adapter = new AmazonAdapter( $adapterParams );

		if ( $adapter->getErrorState()->hasErrors() ) {
			$output->addValue(
				null,
				'errors',
				$adapter->getErrorState()->getErrors()
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
					ResultPages::getFailPage( $adapter )
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
					ResultPages::getThankYouPage( $adapter )
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
