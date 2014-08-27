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
 * WorldPayGateway
 *
 */
class WorldPayGateway extends GatewayPage {

	/**
	 * Constructor - set up the new special page
	 */
	public function __construct() {
		$this->adapter = new WorldPayAdapter();
		parent::__construct();
	}

	/**
	 * Show the special page
	 *
	 * @todo
	 * - Finish error handling
	 */
	protected function handleRequest() {
		$this->getOutput()->addModules( 'ext.donationinterface.worldpay.styles' ); //loads early
		$this->getOutput()->addModules( 'ext.donationinterface.worldpay.code' ); //loads at normal time
		$this->setHeaders();

		// dispatch forms/handling
		if ( $this->adapter->checkTokens() ) {
			$ott = $this->getRequest()->getText( 'OTT' );
			if ( $ott ) {
				$this->adapter->do_transaction( 'QueryAuthorizeDeposit' );
				if ( $this->adapter->getFinalStatus() === 'failed' ) {
					$this->getOutput()->redirect( $this->adapter->getFailPage() );
				} else {
					$this->getOutput()->redirect( $this->adapter->getThankYouPage() );
				}

			} else {
				// Show either the initial form or an error form
				$this->adapter->session_addDonorData();
				$this->displayForm();
			}
		} else { //token mismatch
			$error['general']['token-mismatch'] = $this->msg( 'donate_interface-token-mismatch' )->text();
			$this->adapter->addManualError( $error );
			$this->displayForm();
		}
	}
}
