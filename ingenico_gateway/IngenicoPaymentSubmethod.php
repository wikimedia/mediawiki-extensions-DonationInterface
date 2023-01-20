<?php

use SmashPig\PaymentProviders\Ingenico\ReferenceData;

/**
 * Unstaging helper to get the payment submethod that was actually used.
 * Useful for when we hide the card selector or the donor uses a different card than was selected.
 */
class IngenicoPaymentSubmethod implements UnstagingHelper {

	public function unstage( GatewayType $adapter, $stagedData, &$unstagedData ) {
		$paymentID = $stagedData['payment_submethod'] ?? false;
		try {
			$fromReferenceData = ReferenceData::decodePaymentMethod( $paymentID );
			$unstagedData['payment_submethod'] = $fromReferenceData['payment_submethod'];
		} catch ( OutOfBoundsException $ex ) {
			$subMethods = $adapter->getPaymentSubmethods();

			foreach ( $subMethods as $key => $subMethod ) {

				if ( $subMethod['paymentproductid'] == $paymentID ) {
					$unstagedData['payment_submethod'] = $key;
					break;
				}
			}
		}
	}
}
