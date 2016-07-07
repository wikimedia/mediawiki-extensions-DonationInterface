<?php

/**
 * Stage: amount
 *
 * Adyen requires amounts to be passed as an integer representing the value
 * in minor units for that currency.  Currencies that lack a minor unit
 * (such as JPY) are simply passed as is.
 * For example: USD 10.50 would be changed to 1050, JPY 150 would be passed as 150.
 */
class AmountInMinorUnits implements StagingHelper, UnstagingHelper {
	public function stage( GatewayType $adapter, $normalized, &$stagedData ) {
		if ( empty( $normalized['amount'] ) || empty( $normalized['currency_code'] ) ) {
			//can't do anything with amounts at all. Just go home.
			unset( $stagedData['amount'] );
			return;
		}

		$amount = $normalized['amount'];
		if ( DataValidator::is_exponent3_currency( $normalized['currency_code'] ) ) {
			$stagedData['amount'] = $amount * 1000;
		} elseif ( DataValidator::is_fractional_currency( $normalized['currency_code'] ) ) {
			$stagedData['amount'] = $amount * 100;
		} else {
			$amount = floor( $amount );
			$stagedData['amount'] = $amount;
		}

	}

	public function unstage( GatewayType $adapter, $stagedData, &$unstagedData ) {
		if ( DataValidator::is_exponent3_currency( $stagedData['currency_code'] ) ) {
			$unstagedData['amount'] = $stagedData['amount'] / 1000;
		} elseif ( DataValidator::is_fractional_currency( $stagedData['currency_code'] ) ) {
			$unstagedData['amount'] = $stagedData['amount'] / 100;
		} else {
			$unstagedData['amount'] = $stagedData['amount'];
		}

	}
}