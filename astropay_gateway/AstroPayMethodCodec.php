<?php

class AstroPayMethodCodec implements UnstagingHelper {
	/**
	 * Transforms the astropay payment method into our method name
	 */
	public function unstage( GatewayType $adapter, $stagedData, &$unstagedData ) {
		$method = $stagedData['payment_method'];
		$bank = $stagedData['bank_code'];
		if ( !$method || !$bank ) {
			return;
		}
		$filter = function ( $submethod ) use ( $method, $bank ) {
			$group = $submethod['group'];
			return $method === $group && $submethod['bank_code'] === $bank;
		};
		$candidates = array_filter( $adapter->getPaymentSubmethods(), $filter );

		if ( count( $candidates ) !== 1 ) {
			throw new UnexpectedValueException( "No unique payment submethod defined for payment method $method and bank code $bank." );
		}
		$keys = array_keys( $candidates );
		$unstagedData['payment_submethod'] = $keys[0];
	}
}
