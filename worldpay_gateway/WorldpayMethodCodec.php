<?php

class WorldpayMethodCodec implements UnstagingHelper {
	public function unstage( GatewayType $adapter, $stagedData, &$unstagedData ) {
		if ( empty( $stagedData['payment_method'] ) || empty( $stagedData['payment_submethod'] ) ) {
			return;
		}
		$paymentMethod = $stagedData['payment_method'];
		$paymentSubmethod = $stagedData['payment_submethod'];

		if ( $paymentMethod == 'cc' ) {
			$byApiName = $adapter->getConfig('payment_submethod_api_names');
			// FIXME: It's not fair that we have to step around incorrectly
			// staged normalized submethod.  Need to be much more careful about
			// blindly copying fields when they are not staged.
			if ( isset( $byApiName[$paymentSubmethod] ) ) {
				$unstagedData['payment_submethod'] = $byApiName[$paymentSubmethod];
			}
		}
	}
}
