<?php

/**
 * Silly helper to remove field when empty.
 */
class CleanupRecurringLength implements StagingHelper {
	public function stage( GatewayType $adapter, $normalized, &$stagedData ) {
		if ( empty( $normalized['recurring_length'] ) ) {
			unset( $stagedData['recurring_length'] );
		}
	}
}
