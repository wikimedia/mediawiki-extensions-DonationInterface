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

class PaypalGateway extends GatewayForm {

	/**
	 * Constructor - set up the new special page
	 */
	public function __construct() {
		$this->adapter = new PaypalAdapter();
		parent::__construct(); //the next layer up will know who we are.
	}

	/**
	 * Show the special page
	 */
	public function execute( $param ) {
		$this->getOutput()->allowClickjacking();

		$this->setHeaders();

		if ( $this->validateForm() ) {
			$form_errors = $this->adapter->getValidationErrors();
			if ( array_key_exists( 'currency_code', $form_errors ) ) {
				// If the currency is invalid, fallback to USD
				$oldCurrency = $this->getRequest()->getText( 'currency_code' );
				$approxConverted = 0;
				require_once( __DIR__ . '/../gateway_common/currencyRates.inc' );
				$conversionRates = getCurrencyRates();
				if ( array_key_exists( $oldCurrency, $conversionRates ) ) {
					$approxConverted = floor( $this->getRequest()->getText( 'amount' ) / $conversionRates[$oldCurrency] );
				}

				$this->adapter->addData( array(
					'amount' => $approxConverted,
					'currency_code' => 'USD',
				) );

				// Notify user that this has happened
				$this->adapter->addManualError( array(
					'general' => $this->msg( 'donate_interface-fallback-currency-notice', 'USD' )->text(),
				) );

				$this->adapter->log( $this->adapter->getLogMessagePrefix() . "Unsupported currency forced to USD, user notified of action." );
			}
		} else {
			if ( $this->getRequest()->getText( 'recurring', 0 ) ) {
				$result = $this->adapter->do_transaction( 'DonateRecurring' );
			} else {
				$result = $this->adapter->do_transaction( 'Donate' );
			}

			if ( !empty( $result['redirect'] ) ) {
				$this->getOutput()->redirect( $result['redirect'] );
			}
		}

		$this->displayForm();
	}
}
