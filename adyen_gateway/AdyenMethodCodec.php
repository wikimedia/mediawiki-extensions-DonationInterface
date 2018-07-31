<?php

/**
 * Convert our payment submethods into Adyen brandCodes.
 * https://docs.adyen.com/developers/payment-methods/payment-methods-overview
 */
class AdyenMethodCodec implements StagingHelper {
	/**
	 * Stage: brandCode
	 * @inheritdoc
	 */
	public function stage( GatewayType $adapter, $normalized, &$stagedData ) {
		if ( empty( $normalized['payment_submethod'] ) ) {
			return;
		}
		$payment_submethod = $normalized['payment_submethod'];
		$submethod_data = $adapter->getPaymentSubmethodMeta( $payment_submethod );
		if ( isset( $submethod_data['brandCode'] ) ) {
			$stagedData['payment_product'] = $submethod_data['brandCode'];
		} else {
			// In a surprisingly large number of cases, our internal code
			// matches theirs.
			$stagedData['payment_product'] = $payment_submethod;
		}
	}
}
