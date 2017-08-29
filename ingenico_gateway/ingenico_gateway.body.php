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
 *
 */
class IngenicoGateway extends GlobalCollectGateway {

	protected $gatewayIdentifier = IngenicoAdapter::IDENTIFIER;

	protected function handleRequest() {
		$this->getOutput()->allowClickjacking();
		// TODO: remove conditional when we have a dedicated error render
		// page and move addModule to Mustache#getResources
		if( $this->adapter->getFormClass() === 'Gateway_Form_Mustache' ) {
			$this->getOutput()->addModules( 'ext.donationinterface.ingenico-hosted' );
		}
		$this->handleDonationRequest();
	}
}
