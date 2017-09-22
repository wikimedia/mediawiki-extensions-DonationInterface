<?php

class PayPalCountry implements UnstagingHelper {

	function unstage( GatewayType $adapter, $stagedData, &$unstagedData ) {
		if ( empty( $stagedData['country'] ) ) {
			return;
		}
		$country = $stagedData['country'];
		// Handle PayPal's mutant Chine country code
		if ( $country === 'C2' ) {
			$country = 'CN';
		}
		if ( CountryValidation::isValidIsoCode( $country ) ) {
			$unstagedData['country'] = $country;
		}
	}
}
