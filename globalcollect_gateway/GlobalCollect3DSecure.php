<?php

class GlobalCollect3DSecure extends Abstract3DSecure {

	/**
	 * The WebCollect API defaults to 3DSecure disabled, so we have to
	 * flag when to enable it.
	 *
	 * @param GatewayType $adapter
	 * @param array $normalized Donation data in normalized form.
	 * @param array &$stagedData Reference to output data.
	 */
	public function stage( GatewayType $adapter, $normalized, &$stagedData ) {
		if ( !$this->canSet3dSecure( $normalized ) ) {
			return;
		}
		$stagedData['use_authentication'] = $this->isRecommend3dSecure( $adapter, $normalized );
	}
}
