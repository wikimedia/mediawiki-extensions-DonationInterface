<?php

class Ingenico3DSecure extends Abstract3DSecure {

	/**
	 * The Ingenico Connect API defaults to 3DSecure enabled, so we have to
	 * flag when to DISABLE it. This class inverts the value returned from
	 * the base logic, adding a 'skip_authentication' value that is mapped
	 * to the skipAuthentication json element in hosted payment setup calls.
	 *
	 * @param GatewayType $adapter
	 * @param array $normalized Donation data in normalized form.
	 * @param array &$stagedData Reference to output data.
	 */
	public function stage( GatewayType $adapter, $normalized, &$stagedData ) {
		if ( !$this->canSet3dSecure( $normalized ) ) {
			return;
		}
		$stagedData['skip_authentication'] = !$this->use3dSecure( $adapter, $normalized );
	}
}
