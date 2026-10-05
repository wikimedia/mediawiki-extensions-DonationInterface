<?php
namespace MediaWiki\Extension\DonationInterface\Api;

use SmashPig\PaymentProviders\Gravy\ApplePayPaymentProvider;

class GravyAppleSessionApi extends AppleSessionApi {

	/**
	 * @inheritDoc
	 * @param ApplePayPaymentProvider $provider
	 * @suppress PhanParamSignatureMismatch narrower provider type than IPaymentProvider
	 */
	public function createPaymentSession( $provider, $domainName ): array {
		return $provider->createPaymentSession( [
			'validation_url' => $this->getParameter( 'validation_url' ),
			'domain_name' => $domainName
		] )->getRawResponse();
	}

	/**
	 * @inheritDoc
	 */
	protected function setGateway(): void {
		$this->gateway = 'gravy';
	}
}
