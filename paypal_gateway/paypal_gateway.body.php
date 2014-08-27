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

class PaypalGateway extends GatewayPage {

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
	protected function handleRequest() {
		$this->getOutput()->allowClickjacking();

		$this->setHeaders();

		if ( $this->validateForm() ) {
			$this->displayForm();
			return;
		}

		// We also switch on the form name--if we're redirecting without stopping
		// for user interaction, the form name is our only clue that this is recurring.
		if ( $this->getRequest()->getText( 'ffname', 'default' ) === 'paypal-recurring'
			or $this->getRequest()->getText( 'recurring', 0 )
		) {
			$result = $this->adapter->do_transaction( 'DonateRecurring' );
		} else {
			$country = $this->adapter->getData_Unstaged_Escaped( 'country' );
			if ( array_search( $country, $this->adapter->getGlobal( 'XclickCountries' ) ) !== false ) {
				$result = $this->adapter->do_transaction( 'DonateXclick' );
			} else {
				$result = $this->adapter->do_transaction( 'Donate' );
			}
		}

		if ( !empty( $result['redirect'] ) ) {
			$this->getOutput()->redirect( $result['redirect'] );
		}

		$this->displayForm();
	}
}
