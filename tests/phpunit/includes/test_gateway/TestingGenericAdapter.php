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

	/** @var string[] */
	public static $acceptedCurrencies = [];

	/** @var array */
	public static $donationRules;

	/** @inheritDoc */
	public function normalizeOrderID( $override = null, $dataObj = null ) {
		return '12345';
	}

	/** @inheritDoc */
	public function loadConfig( $variant = null ) {
	}

	public function defineAccountInfo() {
	}

	protected function defineDataConstraints() {
	}

	protected function defineErrorMap() {
	}

	public function defineOrderIDMeta() {
	}

	public function definePaymentMethods() {
	}

	public function defineTransactions() {
	}

	public function defineDataTransformers() {
		$this->data_transformers = [
			// Always stage email address first, to set default if missing
			new DonorEmail(),
			new DonorFullName(),
			new CountryValidation(),
			new Amount(),
			new AmountInCents(),
			new StreetAddress(),
		];
	}

	protected function defineVarMap() {
	}

	/** @inheritDoc */
	protected function setGatewayDefaults( $options = [] ) {
	}

	/** @inheritDoc */
	public function getCurrencies( $options = [] ) {
		return self::$acceptedCurrencies;
	}

	public function doPayment() {
	}

	public function getDonationRules(): array {
		if ( isset( self::$donationRules ) ) {
			return self::$donationRules;
		}
		return parent::getDonationRules();
	}
}
