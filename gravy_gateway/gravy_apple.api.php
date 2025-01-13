<?php

use SmashPig\PaymentProviders\Gravy\ApplePayPaymentProvider;
use SmashPig\PaymentProviders\PaymentProviderFactory;
use Wikimedia\ParamValidator\ParamValidator;

class GravyAppleApi extends DonationApiBase {
	public function getAllowedParams(): array {
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
	public function execute(): void {
		if ( RequestContext::getMain()->getUser()->pingLimiter( 'applesession' ) ) {
			// Allow rate limiting by setting e.g. $wgRateLimits['applesession']['ip']
			return;
		}
		$this->gateway = 'gravy';
		if ( !$this->setAdapterAndValidate() ) {
			return;
		}
		$provider = PaymentProviderFactory::getProviderForMethod( 'apple' );
		'@phan-var ApplePayPaymentProvider $provider';
		// Apple wants a bare domain name in their session start request, so
		// we strip off the detected protocol and slashes from the server.
		$domainName = str_replace(
			WebRequest::detectProtocol() . '://', '', WebRequest::detectServer()
		);
		$session = $provider->createPaymentSession( [
			'validation_url' => $this->getParameter( 'validation_url' ),
			'domain_name' => $domainName
		] )->getRawResponse();
		$this->getResult()->addValue( null, 'session', $session );
	}
}
