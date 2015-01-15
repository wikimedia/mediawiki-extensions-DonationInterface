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
 * A really dumb adapter.
 */
class TestingGenericAdapter extends GatewayAdapter {

	/**
	 * A list of fake errors that is returned each time revalidate() is called.
	 */
	public $errorsForRevalidate = array();

	public $revalidateCount = 0;
	public static $fakeGlobals = array();

	public static $acceptedCurrencies = array();

	public function revalidate($check_not_empty = array()) {
		if ( $this->errorsForRevalidate ) {
			$fakeErrors = $this->errorsForRevalidate[$this->revalidateCount];
			if ( $fakeErrors !== null ) {
				$this->revalidateCount++;
				$this->setValidationErrors( $fakeErrors );
				return empty( $fakeErrors );
			}
		}
		return parent::revalidate($check_not_empty);
	}

	public function normalizeOrderID( $override = null, $dataObj = null ) {
		return '12345';
	}

	public static function getGlobal( $name ) {
		if ( array_key_exists( $name, TestingGenericAdapter::$fakeGlobals ) ) {
			return TestingGenericAdapter::$fakeGlobals[$name];
		}
		return parent::getGlobal( $name );
	}

	public function defineAccountInfo() {
	}

	public function defineDataConstraints() {
	}

	public function defineErrorMap() {
	}

	public function defineOrderIDMeta() {
	}

	public function definePaymentMethods() {
	}

	public function defineReturnValueMap() {
	}

	public function defineStagedVars() {
	}

	public function defineTransactions() {
	}

	public function defineVarMap() {
	}

	public function getResponseData($response) {
	}

	public function getResponseErrors($response) {
	}

	public function getResponseStatus($response) {
	}

	public function processResponse($response, &$retryVars = null) {
	}

	public function setGatewayDefaults() {
	}

	public static function getCurrencies() {
		return TestingGenericAdapter::$acceptedCurrencies;
	}

}
