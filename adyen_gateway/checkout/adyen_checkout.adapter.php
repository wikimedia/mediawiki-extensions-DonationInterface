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
}
