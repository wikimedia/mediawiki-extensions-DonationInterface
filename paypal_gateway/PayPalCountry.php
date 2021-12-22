<?php

class PayPalCountry implements UnstagingHelper {

	/** @var string[] */
	private static $nonStandardCodes = [
		'C2' => 'CN', // mutant China code for merchants outside of Chine
		'AN' => 'NL', // Netherlands Antilles is part of Netherlands since 2010
	];

	public function unstage( GatewayType $adapter, $stagedData, &$unstagedData ) {
		if ( empty( $stagedData['country'] ) ) {
			return;
		}
		$country = $stagedData['country'];

		if ( array_key_exists( $country, self::$nonStandardCodes ) ) {
			$country = self::$nonStandardCodes[$country];
		}
		if ( CountryValidation::isValidIsoCode( $country ) ) {
			$unstagedData['country'] = $country;
		}
	}
}
