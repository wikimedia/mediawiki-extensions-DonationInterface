<?php

class SmsOptin implements StagingHelper {

	/**
	 * When there is a sms optin variant, don't send the phone
	 * to the payment processor
	 *
	 * @param GatewayType $adapter
	 * @param array $normalized Donation data in normalized form.
	 * @param array &$stagedData Reference to output data.
	 */
	public function stage( GatewayType $adapter, $normalized, &$stagedData ) {
		if ( isset( $stagedData['phone'] ) ) {
			unset( $stagedData['phone'] );
		}
	}
}
