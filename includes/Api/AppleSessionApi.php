<?php
namespace MediaWiki\Extension\DonationInterface\Api;

use MediaWiki\Api\ApiUsageException;
use MediaWiki\Context\RequestContext;
use MediaWiki\Request\WebRequest;
use SmashPig\PaymentProviders\IPaymentProvider;
use SmashPig\PaymentProviders\PaymentProviderFactory;
use Wikimedia\ParamValidator\ParamValidator;

abstract class AppleSessionApi extends \DonationApiBase {
	/**
	 * Makes API call to fetch the Apple Pay session
	 *
	 * @param IPaymentProvider $provider
	 * @param string $domainName
	 *
	 * @return array
	 */
	abstract public function createPaymentSession( $provider, $domainName ): array;

	/**
	 * Sets the gateway property
	 * @return void
	 */
	abstract protected function setGateway(): void;

	/** @inheritDoc */
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
		$this->setGateway();
		if ( !$this->setAdapterAndValidate() ) {
			return;
		}
		$provider = PaymentProviderFactory::getProviderForMethod( 'apple' );
		'@phan-var IPaymentProvider $provider';
		// Apple wants a bare domain name in their session start request, so
		// we strip off the detected protocol and slashes from the server.
		$domainName = str_replace(
			WebRequest::detectProtocol() . '://', '', WebRequest::detectServer()
		);

		$session = $this->createPaymentSession( $provider, $domainName );
		$this->getResult()->addValue( null, 'session', $session );
	}
}
