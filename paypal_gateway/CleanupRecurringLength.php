<?php

/**
 * Silly helper to remove field when empty.
 */
class CleanupRecurringLength implements StagingHelper {
	public function stage( GatewayType $adapter, $unstagedData, &$stagedData ) {
		if ( empty( $unstagedData['recurring_length'] ) ) {
			unset( $stagedData['recurring_length'] );
		}
	}
}
