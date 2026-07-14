<?php

use Wikimedia\ParamValidator\ParamValidator;

class GravyCheckoutSessionApi extends DonationApiBase {

	public function getAllowedParams(): array {
		return [
			'amount' => [ ParamValidator::PARAM_TYPE => 'string' ],
			'payment_method' => [ ParamValidator::PARAM_TYPE => 'string' ],
			'currency' => [ ParamValidator::PARAM_TYPE => 'string' ],
			'country' => [ ParamValidator::PARAM_TYPE => 'string' ],
			'recurring' => [ ParamValidator::PARAM_TYPE => 'integer' ],
			'wmf_token' => [ ParamValidator::PARAM_TYPE => 'string' ],
			'gateway' => [ ParamValidator::PARAM_TYPE => 'string' ],
		];
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		if ( RequestContext::getMain()->getUser()->pingLimiter( 'gravycheckoutsession' ) ) {
			return;
		}
		$this->gateway = 'gravy';

		if ( !$this->setAdapterAndValidate() ) {
			return;
		}

		$adapter = $this->adapter;
		'@phan-var GravyAdapter $adapter';
		$session = $adapter->getCheckoutSession();
		if ( !$session->isSuccessful() ) {
			$this->getResult()->addValue(
				null,
				'errors',
				[ 'general' => 'create-session-failed' ]
			);
			return;
		}

		$this->getResult()->addValue(
			null,
			'checkout_session',
			[
				'session_id' => $session->getPaymentSession()
			]
		);
	}
}
