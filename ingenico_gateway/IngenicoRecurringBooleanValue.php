<?php

/**
 * Staging helper to convert the recurring values (1 and 0) from numerals to booleans.
 */
class IngenicoRecurringBooleanValue implements StagingHelper {

	public function stage( GatewayType $adapter, $normalized, &$stagedData ) {
			$recurring = $normalized['recurring'] ?? null;
			$stagedData['recurring'] = ( $recurring === '1' || $recurring === "true" );
	}

}
