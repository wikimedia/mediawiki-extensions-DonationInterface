<?php

/**
 * Some gateways need a unique ID for every API call, so we append a
 * pseudorandom value to the contribution_tracking ID.
 */
class ContributionTrackingPlusUnique implements StagingHelper, UnstagingHelper {
	public function stage( GatewayType $adapter, $normalized, &$stagedData ) {
		$ctid = $normalized['contribution_tracking_id'];
		//append timestamp to ctid
		$ctid .= '.' . (( microtime( true ) * 1000 ) % 100000); //least significant five
		$stagedData['contribution_tracking_id'] = $ctid;
	}

	public function unstage( GatewayType $adapter, $stagedData, &$unstagedData ) {
		$ctid = $stagedData['contribution_tracking_id'];
		$ctid = explode( '.', $ctid );
		$ctid = $ctid[0];
		$unstagedData['contribution_tracking_id'] = $ctid;
	}
}
