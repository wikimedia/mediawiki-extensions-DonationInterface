<?php

class WorldpayCurrency implements StagingHelper {
	public function stage( GatewayType $adapter, $unstagedData, &$stagedData ) {
		$currency = $unstagedData['currency_code'];
		$codes = $adapter->getConfig( 'currencies' );
		if ( array_key_exists( $currency, $codes ) ) {
			$stagedData['iso_currency_id'] = $codes[$currency];
		}
	}
}
