<?php

class WorldpayEmail implements StagingHelper {
	/**
	 * Provide email search in the Worldpay console by hiding garishly mangled email in a bizarre field
	 */
	public function stage( GatewayType $adapter, $unstagedData, &$stagedData ) {
		$alphanumeric = preg_replace('/[^0-9a-zA-Z]/', ' ', $stagedData['email']);
		$stagedData['merchant_reference_2'] = $alphanumeric;
	}
}
