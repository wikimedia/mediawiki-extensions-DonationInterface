<?php

class Ingenico3DSecure extends GlobalCollect3DSecure {

	/**
	 * The Ingenico Connect API defaults to 3DSecure enabled, so we have to
	 * flag when to DISABLE it. By contrast, the WebCollect API defaults to
	 * 3DSecure disabled. The parent class (GlobalCollect3DSecure) logic sets
	 * 'use_authentication' to true when we want 3DSecure, which is mapped to
	 * the USEAUTHENTICATION XML element for WebCollect calls but ignored for
	 * Connect calls. This class takes that value and inverts it, adding a
	 * 'skip_authentication' value that is mapped to the skipAuthentication
	 * json element in hosted payment setup calls.
	 *
	 * @param GatewayType $adapter
	 * @param array $normalized Donation data in normalized form.
	 * @param array &$stagedData Reference to output data.
	 */
	function stage( GatewayType $adapter, $normalized, &$stagedData ) {
		parent::stage( $adapter, $normalized, $stagedData );
		if ( isset( $stagedData['use_authentication'] ) ) {
			$stagedData['skip_authentication'] = !$stagedData['use_authentication'];
		}
	}
}
