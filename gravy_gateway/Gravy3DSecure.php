<?php

use MediaWiki\Context\RequestContext;
use MediaWiki\MediaWikiServices;

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
		$headers = RequestContext::getMain()->getRequest()->getAllHeaders();
		$parser = new WhichBrowser\Parser( $headers );
		$normalizedType = $parser->getType() === 'desktop' ? 'desktop' : 'mobile';
		$stagedData['user_device'] = $normalizedType;
		$stagedData['window_origin'] = MediaWikiServices::getInstance()->getUrlUtils()->getServer( PROTO_HTTPS );
	}
}
