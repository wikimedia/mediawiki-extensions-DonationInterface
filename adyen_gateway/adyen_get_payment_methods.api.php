<?php

use SmashPig\PaymentProviders\Adyen\PaymentProvider;
use SmashPig\PaymentProviders\PaymentProviderFactory;
use Wikimedia\ParamValidator\ParamValidator;

class AdyenGetPaymentMethodsApi extends ApiBase {

	public function execute() {
		if ( RequestContext::getMain()->getUser()->pingLimiter( 'getpaymentmethods' ) ) {
			// Allow rate limiting by setting e.g. $wgRateLimits['applesession']['ip']
			return;
		}

		// Set up adyen
		DonationInterface::setSmashPigProvider( 'adyen' );

		// They need to make this call before they even display a form so we won't know amount
		$params = [
			'country' => $this->getParameter( 'country' ),
			'channel' => 'iOS',
		];

		$provider = PaymentProviderFactory::getProviderForMethod( 'apple' );
		'@phan-var PaymentProvider $provider';
		$rawResponse = $provider->getPaymentMethods( $params )->getRawResponse();

		$this->getResult()->addValue( null, 'response', $rawResponse );
	}

	public function getAllowedParams() {
		return [
			'country' => [ ParamValidator::PARAM_TYPE => 'string' ]
		];
	}

	/**
	 * This allows the api to be hit without being logged in
	 * @return false
	 */
	public function isReadMode() {
		return false;
	}

}
