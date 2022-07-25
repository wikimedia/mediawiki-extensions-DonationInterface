<?php

// TODO: deleteme (along with all the other GlobalCollect stuff!)
// Still keeping this working till we can delete the GC code and
// tests and disentangle it from the Ingenico code.
class GlobalCollect3DSecure extends Abstract3DSecure {

	protected static $supportedSubMethods = [
		'amex',
		'cb',
		'diners',
		'discover',
		'elo',
		'mc',
		'visa'
	];

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

	/**
	 * To set 3DSecure flags, we need a supported payment submethod,
	 * and we also need to know the country and currency.
	 *
	 * @param array $normalized
	 * @return bool
	 */
	protected function canSet3dSecure( array $normalized ): bool {
		if ( empty( $normalized['payment_submethod'] ) ) {
			return false;
		}
		if ( !in_array(
			$normalized['payment_submethod'],
			self::$supportedSubMethods
		) ) {
			return false;
		}
		return parent::canSet3dSecure( $normalized );
	}
}
