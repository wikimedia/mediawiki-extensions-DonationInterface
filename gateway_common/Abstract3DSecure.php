<?php

abstract class Abstract3DSecure implements StagingHelper {

	protected static $supportedSubMethods = [ 'mc', 'visa' ];

	/**
	 * To set 3DSecure flags, we need a supported payment submethod,
	 * and we also need to know the country and currency.
	 *
	 * @param array $normalized
	 * @return bool
	 */
	protected function canSet3dSecure( $normalized ) {
		if ( empty( $normalized['payment_submethod'] ) ) {
			return false;
		}
		if ( !in_array(
			$normalized['payment_submethod'],
			self::$supportedSubMethods
		) ) {
			return false;
		};
		return !empty( $normalized['currency'] ) &&
			!empty( $normalized['country'] );
	}

	protected function use3dSecure( $adapter, $normalized ) {
		$currency = $normalized['currency'];
		$country = $normalized['country'];
		$use3ds = CurrencyCountryRule::isEnabled(
			$adapter->getGlobal( '3DSRules' ),
			$currency,
			$country
		);
		if ( $use3ds ) {
			$logger = DonationLoggerFactory::getLogger( $adapter, '', $adapter );
			$logger->info( "3dSecure enabled for $currency in $country" );
		}
		return $use3ds;
	}
}
