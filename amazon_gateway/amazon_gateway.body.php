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

class AmazonGateway extends GatewayPage {

	protected $gatewayIdentifier = AmazonAdapter::IDENTIFIER;

	/**
	 * MakeGlobalVariablesScript handler, sends settings to Javascript
	 * @param array &$vars
	 */
	public function setClientVariables( &$vars ) {
		parent::setClientVariables( $vars );
		$vars['wgAmazonGatewayClientID'] = $this->adapter->getAccountConfig( 'ClientID' );
		$vars['wgAmazonGatewaySellerID'] = $this->adapter->getAccountConfig( 'SellerID' );
		$vars['wgAmazonGatewaySandbox'] = $this->adapter->getGlobal( 'Test' ) ? true : false;
		$vars['wgAmazonGatewayReturnURL'] = $this->adapter->getAccountConfig( 'ReturnURL' );
		$vars['wgAmazonGatewayWidgetScript'] = $this->adapter->getAccountConfig( 'WidgetScriptURL' );
		$vars['wgAmazonGatewayLoginScript'] = $this->adapter->getGlobal( 'LoginScript' );
		$failPage = ResultPages::getFailPage( $this->adapter );
		if ( !filter_var( $failPage, FILTER_VALIDATE_URL ) ) {
			// It's a rapidfail form, but we need an actual URL:
			$failPage = GatewayFormChooser::buildPaymentsFormURL( $failPage, [ 'gateway' => 'amazon' ] );
		}
		$vars['wgAmazonGatewayFailPage'] = $failPage;
		$vars['wgAmazonGatewayOtherWaysURL'] = $this->adapter->localizeGlobal( 'OtherWaysURL' );
	}

	/**
	 * Overrides parent function to return false.
	 *
	 * @return bool
	 *
	 * @see GatewayPage::showSubmethodButtons()
	 */
	public function showSubmethodButtons() {
		return false;
	}

	/**
	 * Overrides parent function to return false.
	 *
	 * @return bool
	 *
	 * @see GatewayPage::showContinueButton()
	 */
	public function showContinueButton() {
		return false;
	}
}
