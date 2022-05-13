<?php

class AmazonBillingApi extends DonationApiBase {
	protected $allowedParams = [
		'amount',
		'billingAgreementId',
		'currency',
		'orderReferenceId',
		'recurring',
		'wmf_token',
	];

	public function execute() {
		$this->gateway = 'amazon';
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
		$this->adapter = new $adapterClass( $adapterParams );

		if ( $this->adapter->getErrorState()->hasErrors() ) {
			$output->addValue(
				null,
				'errors',
				$this->serializeErrors(
					$this->adapter->getErrorState()->getErrors()
				)
			);
		} elseif ( $token && $this->adapter->checkTokens() ) {
			if ( $recurring ) {
				$this->adapter->addRequestData( [
					'subscr_id' => $this->getParameter( 'billingAgreementId' ),
				] );
			} else {
				$this->adapter->addRequestData( [
					'order_reference_id' => $this->getParameter( 'orderReferenceId' ),
				] );
			}
			$result = $this->adapter->doPayment();
			if ( $result->isFailed() ) {
				$failPage = GatewayChooser::buildGatewayPageUrl(
					'amazon',
					[ 'showError' => true ],
					$this->getConfig()
				);
				$output->addvalue(
					null,
					'redirect',
					$failPage
				);
			} elseif ( $result->getRefresh() ) {
				$output->addValue(
					null,
					'errors',
					$this->serializeErrors( $result->getErrors() )
				);
			} else {
				$output->addValue(
					null,
					'redirect',
					ResultPages::getThankYouPage( $this->adapter )
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
}
