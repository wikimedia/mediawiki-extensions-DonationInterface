<?php

class PaypalExpressReturnUrl implements StagingHelper {
	public function stage( GatewayType $adapter, $normalized, &$staged ) {
		$returnTitle = Title::newFromText( 'Special:PaypalExpressGatewayResult' );
		$staged['returnto'] = $returnTitle->getFullURL( array(
			'order_id' => $normalized['order_id'],
			'wmf_token' => $adapter->token_getSaltedSessionToken(),
		), false, PROTO_CURRENT );
	}
}
