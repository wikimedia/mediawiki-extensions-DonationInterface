<?php

class IngenicoFormVariant implements StagingHelper {

	/**
	 * Sets an alternate processor_form when 3DSecure is turned on.
	 * This is mapped to the hostedCheckoutSpecificInput/variant
	 * field in createHostedCheckout.
	 * TODO: could use this to choose from a configurable range of
	 * processor form variants depending on configuration.
	 *
	 * @param GatewayType $adapter
	 * @param array $normalized Donation data in normalized form.
	 * @param array &$stagedData Reference to output data.
	 */
	public function stage( GatewayType $adapter, $normalized, &$stagedData ) {
		$variants = $adapter->getGlobal( 'HostedFormVariants' );
		if (
			isset( $stagedData['use_authentication'] ) &&
			$stagedData['use_authentication'] === true
		) {
			$stagedData['processor_form'] = $variants['redirect'];
		} else {
			$stagedData['processor_form'] = $variants['iframe'];
		}
	}
}
