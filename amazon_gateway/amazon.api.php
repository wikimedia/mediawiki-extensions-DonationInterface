<?php

class AmazonBillingApi extends ApiBase {
	protected $allowedParams = [
		'amount',
		'billingAgreementId',
		'currency',
		'orderReferenceId',
		'recurring',
		'wmf_token',
	];

	public function execute() {
		DonationInterface::setSmashPigProvider( 'amazon' );
		$output = $this->getResult();
		$recurring = $this->getParameter( 'recurring' );
		$token = $this->getParameter( 'wmf_token' );
		$adapterParams = [
			'external_data' => [
				'amount' => $this->getParameter( 'amount' ),
				'currency' => $this->getParameter( 'currency' ),
				'recurring' => $recurring,
				'wmf_token' => $token,
			],
		];

		$adapterClass = DonationInterface::getAdapterClassForGateway( 'amazon' );
		// @var AmazonAdapter
		$adapter = new $adapterClass( $adapterParams );

		if ( $adapter->getErrorState()->hasErrors() ) {
			$output->addValue(
				null,
				'errors',
				DonationApi::serializeErrors(
					$adapter->getErrorState()->getErrors(),
					$adapter
				)
			);
		} elseif ( $token && $adapter->checkTokens() ) {
			if ( $recurring ) {
				$adapter->addRequestData( [
					'subscr_id' => $this->getParameter( 'billingAgreementId' ),
				] );
			} else {
				$adapter->addRequestData( [
					'order_reference_id' => $this->getParameter( 'orderReferenceId' ),
				] );
			}
			$result = $adapter->doPayment();
			if ( $result->isFailed() ) {
				$output->addvalue(
					null,
					'redirect',
					ResultPages::getFailPage( $adapter )
				);
			} elseif ( $result->getRefresh() ) {
				$output->addValue(
					null,
					'errors',
					DonationApi::serializeErrors(
						$result->getErrors(),
						$adapter
					)
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
				[ 'token-mismatch' => $this->msg( 'donate_interface-cc-token-expired' )->text() ]
			);
		}
	}

	public function getAllowedParams() {
		$params = [];
		foreach ( $this->allowedParams as $param ) {
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
