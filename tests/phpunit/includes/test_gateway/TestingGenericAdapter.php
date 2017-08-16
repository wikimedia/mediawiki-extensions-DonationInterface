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
	use TTestingAdapter;

	/**
	 * A list of fake errors that is returned each time revalidate() is called.
	 */
	public $errorsForRevalidate = array();

	public $revalidateCount = 0;

	public static $acceptedCurrencies = array();

	public function getCommunicationType() {
		return 'xml';
	}

	public function revalidate( $check_not_empty = array() ) {
		if ( !empty( $this->errorsForRevalidate ) ) {
			$fakeErrors = $this->errorsForRevalidate[$this->revalidateCount];
			if ( $fakeErrors !== null ) {
				$this->revalidateCount++;
				$this->setValidationErrors( $fakeErrors );
				return empty( $fakeErrors );
			}
		}
		return parent::revalidate( $check_not_empty );
	}

	public function normalizeOrderID( $override = null, $dataObj = null ) {
		return '12345';
	}

	public function loadConfig( $variant = null ) {
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

	public function defineTransactions() {
	}

	public function defineDataTransformers() {
		$this->data_transformers = parent::getCoreDataTransformers();
	}

	public function defineVarMap() {
	}

	public function processResponse( $response ) {
	}

	public function setGatewayDefaults( $options = array() ) {
	}

	public function getCurrencies( $options = array() ) {
		return self::$acceptedCurrencies;
	}

	public function doPayment() {
	}

	protected function getBasedir() {
		return __DIR__;
	}
}
