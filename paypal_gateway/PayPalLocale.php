<?php

class PayPalLocale implements StagingHelper {
	public function stage( GatewayType $adapter, $unstagedData, &$stagedData ) {
		// FIXME: Document the upstream source for this reference data.
		$supported_countries = array(
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
		);
		$supported_full_locales = array(
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
		);

		if ( in_array( $unstagedData['country'], $supported_countries ) ) {
			$stagedData['locale'] = $unstagedData['country'];
		}

		$fallbacks = Language::getFallbacksFor( strtolower( $unstagedData['language'] ) );
		array_unshift( $fallbacks, strtolower( $unstagedData['language'] ) );
		foreach ( $fallbacks as $lang ) {
			$locale = "{$lang}_{$unstagedData['country']}";
			if ( in_array( $locale, $supported_full_locales ) ) {
				$stagedData['locale'] = $locale;
				return;
			}
		}
	}
}
