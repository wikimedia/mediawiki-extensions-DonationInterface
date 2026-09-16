<?php

namespace MediaWiki\Extension\DonationInterface\ComboWiki;

class ForbiddenCountryRegistry {

	private const FORBIDDEN_COUNTRY_VIEWS = [
		// Custom view variants
		'FI' => 'finland_notice',
		'RU' => 'russia_notice',

		// Generic fallback view for sanctioned or restricted countries
		'AE' => 'default',
		'BY' => 'default',
		'CD' => 'default',
		'CI' => 'default',
		'CU' => 'default',
		'ID' => 'default',
		'IQ' => 'default',
		'IR' => 'default',
		'KR' => 'default',
		'KP' => 'default',
		'LB' => 'default',
		'LY' => 'default',
		'MM' => 'default',
		'SA' => 'default',
		'SD' => 'default',
		'SO' => 'default',
		'SS' => 'default',
		'SY' => 'default',
		'TR' => 'default',
		'YE' => 'default',
		'ZW' => 'default',
	];

	public static function isForbidden( string $countryCode ): bool {
		$countryCode = strtoupper( trim( $countryCode ) );
		return isset( self::FORBIDDEN_COUNTRY_VIEWS[$countryCode] );
	}

	public static function getViewType( string $countryCode ): string {
		$countryCode = strtoupper( trim( $countryCode ) );
		return self::FORBIDDEN_COUNTRY_VIEWS[$countryCode] ?? 'default';
	}
}
