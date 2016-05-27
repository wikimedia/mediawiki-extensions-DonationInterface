<?php

/**
 * Set staged language to a locale ID with country information
 */
class DonorLocale
	implements StagingHelper
{
	public function stage( GatewayType $adapter, $normalized, &$staged_data ) {
		if ( isset( $normalized['language'] ) && isset( $normalized['country'] ) ) {
			$parts = explode( '_', $normalized['language'] );
			$staged_data['language'] = "{$parts[0]}_{$normalized['country']}";
		}
	}
}
