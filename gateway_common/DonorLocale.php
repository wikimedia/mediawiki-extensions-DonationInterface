<?php

/**
 * Set staged language to a locale ID with country information
 * FIXME: use BCP-47 language tags internally, and something that understands
 * http://www.iana.org/assignments/language-subtag-registry/language-subtag-registry
 * to translate between tags like 'yue' and ALPHA2 'zh'
 */
class DonorLocale
	implements StagingHelper, UnstagingHelper {
	public function stage( GatewayType $adapter, $normalized, &$staged_data ) {
		if ( isset( $normalized['language'] ) && isset( $normalized['country'] ) ) {
			$language = $normalized['language'];
			// Get the first part, before any _ or - separator.
			foreach ( [ '_', '-' ] as $separator ) {
				$pos = strpos( $language, $separator );
				if ( $pos !== false ) {
					$language = substr( $language, 0, $pos );
				}
			}

			$staged_data['language'] = "{$language}_{$normalized['country']}";
		}
	}

	public function unstage( GatewayType $adapter, $stagedData, &$unstagedData ) {
		if ( isset( $stagedData['language'] ) ) {
			$parts = explode( '_', $stagedData['language'] );
			$unstagedData['language'] = $parts[0];
		}
	}
}
