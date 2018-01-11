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
use SmashPig\CrmLink\FinalStatus;

/**
 * TODO: Document exactly which PayPal product this integrates with and link to online docs
 */
class PaypalLegacyAdapter extends GatewayAdapter {
	const GATEWAY_NAME = 'PayPal Legacy';
	const IDENTIFIER = 'paypal';
	// TODO: rename or deprecate
	const GLOBAL_PREFIX = 'wgPaypalGateway';

	public function getCommunicationType() {
		return 'redirect';
	}

	function __construct( $options = array() ) {
		parent::__construct( $options );

		if ( $this->getData_Unstaged_Escaped( 'payment_method' ) == null ) {
			$this->addRequestData(
				array( 'payment_method' => 'paypal' )
			);
		}
	}

	function defineAccountInfo() {
		$this->accountInfo = array();
	}
	function defineReturnValueMap() {
	}

	function defineOrderIDMeta() {
		$this->order_id_meta = array(
			'generate' => false,
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

	function getBasedir() {
		return __DIR__;
	}

	public function doPayment() {
		$ctid = $this->getData_Unstaged_Escaped( 'contribution_tracking_id' );
		$this->normalizeOrderID( $ctid );
		$this->addRequestData( array( 'order_id' => $ctid ) );

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
				$result = parent::do_transaction( $transaction );

				$this->finalizeInternalStatus( FinalStatus::COMPLETE );

				return $result;
		}
	}
}
