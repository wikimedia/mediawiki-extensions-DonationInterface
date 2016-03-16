<?php

/**
 * Stage: amount
 *
 * Amounts are usually passed as an integer, and usually x100 rather than
 * using the currency's true fractional denomination ("cents").  Currencies
 * without a fractional unit are still multiplied, so we have to floor to
 * avoid killing the payment processor.
 * For example: JPY 1000.05 would be changed to 100005, but should be 100000.
 */
class AmountInCents implements StagingHelper, UnstagingHelper {
	public function stage( GatewayType $adapter, $unstagedData, &$stagedData ) {
		if ( empty( $unstagedData['amount'] ) || empty( $unstagedData['currency_code'] ) ) {
			//can't do anything with amounts at all. Just go home.
			unset( $stagedData['amount'] );
			return;
		}

		$amount = $unstagedData['amount'];
		if ( !DataValidator::is_fractional_currency( $unstagedData['currency_code'] ) ) {
			$amount = floor( $amount );
		}

		$stagedData['amount'] = $amount * 100;
	}

	public function unstage( GatewayType $adapter, $stagedData, &$unstagedData ) {
		$unstagedData['amount'] = $stagedData['amount'] / 100;
	}
}
