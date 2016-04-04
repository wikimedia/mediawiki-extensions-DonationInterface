<?php

class WorldpayCurrency implements StagingHelper {
	public function stage( GatewayType $adapter, $normalized, &$stagedData ) {
		$currency = $normalized['currency_code'];
		$codes = $adapter->getConfig( 'currencies' );
		if ( array_key_exists( $currency, $codes ) ) {
			$stagedData['iso_currency_id'] = $codes[$currency];
		}
	}
}
