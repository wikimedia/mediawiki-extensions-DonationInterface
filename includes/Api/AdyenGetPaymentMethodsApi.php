<?php
namespace MediaWiki\Extension\DonationInterface\Api;

use SmashPig\PaymentProviders\Adyen\PaymentProvider;

class AdyenGetPaymentMethodsApi extends GetPaymentMethodsApi {
	/**
	 * @inheritDoc
	 * @param PaymentProvider $provider
	 * @suppress PhanParamSignatureMismatch narrower provider type than IPaymentProvider
	 */
	protected function getPaymentMethod( $provider, $params ): array {
		return $provider->getPaymentMethods( $params )->getRawResponse();
	}

	/**
	 * @inheritDoc
	 */
	protected function setGateway(): void {
		$this->gateway = 'adyen';
	}
}
