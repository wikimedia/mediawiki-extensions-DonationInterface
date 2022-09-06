<?php

class PayPalAmount extends Amount implements StagingHelper {

	public function stage( GatewayType $adapter, $normalized, &$stagedData ) {
		$stagedData[ 'amount' ] = static::round( $normalized[ 'amount' ], $normalized[ 'currency' ] );
	}

	public static function is_fractional_currency( $currency_code ) {
		// cause this function only used for paypal, no need to check more than paypal supported currencies
		// https://developer.paypal.com/braintree/docs/reference/general/currencies
		$non_fractional_currencies = [ 'JPY', 'HUF', 'TWD' ];

		if ( in_array( strtoupper( $currency_code ), $non_fractional_currencies ) ) {
			return false;
		}
		return true;
	}
}
