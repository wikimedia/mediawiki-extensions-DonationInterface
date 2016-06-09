<?php

class RiskScore implements StagingHelper {
	/*
	 * Send our fraud score to Adyen so we can see it in the console
	 */
	public function stage( GatewayType $adapter, $normalized, &$stagedData ) {
		// This isn't smart enough to grab a new value here;
		// Late-arriving values have to trigger a restage via addData or
		// this will always equal the risk_score at the time of object
		// construction. Still need the formatting, though.
		if ( isset( $normalized['risk_score'] ) ) {
			// Cap the score we send to Adyen since they automatically
			// decline any transaction with offset > 100
			$maximumScore = $adapter->getGlobal( 'MaxRiskScore' );
			$stagedScore = min( $normalized['risk_score'], $maximumScore );
			$stagedData['risk_score'] = ( string ) round( $stagedScore );
		}
	}
}
