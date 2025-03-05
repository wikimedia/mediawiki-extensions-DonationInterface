<?php

use MediaWiki\Title\Title;

class PaypalExpressReturnUrl implements StagingHelper {
	public function stage( GatewayType $adapter, $normalized, &$staged ) {
		$returnTitle = Title::newFromText( 'Special:PaypalExpressGatewayResult' );

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
