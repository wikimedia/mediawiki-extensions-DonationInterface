<?php

use MediaWiki\Context\RequestContext;

class Gravy3DSecure extends Frictionless3DSecure {

	/**
	 * Adds gravy-specific parameters for frictionless 3D-secure card authentication
	 *
	 * @param GatewayType $adapter
	 * @param array $normalized
	 * @param array &$stagedData
	 * @return void
	 */
	public function stage( GatewayType $adapter, $normalized, &$stagedData ) {
		if ( $normalized['payment_method'] !== 'cc' ) {
			return;
		}
		parent::stage( $adapter, $normalized, $stagedData );
		// Add user device
		$request = RequestContext::getMain()->getRequest();
		$headers = $request->getAllHeaders();
		$parser = new WhichBrowser\Parser( $headers );
		$normalizedType = $parser->getType() === 'desktop' ? 'desktop' : 'mobile';
		$stagedData['user_device'] = $normalizedType;
		$stagedData['window_origin'] = parse_url( $request->getFullRequestURL(), PHP_URL_HOST );
	}
}
