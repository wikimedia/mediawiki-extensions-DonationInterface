<?php

class WorldpayMethodCodec implements UnstagingHelper {
	public function unstage( GatewayType $adapter, $stagedData, &$unstagedData ) {
		if ( empty( $stagedData['payment_method'] ) || empty( $stagedData['payment_submethod'] ) ) {
			return;
		}
		$paymentMethod = $stagedData['payment_method'];
		$paymentSubmethod = $stagedData['payment_submethod'];
		if ( $paymentMethod == 'cc' ) {
			$unstagedData['payment_submethod'] =
				$this->get_payment_method_name_from_api_name( $adapter, $paymentSubmethod );
		}
	}

	protected function get_payment_method_name_from_api_name ( GatewayType $adapter, $api_name ) {
		foreach ( $adapter->getPaymentSubmethods() as $name => $info ) {
			if ( $api_name === $info['api_name'] ) {
				return $name;
			}
		}
		return null;
	}
}
