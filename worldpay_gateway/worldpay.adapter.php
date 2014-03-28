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

	public function __construct( $options = array ( ) ) {
		parent::__construct( $options );
	}

	function defineStagedVars() {
		$this->staged_vars = array(
			'returnto',
			'wp_acctname',
			'wp_storeid',
			'iso_currency_id',
			'donation_desc'
		);
	}

	function defineAccountInfo() {
		$this->accountInfo = array(
			'IsTest' => $this->account_config[ 'Test' ],
			'MerchantId' => $this->account_config[ 'MerchantId' ],
			'UserName' => $this->account_config[ 'Username' ],
			'UserPassword' => $this->account_config[ 'Password' ],

			'StoreIDs' => $this->account_config[ 'StoreIDs' ],
		);
	}

	function defineDataConstraints() {}

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
			'BZD',
			'CAD',
			'CHF',
			'EUR',
			'GBP',
			'NOK',
			'SEK',
			'VEF'
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

		$this->transactions['QueryTokenData'] = array(
			'request' => array(
				'VersionUsed',
				'TransactionType',
				'Timeout',
				'RequestType',

				'IsTest',
				'MerchantId',
				'UserName',
				'UserPassword',

				'OrderNumber',
				'OTT'
			),
			'values' => array(
				'VersionUsed' => 6,
				'TransactionType' => 'RD',  // Redirect
				'Timeout' => 60000,         // 60 seconds
				'RequestType' => 'Q'        // Query one time token data
			)
		);

		$this->transactions['AuthorizePayment'] = array(
			'request' => array(
				'VersionUsed',
				'TransactionType',
				'Timeout',
				'RequestType',
				'TRXSource',
				'MOP',
				'IsVerify',

				'IsTest',
				'MerchantId',
				'UserName',
				'UserPassword',

				'StoreID',
				'OrderNumber',
				'CustomerId',
				'CurrencyId',
				'Amount',
				'CardId',
				'REMOTE_ADDR',

				'AcctName',
				'FirstName',
				'LastName',
				'Address1',
				'City',
				'StateCode',
				'ZipCode',
				'CountryCode',
				'Email',

				'CVN'
			),
			'values' => array(
				'VersionUsed' => 6,
				'TransactionType' => 'PT',  // PaymentTrust
				'Timeout' => 60000,         // 60 seconds
				'RequestType' => 'A',       // Authorize a payment
				'TRXSource' => 4,           // Card not present (web order) transaction
				'MOP' => 'CC',              // Credit card transaction
				'IsVerify' => 1,            // Perform CVV and AVS verification
			)
		);

		$this->transactions['DepositPayment'] = array(
			'request' => array(
				'VersionUsed',
				'TransactionType',
				'Timeout',
				'RequestType',

				'IsTest',
				'MerchantId',
				'UserName',
				'UserPassword',

				'OrderNumber',
				'CurrencyId',
				'Amount',
				'PTTID',
				'NarrativeStatement1',
			),
			'values' => array(
				'VersionUsed' => 6,
				'TransactionType' => 'PT',  // PaymentTrust
				'Timeout' => 60000,         // 60 seconds
				'RequestType' => 'D'        // Deposit an authorized payment
			)
		);
	}

	function defineErrorMap() {
		$this->error_map = array(
			// Internal messages
			'internal-0000' => 'donate_interface-processing-error', // Failed failed pre-process checks.
			'internal-0001' => 'donate_interface-processing-error', // Transaction could not be processed due to an internal error.
			'internal-0002' => 'donate_interface-processing-error', // Communication failure
		);
	}

	function defineReturnValueMap() {
		// We just have a large list of return values from WP; with no real indication
		// of what operations will return which codes. So; I I conservatively mapped
		// every code.

		$this->addCodeRange( 'AuthorizePayment', 'MessageCode', 'failed', 2000, 2001 );
		$this->addCodeRange( 'AuthorizePayment', 'MessageCode', 'failed', 2051 );
		$this->addCodeRange( 'AuthorizePayment', 'MessageCode', 'failed', 2061, 2080 );
		$this->addCodeRange( 'AuthorizePayment', 'MessageCode', 'failed', 2112 );
		$this->addCodeRange( 'AuthorizePayment', 'MessageCode', 'failed', 2200, 2804 );
		$this->addCodeRange( 'AuthorizePayment', 'MessageCode', 'failed', 2831, 2804 );
		$this->addCodeRange( 'AuthorizePayment', 'MessageCode', 'failed', 2831, 2990 );
		$this->addCodeRange( 'AuthorizePayment', 'MessageCode', 'failed', 3216, 3614 );
		$this->addCodeRange( 'AuthorizePayment', 'MessageCode', 'failed', 4206, 4700 );


		$this->addCodeRange( 'DepositPayment', 'MessageCode', 'failed', 2000, 2001 );
		$this->addCodeRange( 'DepositPayment', 'MessageCode', 'pending', 2040, 2050 );
		$this->addCodeRange( 'DepositPayment', 'MessageCode', 'failed', 2051 );
		$this->addCodeRange( 'DepositPayment', 'MessageCode', 'pending', 2053, 2055 );
		$this->addCodeRange( 'DepositPayment', 'MessageCode', 'failed', 2061, 2080 );
		$this->addCodeRange( 'DepositPayment', 'MessageCode', 'failed', 2112 );
		$this->addCodeRange( 'DepositPayment', 'MessageCode', 'pending', 2122 );
		$this->addCodeRange( 'DepositPayment', 'MessageCode', 'complete', 2150, 2180 );
		$this->addCodeRange( 'DepositPayment', 'MessageCode', 'complete', 2100, 2106 );
		$this->addCodeRange( 'DepositPayment', 'MessageCode', 'failed', 2200, 2804 );
		$this->addCodeRange( 'DepositPayment', 'MessageCode', 'pending', 2830 );
		$this->addCodeRange( 'DepositPayment', 'MessageCode', 'failed', 2831, 2804 );
		$this->addCodeRange( 'DepositPayment', 'MessageCode', 'pending', 3050 );
		$this->addCodeRange( 'DepositPayment', 'MessageCode', 'failed', 3216, 3614 );
		$this->addCodeRange( 'DepositPayment', 'MessageCode', 'complete', 3100 );
		$this->addCodeRange( 'DepositPayment', 'MessageCode', 'failed', 4206, 4700 );
	}

	function defineVarMap() {
		$this->var_map = array(
			'OrderNumber'       => 'order_id',
			'CustomerId'        => 'contribution_tracking_id',
			'OTTRegion'         => 'region_code',
			'OTTResultURL'      => 'returnto',
			'OTT'               => 'wp_one_time_token',
			'CardId'            => 'wp_card_id',
			'Amount'            => 'amount',
			'FirstName'         => 'fname',
			'LastName'          => 'lname',
			'Address1'          => 'street',
			'City'              => 'city',
			'StateCode'         => 'state',
			'ZipCode'           => 'zip',
			'CountryCode'       => 'country',
			'Email'             => 'email',
			'REMOTE_ADDR'       => 'user_ip',
			'StoreID'           => 'wp_storeid',
			'CurrencyId'        => 'iso_currency_id',
			'AcctName'          => 'wp_acctname',
			'CVN'               => 'cvv',
			'PTTID'             => 'wp_pttid',
			'NarrativeStatement1' => 'donation_desc',
		);
	}

	public function do_transaction( $transaction ) {
		$this->url = $this->getGlobal( "URL" );

		switch ( $transaction ) {
			case 'GenerateToken':
				// XXX: This has no error handling yet... eep!
				$result = parent::do_transaction( $transaction );

				$this->addData( array(
					'wp_one_time_token' => $result['data']['OTT'],
					'wp_process_url' => $result['data']['OTTProcessURL'],
					'wp_rdid' => $result['data']['RDID']
				));

				// Save the OTT to the session for later
				$this->session_addDonorData();
				break;

			case 'QueryTokenData':
				// XXX: Still no error handling
				$result = parent::do_transaction( $transaction );

				$this->addData( array(
					'wp_card_id' => $result['data']['CardId'],
					'wp_card_type' => $result['data']['CreditCardType'],
				));
				break;

			case 'AuthorizePayment':
				$this->addData( array( 'cvv' => $this->get_cvv() ) );
				$this->store_cvv_in_session( null ); // Remove the CVV from the session
				$result = parent::do_transaction( $transaction );
				break;

			case 'DepositPayment':
				$result = parent::do_transaction( $transaction );
				break;
		}
	}

	function getResponseStatus( $response ) {
		$ok = null;

		foreach( $response->getElementsByTagName('MessageCode') as $node) {
			if ( $node->nodeValue ) {
				// TODO: This is a numeric code we should do something with
				$ok = true;
			}
		}
		return ( is_null( $ok ) ? false : $ok );
	}

	function getResponseErrors( $response ) {

	}

	public function processResponse( $response, &$retryVars = null ) {}

	function getResponseData( $response ) {
		$data = $this->xmlChildrenToArray( $response, 'TMSTN' );

		//have to do this here, so this data is available for the
		//AuthorizePayment post process hook.
		if ( $this->getCurrentTransaction() === 'AuthorizePayment' ) {
			$pull_vars = array (
				'CVNMatch' => 'cvv_result',
				'AddressMatch' => 'avs_address',
				'PostalCodeMatch' => 'avs_zip',
				'PTTID' => 'wp_pttid'
			);
			$addme = array ( );
			foreach ( $pull_vars as $theirs => $ours ) {
				if ( isset( $data[$theirs] ) ) {
					$addme[$ours] = $data[$theirs];
				}
			}
			$this->addData( $addme );
		}

		return $data;
	}

	protected function buildRequestXML( $rootElement = 'TMSTN' ) {
		return 'StringIn=' . str_replace( "\n", '', parent::buildRequestXML( $rootElement ) );
	}

	protected function stage_returnto( $type = 'request' ) {
		global $wgServer, $wgArticlePath;

		$this->staged_data['returnto'] = str_replace(
			'$1',
			'Special:WorldPayGateway?token=' . rawurlencode( $this->token_getSaltedSessionToken() ),
			$wgServer . $wgArticlePath
		);
	}

	protected function stage_wp_acctname( $type = 'request' ) {
		$this->staged_data['wp_acctname'] = implode( ' ', array(
			$this->getData_Unstaged_Escaped( 'fname' ),
			$this->getData_Unstaged_Escaped( 'lname' )
		));
	}

	protected function stage_wp_storeid( $type = 'request' ) {
		$currency = $this->getData_Unstaged_Escaped( 'currency_code' );
		if ( array_key_exists( $currency, $this->accountInfo['StoreIDs'] ) ) {
			$this->staged_data['wp_storeid'] = $this->accountInfo['StoreIDs'][$currency];
		} else {
			if ( $this->getCurrentTransaction() === 'AuthorizePayment' ) {
				throw new MWException( 'Store not configured for currency. Cannot perform auth request.' );
			}
		}
	}

	protected function stage_iso_currency_id( $type = 'request' ) {
		// From Appendix B of the integration manual; apparently these are ISO standard codes...
		$currency_codes = array(
			'AUD' => '36',
			'ATS' => '40',
			'BHD' => '48',
			'BEF' => '56',
			'BMD' => '60',
			'BRL' => '986',
			'CAD' => '124',
			'COP' => '170',
			'CYP' => '196',
			'CZK' => '203',
			'DKK' => '208',
			'DOP' => '214',
			'EUR' => '978',
			'FIM' => '246',
			'FRF' => '250',
			'XPF' => '953',
			'DEM' => '280',
			'GRD' => '300',
			'HKD' => '344',
			'HUF' => '348',
			'INR' => '356',
			'IDR' => '360',
			'IEP' => '372',
			'ILS' => '376',
			'ITL' => '380',
			'JMD' => '388',
			'JPY' => '392',
			'JOD' => '400',
			'KRW' => '410',
			'KWD' => '414',
			'LUF' => '442',
			'MYR' => '458',
			'MVR' => '462',
			'MTL' => '470',
			'MXN' => '484',
			'MAD' => '504',
			'NLG' => '528',
			'NZD' => '554',
			'NOK' => '578',
			'OMR' => '512',
			'PAB' => '590',
			'PHP' => '608',
			'PLN' => '985',
			'PTE' => '620',
			'QAR' => '634',
			'RUB' => '643',
			'SAR' => '682',
			'SGD' => '702',
			'ZAR' => '710',
			'ESP' => '724',
			'SEK' => '752',
			'CHF' => '756',
			'TWD' => '901',
			'THB' => '764',
			'TRL' => '792',
			'TRY' => '949',
			'AED' => '784',
			'GBP' => '826',
			'USD' => '840',
			'UZS' => '860',
			'VEB' => '862',
			'VND' => '704',
		);

		$currency = $this->getData_Unstaged_Escaped( 'currency_code' );
		if ( array_key_exists( $currency, $currency_codes ) ) {
			$this->staged_data['iso_currency_id'] = $currency_codes[$currency];
		}
	}

	protected function stage_donation_desc( $type = 'request' ) {
		// TODO: Make this translatable.
		$this->staged_data['donation_desc'] = substr( 'Donation to the Wikimedia Foundation', 0, 50 );
	}

	public function session_addDonorData() {
		parent::session_addDonorData();
		// XXX: We might end up moving this into a STOMP required field,
		// but I don't know yet so kludging it in here so we have it for later
		$_SESSION['Donor']['wp_one_time_token'] = $this->getData_Unstaged_Escaped( 'wp_one_time_token' );
	}

	public function store_cvv_in_session( $cvv ) {
		if ( !is_null( $cvv ) ) {
			$_SESSION['Donor_protected']['cvv'] = $cvv;
		} else {
			unset( $_SESSION['Donor_protected']['cvv'] );
		}
	}

	protected function get_cvv() {
		if ( isset( $_SESSION['Donor_protected']['cvv'] ) ) {
			return $_SESSION['Donor_protected']['cvv'];
		} else {
			return null;
		}
	}

	/**
	 * More should go here.
	 * @TODO: Once we get some data back from AuthorizePayment, addData here
	 * for AVS results, CVV results, and whatever else they tell us that
	 * would be helpful.
	 * @TODO: And furthermore, you will need to either pull the GC functions
	 * for AVS and CVV checking into the parent and use those, or make new
	 * ones here. I'd vastly prefer that the GC ones get reused unless
	 * there's a seriously good reason to go custom.
	 *
	 * Even more will probably need to go here once we start thinking about
	 * things like !cc payment types, and batch operations.
	 */
	protected function post_process_authorizepayment() {
		$this->runAntifraudHooks();
	}

	/**
	 * getCVVResult is intended to be used by the functions filter, to
	 * determine if we want to fail the transaction ourselves or not.
	 */
	public function getCVVResult() {
		$cvv_result = '';
		if ( is_null( $this->getData_Unstaged_Escaped( 'cvv_result' ) ) ) {
			$cvv_result = $this->getData_Unstaged_Escaped( 'cvv_result' );
		}

		$cvv_map = $this->getGlobal( 'CvvMap' );

		$result = $cvv_map[$cvv_result];
		return $result;
	}

	/**
	 * getAVSResult is intended to be used by the functions filter, to
	 * determine if we want to fail the transaction ourselves or not.
	 *
	 * In WorldPay, we get two values back that we get to synthesize
	 * together: One for address, and one for zip.
	 */
	public function getAVSResult() {
		$avs_address = '';
		$avs_zip = '';

		if ( !is_null( $this->getData_Unstaged_Escaped( 'avs_address' ) ) ) {
			$avs_address = $this->getData_Unstaged_Escaped( 'avs_address' );
		}

		if ( !is_null( $this->getData_Unstaged_Escaped( 'avs_zip' ) ) ) {
			$avs_zip = $this->getData_Unstaged_Escaped( 'avs_zip' );
		}
		//Best guess here:
		//Scale of 0 - 100, of Problem we think this result is likely to cause.

		$avs_address_map = $this->getGlobal( 'AvsAddressMap' );
		$avs_zip_map = $this->getGlobal( 'AvsZipMap' );

		$result = $avs_address_map[$avs_address];
		$result += $avs_zip_map[$avs_zip];

		return $result;
	}

}
