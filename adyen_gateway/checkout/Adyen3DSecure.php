<?php

class Adyen3DSecure extends Abstract3DSecure {

	/**
	 * The Checkout API will apply 3DSecure rules when browser_info and
	 * a return_url are sent. This staging helper sets all the browser_info
	 * subkeys we need. These are then added to the transaction request
	 * structure in AdyenCheckoutAdapter::tuneFor3DSecure
	 *
	 * @param GatewayType $adapter
	 * @param array $normalized Donation data in normalized form.
	 * @param array &$stagedData Reference to output data.
	 */
	public function stage( GatewayType $adapter, $normalized, &$stagedData ) {
		if ( !$this->canSet3dSecure( $normalized ) ) {
			return;
		}
		if ( !$this->isRecommend3dSecure( $adapter, $normalized ) ) {
			return;
		}
		$request = RequestContext::getMain()->getRequest();
		$stagedData['user_agent'] = $request->getHeader( 'User-Agent' );
		$stagedData['accept_header'] = $request->getHeader( 'Accept' );
		// TODO: check what Adyen does when locale is a combination they don't have
		$stagedData['locale'] = $normalized['language'] . '-' . $normalized['country'];
		// FIXME: need to bring the rest of these in from the API request
		// currently just stuffing 'em with plausible placeholders
		$stagedData['color_depth'] = 24;
		$stagedData['screen_height'] = 723;
		$stagedData['screen_width'] = 1536;
		$stagedData['time_zone_offset'] = 0;
		$stagedData['java_enabled'] = true;
	}
}
