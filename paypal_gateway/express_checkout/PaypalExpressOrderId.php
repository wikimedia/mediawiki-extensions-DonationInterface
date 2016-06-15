<?php

class PaypalExpressOrderId implements StagingHelper {
	public function stage( GatewayType $adapter, $normalized, &$staged ) {
		// Add "Order ID Paypal Express Checkout" namespace so the listener
		// can distinguish from legacy.
		$prefix = 'OID-PPEC-';
		$staged['order_id'] = $prefix . $normalized['order_id'];
	}
}
