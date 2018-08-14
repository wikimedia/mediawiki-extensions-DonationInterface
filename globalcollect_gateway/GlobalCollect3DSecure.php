<?php

class GlobalCollect3DSecure implements StagingHelper {

	protected static $supportedSubMethods = [ 'mc', 'visa' ];

	/**
	 * The WebCollect API defaults to 3DSecure disabled, so we have to
	 * flag when to enable it.
	 *
	 * @param GatewayType $adapter
	 * @param array $normalized Donation data in normalized form.
	 * @param array $stagedData Reference to output data.
	 */
	function stage( GatewayType $adapter, $normalized, &$stagedData ) {
		if ( !$this->canSet3dSecure( $normalized ) ) {
			return;
		}
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
		$stagedData['use_authentication'] = $use3ds;
	}

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
}
