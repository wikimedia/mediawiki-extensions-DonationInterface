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
	const GLOBAL_PREFIX = 'wgWorldPayGateway';

	public $communication_type = 'xml';
	public $redirect = FALSE;

	public function __construct() {
		parent::__construct();
	}

	function getResponseStatus( $response ) {}

	function getResponseErrors( $response ) {
		// XXX Fill this out; likely looking at the StrId and MessageCode
	}

	function getResponseData( $response ) {
		$data = array( );

		$transaction = $this->getCurrentTransaction();

		switch ( $transaction ) {
			case 'GenerateToken':
				$data = $this->xmlChildrenToArray( $response, 'TMSTN' );
				$this->addData( array(
					'wp_one_time_token' => $data['OTT'],
					'wp_process_url' => $data['OTTProcessURL'],
					'wp_rdid' => $data['RDID']
				));
		}

		return $data;
	}


	public function processResponse( $response, &$retryVars = null ) {}

	function defineStagedVars() {
		$this->staged_vars = array(
			'returnto',
		);
	}

	function defineTransactions() {
		$this->transactions = array();

		$this->transactions['GenerateToken'] = array(
			'request' => array(
				'VersionUsed',
				'TransactionType',
				'Timeout',
				'RequestType',
				'Action',

				'IsTest',
				'MerchantId',
				'UserName',
				'UserPassword',

				'OrderNumber',
				'CustomerId',
				'OTTRegion',
				'OTTResultURL',
			),
			'values' => array(
				'VersionUsed' => 6,
				'TransactionType' => 'RD',  // Redirect
				'Timeout' => 60000,         // 60 seconds
				'RequestType' => 'G',       // Generate 1 time token
				'Action' => 'A',            // Add a card to OTT
			),
		);
	}

	function defineErrorMap() {}

	function defineVarMap() {
		$this->var_map = array(
			'OrderNumber'       => 'order_id',
			'CustomerId'        => 'contribution_tracking_id',
			'OTTRegion'         => 'region_code',
			'OTTResultURL'      => 'returnto',
		);
	}

	function defineAccountInfo() {
		$this->accountInfo = array(
			'IsTest' => $this->account_config[ 'Test' ],
			'MerchantId' => $this->account_config[ 'MerchantId' ],
			'UserName' => $this->account_config[ 'Username' ],
			'UserPassword' => $this->account_config[ 'Password' ],
		);
	}

	function defineDataConstraints() {}
	function defineReturnValueMap() {}

	function definePaymentMethods() {
		$this->payment_methods = array();
		$this->payment_submethods = array();

		$this->payment_methods['cc'] = array(
			'label'	=> 'Credit Cards',
		);

		$this->payment_submethods[''] = array(
			'paymentproductid'	=> 0,
			'label'	=> 'Any',
			'group'	=> 'cc',
			'validation' => array( 'address' => true, 'amount' => true, 'email' => true, 'name' => true, ),
			'keys' => array(),
		);
	}

	function defineOrderIDMeta() {
		$this->order_id_meta = array (
			'generate' => TRUE,
		);
	}

	function setGatewayDefaults() {
		$this->addData( array(
			'region_code'  => 0  // TODO: geolocating this into the right region...
		));
	}


	static function getCurrencies() {
		return array(
			'USD',
		);
	}

	public function do_transaction( $transaction ) {
		$this->url = $this->getGlobal( "URL" );

		switch ( $transaction ) {
			case 'GenerateToken':
				// XXX: This has no error handling yet... eep!
				$result = parent::do_transaction( $transaction );
				// XXX: Might want to do the this->addData call here instead of in the parse response function
				break;

			case 'QueryTokenData':
				break;
		}
	}

	protected function buildRequestXML( $rootElement = 'TMSTN' ) {
		return 'StringIn=' . str_replace( "\n", '', parent::buildRequestXML( $rootElement ) );
	}

	protected function stage_returnto( $type = 'request' ) {
		global $wgServer, $wgArticlePath;

		$this->staged_data['returnto'] = str_replace(
			'$1',
			'Special:WorldPayGateway?token=' . $this->token_getSaltedSessionToken(),
			$wgServer . $wgArticlePath
		);
	}
}
