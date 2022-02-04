<?php

abstract class Abstract3DSecure implements StagingHelper {

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
		// We SHOULD always have these, but we check here to make
		// sure we don't get errors in isRecommend3dSecure.
		return !empty( $normalized['currency'] ) &&
			!empty( $normalized['country'] );
	}

	/**
	 * Determines whether a given donation should be sent through 3D Secure
	 * authentication. We configure this on our side based on a global
	 * variable that specifies combinations of currency and country. There
	 * may be additional rules configured in the processor console.
	 *
	 * @param GatewayType $adapter
	 * @param array $normalized
	 * @return bool true when we want to send the donor through 3D Secure
	 */
	protected function isRecommend3dSecure( GatewayType $adapter, array $normalized ): bool {
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
