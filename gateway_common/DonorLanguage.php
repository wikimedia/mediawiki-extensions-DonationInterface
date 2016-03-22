<?php

class DonorLanguage implements StagingHelper {
	public function stage( GatewayType $adapter, $unstagedData, &$stagedData ) {
		$language = strtolower( $unstagedData['language'] );
		$adapterLanguages = $adapter->getAvailableLanguages();
		if ( !in_array( $language, $adapterLanguages ) ) {
			$fallbacks = Language::getFallbacksFor( $language );
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
