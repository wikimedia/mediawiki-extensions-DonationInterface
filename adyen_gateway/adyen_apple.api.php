<?php

use SmashPig\PaymentProviders\PaymentProviderFactory;
use SmashPig\PaymentProviders\PayPal\PaymentProvider;
use Wikimedia\ParamValidator\ParamValidator;

class AdyenAppleApi extends DonationApiBase {
	public function getAllowedParams() {
		return [
			'validation_url' => [ ParamValidator::PARAM_TYPE => 'string' ],
			'wmf_token' => [ ParamValidator::PARAM_TYPE => 'string' ],
			'gateway' => [ ParamValidator::PARAM_TYPE => 'string' ]
		];
	}

	/**
	 * Makes a SmashPig library call to get an Apple Pay session
	 *
	 * @throws ApiUsageException
	 */
	public function execute() {
		if ( RequestContext::getMain()->getUser()->pingLimiter( 'applesession' ) ) {
			// Allow rate limiting by setting e.g. $wgRateLimits['applesession']['ip']
			return;
		}
		$this->gateway = 'adyen';
		if ( !$this->setAdapterAndValidate() ) {
			return;
		}
		$provider = PaymentProviderFactory::getProviderForMethod( 'apple' );
		'@phan-var PaymentProvider $provider';
		// Apple wants a bare domain name in their session start request, so
		// we strip off the detected protocol and slashes from the server.
		$domainName = str_replace(
			WebRequest::detectProtocol() . '://', '', WebRequest::detectServer()
		);
		$session = $provider->createPaymentSession( [
			'validation_url' => $this->getParameter( 'validation_url' ),
			'domain_name' => $domainName
		] );
		$this->getResult()->addValue( null, 'session', $session );
	}
}
