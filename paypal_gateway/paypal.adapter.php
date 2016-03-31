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
 * Class PaypalAdapter
 * TODO: Document exactly which PayPal product this integrates with and link to online docs
 */
class PaypalAdapter extends GatewayAdapter {
	const GATEWAY_NAME = 'Paypal';
	const IDENTIFIER = 'paypal';
	const GLOBAL_PREFIX = 'wgPaypalGateway';

	public function getCommunicationType() {
		return 'redirect';
	}

	public function getFormClass() {
		return 'Gateway_Form_RapidHtml';
	}

	function __construct( $options = array() ) {
		parent::__construct( $options );

		if ( $this->getData_Unstaged_Escaped( 'payment_method' ) == null ) {
			$this->addRequestData(
				array( 'payment_method' => 'paypal' )
			);
		}
	}

	function defineStagedVars() {
		$this->staged_vars = array(
			'recurring_length',
			'locale',
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
			'lc' => 'locale',
		);
	}

	function defineAccountInfo() {
		$this->accountInfo = array();
	}
	function defineReturnValueMap() {
	}
	function processResponse( $response ) {
		$this->transaction_response->setCommunicationStatus( true );
	}
	function defineDataConstraints() {
	}
	function defineOrderIDMeta() {
		$this->order_id_meta = array(
			'generate' => false,
		);
	}
	function setGatewayDefaults() {}

	public function defineErrorMap() {

		$this->error_map = array(
			// Internal messages
			// Failed failed pre-process checks.
			'internal-0000' => 'donate_interface-processing-error',
			// Transaction could not be processed due to an internal error.
			'internal-0001' => 'donate_interface-processing-error',
			// Communication failure
			'internal-0002' => 'donate_interface-processing-error',
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
				'lc',
			),
			'values' => array(
				'business' => $this->account_config[ 'AccountEmail' ],
				'cancel_return' => ResultPages::getCancelPage( $this ),
				'cmd' => '_donations',
				'item_number' => 'DONATE',
				'item_name' => WmfFramework::formatMessage( 'donate_interface-donation-description' ),
				'no_note' => 0,
				'return' => ResultPages::getThankYouPage( $this ),
			),
		);
		$this->transactions[ 'DonateXclick' ] = array(
			'request' => array(
				'cmd',
				'item_number',
				'item_name',
				'cancel_return',
				'no_note',
				'return',
				'business',
				'no_shipping',
				// 'lc', // Causes issues when lc=CN for some reason; filed bug report
				'amount',
				'currency_code',
				'country',
				'custom'
			),
			'values' => array(
				'item_number' => 'DONATE',
				'item_name' => WmfFramework::formatMessage( 'donate_interface-donation-description' ),
				'cancel_return' => ResultPages::getCancelPage( $this ),
				'no_note' => '1',
				'return' => ResultPages::getThankYouPage( $this ),
				'business' => $this->account_config[ 'AccountEmail' ],
				'cmd' => '_xclick',
				'no_shipping' => '1'
			),
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
				'lc',
			),
			'values' => array(
				'business' => $this->account_config[ 'AccountEmail' ],
				'cancel_return' => ResultPages::getCancelPage( $this ),
				'cmd' => '_xclick-subscriptions',
				'item_number' => 'DONATE',
				'item_name' => WmfFramework::formatMessage( 'donate_interface-donation-description' ),
				'no_note' => 0,
				'return' => ResultPages::getThankYouPage( $this ),
				// recurring fields
				't3' => 'M',
				'p3' => '1',
				'src' => '1',
				'srt' => $this->getGlobal( 'RecurringLength' ), // number of installments
			),
		);
	}

	public function defineDataTransformers() {
		$this->data_transformers = array_merge( parent::getCoreDataTransformers(), array(
			new CleanupRecurringLength(),
			new PayPalLocale(),
		) );
	}

	function getBasedir() {
		return __DIR__;
	}

	public function doPayment() {
		if ( $this->getData_Unstaged_Escaped( 'recurring' ) ) {
			$resultData = $this->do_transaction( 'DonateRecurring' );
		} else {
			$country = $this->getData_Unstaged_Escaped( 'country' );
			if ( in_array( $country, $this->getGlobal( 'XclickCountries' ) ) ) {
				$resultData = $this->do_transaction( 'DonateXclick' );
			} else {
				$resultData = $this->do_transaction( 'Donate' );
			}
		}

		return PaymentResult::fromResults(
			$resultData,
			$this->getFinalStatus()
		);
	}

	function do_transaction( $transaction ) {
		$this->session_addDonorData();
		$this->setCurrentTransaction( $transaction );

		switch ( $transaction ) {
			case 'Donate':
			case 'DonateXclick':
			case 'DonateRecurring':
				$this->transactions[ $transaction ][ 'url' ] =
					$this->getGlobal( 'URL' ) . '?' . http_build_query( $this->buildRequestParams() );
				$result = parent::do_transaction( $transaction );
				$this->finalizeInternalStatus( FinalStatus::COMPLETE );
				return $result;
		}
	}
}
