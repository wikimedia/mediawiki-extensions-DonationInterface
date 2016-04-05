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
			$unstagedData['payment_submethod'] = $byApiName[$paymentSubmethod];
		}
	}
}
