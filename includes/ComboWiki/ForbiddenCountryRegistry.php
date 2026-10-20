<?php

namespace MediaWiki\Extension\DonationInterface\ComboWiki;

class ForbiddenCountryRegistry {
	/*
	 * Custom view variants for assign country with special frontend design
	 */
	private const FORBIDDEN_COUNTRY_VIEWS = [
		'FI' => 'finland_notice',
		'RU' => 'russia_notice',
	];

	/**
	 * Check if a country code is marked as forbidden.
	 * @param string $countryCode
	 * @return bool
	 */
	public static function isForbidden( string $countryCode ): bool {
		global $wgDonationInterfaceForbiddenCountries;
		$countryCode = strtoupper( trim( $countryCode ) );
		return in_array( $countryCode, $wgDonationInterfaceForbiddenCountries ?? [], true );
	}

	/**
	 * Get the view type for a forbidden country.
	 * @param string $countryCode
	 * @return string
	 */
	public static function getViewType( string $countryCode ): string {
		$countryCode = strtoupper( trim( $countryCode ) );
		return self::FORBIDDEN_COUNTRY_VIEWS[$countryCode] ?? 'default';
	}
}
