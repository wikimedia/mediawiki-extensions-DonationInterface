<?php

use SmashPig\PaymentProviders\PaymentProviderFactory;

class AdyenAppleApi extends DonationApiBase {
	public function getAllowedParams() {
		return [
			'validation_url' => [ ApiBase::PARAM_TYPE => 'string' ],
			'wmf_token' => [ ApiBase::PARAM_TYPE => 'string' ],
			'gateway' => [ ApiBase::PARAM_TYPE => 'string' ]
		];
	}

	/**
	 * Makes a SmashPig library call to get an Apple Pay session
	 *
	 * @throws ApiUsageException
	 */
	public function execute() {
		$this->gateway = 'adyen';
		if ( !$this->setAdapterAndValidate() ) {
			return;
		}
		$provider = PaymentProviderFactory::getProviderForMethod( 'apple' );
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
