<?php

/**
 * Some gateways need a unique ID for every API call, so we append a
 * pseudorandom value to the contribution_tracking ID.
 */
class ContributionTrackingPlusUnique implements StagingHelper, UnstagingHelper {
	public function stage( GatewayType $adapter, $normalized, &$stagedData ) {
		if ( !isset( $normalized['contribution_tracking_id'] ) ) {
			// Note that we should only reach this condition during testing.
			// A ctid will have been assigned, so we have to pointedly not care today.
			return;
		}
		$ctid = $normalized['contribution_tracking_id'];
		// append timestamp to ctid
		$suffix = (string)( ( microtime( true ) * 1000 ) % 100000 ); // least significant five
		$suffix = str_pad( $suffix, 5, '0', STR_PAD_LEFT );
		$ctid .= '.' . $suffix;
		$stagedData['contribution_tracking_id'] = $ctid;
	}

	public function unstage( GatewayType $adapter, $stagedData, &$unstagedData ) {
		$ctid = $stagedData['contribution_tracking_id'];
		$ctid = explode( '.', $ctid );
		$ctid = $ctid[0];
		$unstagedData['contribution_tracking_id'] = $ctid;
	}
}
