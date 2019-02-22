<?php

class PaypalLegacyLocale implements StagingHelper {
	public function stage( GatewayType $adapter, $normalized, &$stagedData ) {
		// FIXME: Document the upstream source for this reference data.
		$supported_countries = [
			'AU',
			'AT',
			'BE',
			'BR',
			'CA',
			'CH',
			'CN',
			'DE',
			'ES',
			'GB',
			'FR',
			'IT',
			'NL',
			'PL',
			'PT',
			'RU',
			'US',
		];
		$supported_full_locales = [
			'da_DK',
			'he_IL',
			'id_ID',
			'jp_JP',
			'no_NO',
			'pt_BR',
			'ru_RU',
			'sv_SE',
			'th_TH',
			'tr_TR',
			'zh_CN',
			'zh_HK',
			'zh_TW',
		];

		if ( in_array( $normalized['country'], $supported_countries ) ) {
			$stagedData['locale'] = $normalized['country'];
		}

		$fallbacks = Language::getFallbacksFor( $normalized['language'] );
		array_unshift( $fallbacks, $normalized['language'] );
		foreach ( $fallbacks as $lang ) {
			$locale = "{$lang}_{$normalized['country']}";
			if ( in_array( $locale, $supported_full_locales ) ) {
				$stagedData['locale'] = $locale;
				return;
			}
		}
	}
}
