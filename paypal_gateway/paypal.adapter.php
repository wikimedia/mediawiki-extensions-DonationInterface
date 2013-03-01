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

class PaypalAdapter extends GatewayAdapter {
	const GATEWAY_NAME = 'Paypal';
	const IDENTIFIER = 'paypal';
	const COMMUNICATION_TYPE = 'namevalue';
	const GLOBAL_PREFIX = 'wgPaypalGateway';

	function __construct( $options = array() ) {
		parent::__construct( $options );

		if ($this->getData_Unstaged_Escaped( 'payment_method' ) == null ) {
			$this->addData(
				array( 'payment_method' => 'paypal' )
			);
		}
	}

	function defineStagedVars() {
		$this->staged_vars = array(
			'recurring_length',
		);
	}

	function defineVarMap() {
		$this->var_map = array(
			'amount' => 'amount',
			'country' => 'country',
			'currency_code' => 'currency_code',
			'item_name' => 'description',
			'return' => 'return',
			'custom' => 'contribution_tracking_id',
			'a3' => 'amount',
			'srt' => 'recurring_length',
		);
	}

	function defineAccountInfo() {
		$this->accountInfo = array();
	}
	function defineReturnValueMap() {}
	function getResponseStatus( $response ) {}
	function getResponseErrors( $response ) {}
	function getResponseData( $response ) {}
	function processResponse( $response, &$retryVars = null ) {}
	function defineDataConstraints() {}

	public function defineErrorMap() {

		$this->error_map = array(
			// Internal messages
			'internal-0000' => 'donate_interface-processing-error', // Failed failed pre-process checks.
			'internal-0001' => 'donate_interface-processing-error', // Transaction could not be processed due to an internal error.
			'internal-0002' => 'donate_interface-processing-error', // Communication failure
		);
	}

	function defineTransactions() {
		$this->transactions = array();
		$this->transactions[ 'Donate' ] = array(
			'request' => array(
				'amount',
				'currency_code',
				'country',
				'business',
				'cancel_return',
				'cmd',
				'item_name',
				'item_number',
				'no_note',
				'return',
				'custom',
			),
			'values' => array(
				'business' => $this->account_config[ 'AccountEmail' ],
				'cancel_return' => $this->getGlobal( 'ReturnURL' ),
				'cmd' => '_donations',
				'item_number' => 'DONATE',
				'item_name' => wfMsg( 'donate_interface-donation-description' ),
				'no_note' => 0,
				'return' => $this->getGlobal( 'ReturnURL' ),
			),
			'communication_type' => 'redirect',
		);
		$this->transactions[ 'DonateRecurring' ] = array(
			'request' => array(
				'a3',
				'currency_code',
				'country',
				'business',
				'cancel_return',
				'cmd',
				'item_name',
				'item_number',
				'no_note',
				'return',
				'custom',
				't3',
				'p3',
				'src',
				'srt',
			),
			'values' => array(
				'business' => $this->account_config[ 'AccountEmail' ],
				'cancel_return' => $this->getGlobal( 'ReturnURL' ),
				'cmd' => '_xclick-subscriptions',
				'item_number' => 'DONATE',
				'item_name' => wfMsg( 'donate_interface-donation-description' ),
				'no_note' => 0,
				'return' => $this->getGlobal( 'ReturnURL' ),
				// recurring fields
				't3' => 'M',
				'p3' => '1',
				'src' => '1',
				'srt' => $this->getGlobal( 'RecurringLength' ), // number of installments
			),
			'communication_type' => 'redirect',
		);
	}

	function do_transaction( $transaction ) {
		$this->setCurrentTransaction( $transaction );

		switch ( $transaction ) {
			case 'Donate':
			case 'DonateRecurring':
				$this->transactions[ $transaction ][ 'url' ] = $this->getGlobal( 'URL' ) . '?' . http_build_query( $this->buildRequestParams() );
				return parent::do_transaction( $transaction );
		}
	}

	static function getCurrencies() {
		// see https://www.x.com/developers/paypal/documentation-tools/api/currency-codes
		return array(
			'AUD',
			'BRL', // in-country only
			'CAD',
			'CZK',
			'DKK',
			'EUR',
			'HKD',
			'HUF',
			'ILS',
			'JPY', // no fractions
			'MYR',
			'MXN',
			'NOK',
			'NZD',
			'PHP',
			'PLN',
			'GBP',
			'SGD',
			'SEK',
			'CHF',
			'TWD', // no fractions
			'THB',
			'TRY', // in-country only
			'USD',
		);
	}

	protected function stage_recurring_length( $mode = 'request' ) {
		if ( array_key_exists( 'recurring_length', $this->staged_data ) && !$this->staged_data['recurring_length'] ) {
			unset( $this->staged_data['recurring_length'] );
		}
	}

	public function validatedOK() {
		$result = parent::validatedOK();

		if ( !$result ) {
			$validation_errors = $this->getValidationErrors();
			if ( array_keys( $validation_errors ) === array( 'amount' )
					and (!$this->staged_data['amount'] or (float)$this->staged_data['amount'] == 0 ) ) {
				// ignore empty amount error
				$result = true;
			}
		}

		return $result;
	}
}
