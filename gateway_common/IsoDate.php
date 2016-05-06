<?php

class IsoDate
	implements
		StagingHelper,
		UnstagingHelper
{
	public function stage( GatewayType $adapter, $normalized, &$staged_data ) {
		// Print timestamp as ISO 8601 string.
		if ( isset( $normalized['date'] ) ) {
			$staged_data['date'] = date( DateTime::ISO8601, $normalized['date'] );
		}
	}

	public function unstage( GatewayType $adapter, $staged_data, &$unstaged_data ) {
		// Parse ISO 8601 string to timestamp.
		if ( isset( $staged_data['date'] ) ) {
			$unstaged_data['date'] = strtotime( $staged_data['date'] );
		}
	}
}
