<?php

use MediaWiki\Title\Title;

class ReturnUrl implements StagingHelper {

	/** @inheritDoc */
	public function stage( GatewayType $adapter, $normalized, &$staged ) {
		$queryStringParams = [
			'order_id' => $normalized['order_id'],
			'wmf_token' => $adapter->token_getSaltedSessionToken(),
			'amount' => $normalized['amount'],
			'currency' => $normalized['currency'],
			'payment_method' => $normalized['payment_method'] ?? '',
			'payment_submethod' => $normalized['payment_submethod'] ?? '',
		];

		if ( ( $normalized['result_page'] ?? '' ) === 'combowiki' ) {
			$specialName = 'ComboWikiGatewayResult';
			$queryStringParams['gateway'] = $normalized['gateway'] ?? '';
		} else {
			$specialName = str_replace( 'Adapter', 'GatewayResult', get_class( $adapter ) );
		}
		$returnTitle = Title::newFromText( "Special:$specialName" );

		if ( !empty( $normalized['utm_source'] ) ) {
			$queryStringParams['wmf_source'] = $normalized['utm_source'];
		}
		if ( !empty( $normalized['utm_campaign'] ) ) {
			$queryStringParams['wmf_campaign'] = $normalized['utm_campaign'];
		}
		if ( !empty( $normalized['utm_medium'] ) ) {
			$queryStringParams['wmf_medium'] = $normalized['utm_medium'];
		}
		if ( $normalized['recurring'] ) {
			$queryStringParams['recurring'] = 1;
		}

		$staged['return_url'] = $returnTitle->getFullURL( $queryStringParams, false, PROTO_CURRENT );
	}
}
