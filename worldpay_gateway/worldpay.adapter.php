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
 * WorldPayAdapter
 *
 */
class WorldPayAdapter extends GatewayAdapter {

	const GATEWAY_NAME = 'WorldPay Gateway';
	const IDENTIFIER = 'worldpay';
	const GLOBAL_PREFIX = 'wgWorldPayGateway'; //...for example.

	public $communication_type = 'xml'; //this needs to be either 'xml' or 'namevalue'
	public $redirect = FALSE;

	function getResponseStatus( $response ) {}
	function getResponseErrors( $response ) {}
	function getResponseData( $response ) {}
	public function processResponse( $response, &$retryVars = null ) {}
	function defineStagedVars() {}
	function defineTransactions() {}
	function defineErrorMap() {}
	function defineVarMap() {}
	function defineDataConstraints() {}
	function defineAccountInfo() {}
	function defineReturnValueMap() {}
	function definePaymentMethods() {}
	function defineOrderIDMeta() {
		$this->order_id_meta = array (
			'generate' => TRUE,
		);
	}

	function setGatewayDefaults() {}
	static function getCurrencies() {}

}
