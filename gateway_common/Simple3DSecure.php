<?php

class Simple3DSecure extends Abstract3DSecure implements StagingHelper {

	/**
	 * @param GatewayType $adapter
	 * @param array $normalized Donation data in normalized form.
	 * @param array &$stagedData Reference to output data.
	 */
	public function stage( GatewayType $adapter, $normalized, &$stagedData ) {
		if ( !$this->canSet3dSecure( $normalized ) ) {
			return;
		}
		$stagedData['use_3d_secure'] = $this->isRecommend3dSecure( $adapter, $normalized );
	}

	/**
	 * To set 3DSecure flags, we need a supported payment submethod,
	 * and we also need to know the country and currency.
	 *
	 * @param array $normalized
	 * @return bool
	 */
	protected function canSet3dSecure( array $normalized ): bool {
		if ( $normalized['payment_method'] !== 'cc' ) {
			return false;
		}
		return parent::canSet3dSecure( $normalized );
	}
}
