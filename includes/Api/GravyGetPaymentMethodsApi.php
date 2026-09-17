<?php
namespace MediaWiki\Extension\DonationInterface\Api;

use SmashPig\PaymentProviders\Gravy\PaymentProvider;

class GravyGetPaymentMethodsApi extends GetPaymentMethodsApi {
	/**
	 * @inheritDoc
	 * @param PaymentProvider $provider
	 * @suppress PhanParamSignatureMismatch narrower provider type than IPaymentProvider
	 */
	protected function getPaymentMethod( $provider, $params ): array {
		$response = $provider->getPaymentMethods( $params );
		return [
			'paymentMethods' => $response->getPaymentMethods()
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function setGateway(): void {
		$this->gateway = 'gravy';
	}
}
