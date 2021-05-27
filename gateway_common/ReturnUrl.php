<?php

class ReturnUrl implements StagingHelper {

	public function stage( GatewayType $adapter, $normalized, &$staged ) {
		$specialName = str_replace( 'Adapter', 'GatewayResult', get_class( $adapter ) );
		$returnTitle = Title::newFromText( "Special:$specialName" );

		$querySringParams = [
			'order_id' => $normalized['order_id'],
			'wmf_token' => $adapter->token_getSaltedSessionToken(),
		];
		if ( $normalized['recurring'] ) {
			$querySringParams['recurring'] = 1;
		}

		$staged['return_url'] = $returnTitle->getFullURL( $querySringParams, false, PROTO_CURRENT );
	}
}
