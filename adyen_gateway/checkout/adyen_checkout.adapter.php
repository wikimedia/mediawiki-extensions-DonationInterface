<?php

class AdyenCheckoutAdapter extends GatewayAdapter {
	const GATEWAY_NAME = 'AdyenCheckout';
	const IDENTIFIER = 'adyen';
	const GLOBAL_PREFIX = 'wgAdyenCheckoutGateway';

	public function doPayment() {
		// TODO: Implement doPayment() method.
	}

	public function getCommunicationType() {
		// TODO: Implement getCommunicationType() method.
	}

	protected function getBasedir() {
		return __DIR__;
	}

	protected function defineTransactions() {
		// TODO: Implement defineTransactions() method.
	}

	protected function defineAccountInfo() {
		// TODO: Implement defineAccountInfo() method.
	}

	protected function defineReturnValueMap() {
		// TODO: Implement defineReturnValueMap() method.
	}

	protected function defineOrderIDMeta() {
		// TODO: Implement defineOrderIDMeta() method.
	}

	public function getCheckoutConfiguration() {
		return [
			'clientKey' => $this->getAccountConfig( 'ClientKey' ),
			'locale' => str_replace( '_', '-', $this->getData_Staged( 'language' ) ),
			// TODO: either make a getAvailablePaymentMethods request for this
			// or return a paymentMethods array customized to the donor's
			// selected paymentMethod.
			'paymentMethodsResponse' => [
				'paymentMethods' => [ [
					'brands' => [ 'visa', 'mc', 'amex', 'discover', 'cup', 'maestro', 'diners', 'jcb' ],
					'details' => [
						[ 'key' => 'encryptedCardNumber', 'type' => 'cardToken' ],
						[ 'key' => 'encryptedSecurityCode', 'type' => 'cardToken' ],
						[ 'key' => 'encryptedExpiryMonth', 'type' => 'cardToken' ],
						[ 'key' => 'encryptedExpiryYear', 'type' => 'cardToken' ],
						[ 'key' => 'holderName', 'optional' => true, 'type' => 'text' ]
					],
					'type' => 'scheme'
				] ]
			],
			// TODO: maybe make this dynamic based on donor location
			'environment' => $this->getAccountConfig( 'Environment' ),
		];
	}
}
