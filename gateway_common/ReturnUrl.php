<?php

class ReturnUrl implements StagingHelper {

	public function stage( GatewayType $adapter, $normalized, &$staged ) {
		$specialName = str_replace( 'Adapter', 'GatewayResult', get_class( $adapter ) );
		$returnTitle = Title::newFromText( "Special:$specialName" );

		$querySringParams = [
			'order_id' => $normalized['order_id'],
			'wmf_token' => $adapter->token_getSaltedSessionToken(),
			'amount' => $normalized['amount'],
			'currency' => $normalized['currency'],
			'payment_method' => $normalized['payment_method'] ?? '',
			'payment_submethod' => $normalized['payment_submethod'] ?? '',
		];
		if ( !empty( $normalized['utm_source'] ) ) {
			$querySringParams['wmf_source'] = $normalized['utm_source'];
		}
		if ( !empty( $normalized['utm_campaign'] ) ) {
			$querySringParams['wmf_campaign'] = $normalized['utm_campaign'];
		}
		if ( !empty( $normalized['utm_medium'] ) ) {
			$querySringParams['wmf_medium'] = $normalized['utm_medium'];
		}
		if ( $normalized['recurring'] ) {
			$querySringParams['recurring'] = 1;
		}

		$staged['return_url'] = $returnTitle->getFullURL( $querySringParams, false, PROTO_CURRENT );
	}
}
