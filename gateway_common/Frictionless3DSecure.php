<?php

class Frictionless3DSecure extends Abstract3DSecure {

	/**
	 * The Checkout API will apply 3DSecure rules when browser_info and
	 * a return_url are sent. This staging helper sets some browser_info
	 * subkeys from the request headers. Others are added to the donate api
	 * call in adyen.js. The browser_info array is added to the transaction
	 * request structure in AdyenCheckoutAdapter::tuneForPaymentMethod.
	 *
	 * @param GatewayType $adapter
	 * @param array $normalized Donation data in normalized form.
	 * @param array &$stagedData Reference to output data.
	 */
	public function stage( GatewayType $adapter, $normalized, &$stagedData ) {
		if ( $normalized['payment_method'] !== 'cc' ) {
			return;
		}
		$request = RequestContext::getMain()->getRequest();
		$stagedData['user_agent'] = $request->getHeader( 'User-Agent' );
		$stagedData['accept_header'] = $request->getHeader( 'Accept' );
		if ( str_contains( $normalized['language'], '-' ) ) {
			$stagedData['locale'] = $normalized['language'];
		} else {
			$stagedData['locale'] = $normalized['language'] . '-' . $normalized['country'];
		}
	}
}
