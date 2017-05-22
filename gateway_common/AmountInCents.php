<?php

/**
 * Stage: gross
 *
 * Amounts are usually passed as an integer, and usually x100 rather than
 * using the currency's true fractional denomination ("cents").  Currencies
 * without a fractional unit are still multiplied, so we have to floor to
 * avoid killing the payment processor.
 * For example: JPY 1000.05 would be changed to 100005, but should be 100000.
 */
class AmountInCents implements StagingHelper, UnstagingHelper {
	public function stage( GatewayType $adapter, $normalized, &$stagedData ) {
		if (
			empty( $normalized['gross'] ) ||
			empty( $normalized['currency'] ) ||
			!is_numeric( $normalized['gross'] )
		) {
			//can't do anything with amounts at all. Just go home.
			unset( $stagedData['gross'] );
			return;
		}

		$amount = Amount::round( $normalized['gross'], $normalized['currency'] );

		$stagedData['gross'] = $amount * 100;
	}

	public function unstage( GatewayType $adapter, $stagedData, &$unstagedData ) {
		$unstagedData['gross'] = $stagedData['gross'] / 100;
	}
}
