<?php

class Ingenico3DSecure extends Simple3DSecure {

	/** @var string[] */
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
