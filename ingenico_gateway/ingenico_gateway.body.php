<?php
/**
 * Wikimedia Foundation
 *
 * LICENSE
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 */

/**
 * IngenicoGateway
 */
class IngenicoGateway extends GatewayPage {

	/** @inheritDoc */
	protected $gatewayIdentifier = IngenicoAdapter::IDENTIFIER;

	public function execute( $par ) {
		global $wgAdyenCheckoutGatewayEnabled;
		if ( !$wgAdyenCheckoutGatewayEnabled ) {
			parent::execute( $par );
			return;
		}
		$this->logger = DonationLoggerFactory::getLoggerForType(
			IngenicoAdapter::class,
			$this->getLogPrefix()
		);
		$referrer = $this->getRequest()->getHeader( 'referer' );
		$params = $this->getRequest()->getQueryValues();
		$paramJson = json_encode( $params );
		$this->logger->warning(
			"Donors sent to the Ingenico form from referrer $referrer with params $paramJson.  " .
			'Redirecting to Adyen.'
		);
		unset( $params['title'] );
		if ( !empty( $params['gateway'] ) ) {
			$params['gateway'] = 'adyen';
		}
		$adyenTitle = Title::newFromText( 'Special:AdyenCheckoutGateway' );
		$this->getOutput()->redirect(
			$adyenTitle->getFullURL( $params, false, PROTO_CURRENT )
		);
	}
}
