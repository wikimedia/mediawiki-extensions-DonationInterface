<?php

class DonorLanguage implements StagingHelper {
	public function stage( GatewayType $adapter, $normalized, &$stagedData ) {
		$language = $normalized['language'];
		$adapterLanguages = $adapter->getAvailableLanguages();
		if ( !in_array( $language, $adapterLanguages ) ) {
			$fallbacks = WmfFramework::getLanguageFallbacks( $language );
			foreach ( $fallbacks as $fallback ) {
				if ( in_array( $fallback, $adapterLanguages ) ) {
					$language = $fallback;
					break;
				}
			}
		}

		if ( !in_array( $language, $adapterLanguages ) ){
			$language = 'en';
		}

		$stagedData['language'] = $language;
	}
}
