<?php

/**
 *	Unstaging helper to get the payment sumethod that was actually used.
 * Useful for when we hide the card selector or the donor uses a different card than was selected.
 */
class IngenicoPaymentSubmethod implements UnstagingHelper {

	public function unstage( GatewayType $adapter, $stagedData, &$unstagedData ) {
		$paymentID = $stagedData['payment_product_id'] ?? false;
		$subMethods = $adapter->getPaymentSubmethods();

		foreach ( $subMethods as $key => $subMethod ) {

			if ( $subMethod['paymentproductid'] == $paymentID ) {
				$unstagedData['payment_submethod'] = $key;
				break;
			}
		}
	}
}
