<?php

class BraintreeAdapter extends GatewayAdapter implements RecurringConversion {
	use RecurringConversionTrait;

	const GATEWAY_NAME = 'Braintree';
	const IDENTIFIER = 'braintree';
	const GLOBAL_PREFIX = 'wgBraintreeGateway';

	protected function defineOrderIDMeta() {
		// TODO: Implement defineOrderIDMeta() method.
	}

	protected function defineReturnValueMap() {
		// TODO: Implement defineReturnValueMap() method.
	}

	public function doPayment() {
		// TODO: Implement doPayment() method.
	}

	public function getCommunicationType() {
		return 'array';
	}

	protected function defineTransactions() {
		// TODO: Implement defineTransactions() method.
	}

	protected function defineAccountInfo() {
		$this->accountInfo = [];
	}

}
