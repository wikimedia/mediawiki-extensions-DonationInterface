<?php

class WorldpayCurrency implements StagingHelper {
	public function stage( GatewayType $adapter, $unstagedData, &$stagedData ) {
		$currency = $unstagedData['currency_code'];
		if ( array_key_exists( $currency, WorldpayAdapter::$CURRENCY_CODES ) ) {
			$stagedData['iso_currency_id'] = WorldpayAdapter::$CURRENCY_CODES[$currency];
		}
	}
}
