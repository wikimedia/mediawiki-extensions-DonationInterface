<?php

class CurrencyCountryRule {
	/**
	 * Looks up whether a rule is enabled for a particular currency and country
	 * Example rules:
	 * $use3ds = [
	 *    'EUR' => [ 'FR', 'DE' ]
	 * ]; # True when currency is EUR and country is FR or DE
	 * $use3ds = [
	 *    'INR' => 'IN'
	 * ]; # True only when currency is INR and country is IN
	 * $use3ds = [
	 *    'INR' => []
	 * ]; # True when currency is INR, for all countries
	 *
	 * @param array $rule structured as described above
	 * @param string $currency
	 * @param string $country
	 * @return bool
	 */
	public static function isEnabled( $rule, $currency, $country ) {
		$country = strtoupper( $country );
		$currency = strtoupper( $currency );
		$isEnabled = false;
		if ( array_key_exists( $currency, $rule ) ) {
			if ( !is_array( $rule[$currency] ) ) {
				if ( $rule[$currency] === $country ) {
					$isEnabled = true;
				}
			} else {
				if ( empty( $rule[$currency] ) || in_array( $country, $rule[$currency] ) ) {
					$isEnabled = true;
				}
			}
		}
		return $isEnabled;
	}
}
