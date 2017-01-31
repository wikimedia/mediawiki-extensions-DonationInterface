<?php

/**
 * Set staged language to a locale ID with country information
 */
class DonorLocale
	implements StagingHelper, UnstagingHelper
{
	public function stage( GatewayType $adapter, $normalized, &$staged_data ) {
		if ( isset( $normalized['language'] ) && isset( $normalized['country'] ) ) {
			$parts = explode( '_', $normalized['language'] );
			$staged_data['language'] = "{$parts[0]}_{$normalized['country']}";
		}
	}

	public function unstage( GatewayType $adapter, $stagedData, &$unstagedData ) {
		if ( isset( $stagedData['language'] ) ) {
			$parts = explode( '_', $stagedData['language'] );
			$unstagedData['language'] = $parts[0];
		}
	}
}
