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

	/**
	 * @var string[] Card types (as returned by WP) mapped to what we call them
	 */
	static $cardTypes = array(
		'VI' => 'visa',
		'AX' => 'amex',
		'BE' => 'visa-beneficial',
		'CB' => 'cb',
		'DC' => 'diners',
		'DI' => 'discover',
		'JC' => 'jcb',
		'MC' => 'mc',
		'SW' => 'solo',
		'VE' => 'visa-electron',
		'VD' => 'visa-debit',
		'MA' => 'maestro',
		'MD' => 'mc-debit',
		'XX' => '',
	);

	public function __construct( $options = array ( ) ) {
		parent::__construct( $options );
	}

	function defineStagedVars() {
		$this->staged_vars = array(
			'returnto',
			'wp_acctname',
			'wp_storeid',
			'iso_currency_id',
			'donation_desc',
			'payment_submethod',
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

	function defineDataConstraints() {
		$this->dataConstraints = array(
			// AcctName
			'wp_acctname' => array( 'type' => 'alphanumeric', 'length' => 30 ),

			// Address1
			'street' => array( 'type' => 'alphanumeric', 'length' => 60 ),

			// Amount
			'amount' => array( 'type' => 'numeric' ),

			// CardId
			'wp_card_id' => array( 'type' => 'numeric' ),

			// City
			'city' => array( 'type' => 'alphanumeric', 'length' => 60 ),

			// CountryCode
			'country' => array( 'type' => 'alphanumeric', 'length' => 2 ),

			// CurrencyId
			'iso_currency_id' => array( 'type' => 'numeric' ),

			// CVN
			'cvv' => array( 'type' => 'numeric' ),

			// Email
			'email' => array( 'type' => 'alphanumeric', 'length' => 50 ),

			// FirstName
			'fname' => array( 'type' => 'alphanumeric', 'length' => 60 ),

			// LastName
			'lname' => array( 'type' => 'alphanumeric', 'length' => 60 ),

			// NarrativeStatement1
			'donation_desc' => array( 'type' => 'alphanumeric', 'length' => 50 ),

			// OrderNumber
			'order_id' => array( 'type' => 'alphanumeric', 'length' => 35 ),

			// OTTRegion
			'region_code' => array( 'type' => 'numeric' ),

			// OTTResultURL
			'returnto' => array( 'type' => 'alphanumeric', 'length' => 255 ),

			// PTTID
			'wp_pttid' => array( 'type' => 'numeric' ),

			// REMOTE_ADDR
			'user_ip' => array( 'type' => 'alphanumeric', 'length' => 100 ),

			// StateCode
			'state' => array( 'type' => 'alphanumeric', 'length' => 60 ),

			// ZipCode
			'zip' => array( 'type' => 'alphanumeric', 'length' => 30 ),
		);
	}

	function definePaymentMethods() {
		$this->payment_methods = array();
		$this->payment_submethods = array();

		$this->payment_methods['cc'] = array(
			'label'	=> 'Credit Cards',
		);

		$this->payment_submethods = array();
		foreach( self::$cardTypes as $wpName => $ourName ) {
			$this->payment_submethods[$ourName] = array(
				'group'	=> 'cc',
				'validation' => array( 'address' => true, 'amount' => true, 'email' => true, 'name' => true, ),
			);
		}

		PaymentMethod::registerMethods( $this->payment_methods );
		PaymentMethod::registerMethods( $this->payment_submethods );
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

		// NOTE: This authorization step is ONLY for fraud, when you set IsVerify=1
		// the transaction is immediately canceled on WPs end but we get back
		// AVS and CVV check details. If fraud checks pass we will simultaneously
		// authorize and deposit the payment using the 'Sale' aka AuthorizeAndDepositPayment
		// transaction.
		$this->transactions['AuthorizePaymentForFraud'] = array(
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
				'IsVerify' => 1,            // Perform CVV and AVS verification for account (deposit not allowed)
			)
		);

		// NOTE: This transaction type is actually a 'Sale' transaction but that
		// sounded hella confusing. It is a compound authorize / deposit API call
		// TODO: This can also support creating recurring payments!
		$this->transactions['AuthorizeAndDepositPayment'] = array(
			'request' => array(
				'VersionUsed',
				'TransactionType',
				'Timeout',
				'RequestType',
				'TRXSource',
				'MOP',

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

				'NarrativeStatement1',
			),
			'values' => array(
				'VersionUsed' => 6,
				'TransactionType' => 'PT',  // PaymentTrust
				'Timeout' => 60000,         // 60 seconds
				'RequestType' => 'S',       // Sale request - authorize and deposit payment
				'MOP' => 'CC',              // Credit card transaction
				'TRXSource' => 4,           // Card not present (web order) transaction
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

		/* From the integration manual; this is the list of all possible return codes
		 *  2000	Received no answer from banking network. Resend transaction.
		 *  2001	No need to do this transaction.
		 *  2040	Request submitted and waiting for Finalization.
		 *  2050	Request submitted and waiting for processing to be completed next cycle.
		 *  2051	Cannot find the BTID for the original request.
		 *  2053	Notification Received.
		 *  2055	The request in a pending state at the payment provider.
		 *  2061	Validation/Verification Failure.
		 *  2062	Identification supplied is invalid.
		 *  2080	Voided.
		 *  2100	Transaction Authorized/Approved.
		 *  2101	Validated.
		 *  2102	Verified.
		 *  2103	Prenoted.
		 *  2104	Transaction was approved - Base 24.
		 *  2105	Notification Cleared.
		 *  2106	Certegy warrants the item presented.
		 *  2112	The request is duplication of original transaction, it is voided.
		 *  2122	Cheque verified, not ACH-able, Auth Only.
		 *  2135	ACH bank Balance info obtained.
		 *  2150	Deposit request previously submitted has been processed successfully.
		 *  2160	Refund request previously submitted has been processed successfully.
		 *  2170	Cancellation request has been processed successfully.
		 *  2180	Transaction voided successfully.
		 *  2200	Transaction Declined/Not Authorized/Not Settled.
		 *  2201	Acquirer/Issuer does not allow this transaction.
		 *  2202	Cancellation declined by issuer/acquirer.
		 *  2203	Cancellation transaction failed.
		 *  2204	Card was authorized but AVS did not match. Contact client.
		 *  2205	Service provider does not allow this transaction.
		 *  2206	Incoming record currency type does not match system stored currency.
		 *  2208	Invalid merchant account number.
		 *  2210	Bad check digit, length, or other credit card problem.
		 *  2212	Card has expired or incorrect date entered. Confirm date.
		 *  2214	Card has expired.
		 *  2216	Amount sent was 0 or unreadable.
		 *  2218	Method of payment is invalid for this account number.
		 *  2219	The specific card will not accept payment.
		 *  2220	Method of payment is invalid for this merchant.
		 *  2222	Invalid information entered.
		 *  2223	No Sort code or Account Number in Payback system.
		 *  2224	Specific and relevant data within transaction is inaccurate or missing.
		 *  2226	Same transaction had been submitted.
		 *  2228	Issuer does not allow this transaction.
		 *  2229	Processor permits only one deposit request per authorization.
		 *  2230	Invalid merchant account number.
		 *  2232	Invalid issuer or institution.
		 *  2234	Invalid response code.
		 *  2235	Currency code submitted is different than code submitted with original authorization request.
		 *  2236	Invalid for credit.
		 *  2237	Invalid refund not allowed (CFT).
		 *  2238	Invalid for debit.
		 *  2240	Amex CID is incorrect.
		 *  2242	Honour with ID.
		 *  2248	Invalid Transaction
		 *  2280	Switch/Solo - Incorrect start date or requires an issue number. Please correct.
		 *  2282	Switch/Solo - 1-digit number submitted when 2-digit number should have been sent. Please correct.
		 *  2284	Switch/Solo - a format issue, re-examine transaction layout. Please correct.
		 *  2286	Bank not supported by Switch.
		 *  2300	No card record.
		 *  2302	Invalid bank routing number.
		 *  2304	Missing the cheque writer’s name.
		 *  2306	Bank account has been closed.
		 *  2308	Account type is invalid or missing. Deposit transactions only.
		 *  2310	Account does not exist.
		 *  2312	Account number does not correspond to the individual.
		 *  2314	Account holder deceased. No further debits will be accepted by the bank.
		 *  2316	Beneficiary deceased. No further debits will be accepted by the bank.
		 *  2318	The funds in this account are unavailable. No further debits will be accepted by the bank.
		 *  2320	Customer has refused to allow the transaction.
		 *  2322	Banking institute does not accept ACH transactions (For US ECP).
		 *  2324	Account number is incorrect.
		 *  2326	Customer has notified their bank not to accept these transactions.
		 *  2328	Customer has not authorized bank to accept these transactions.
		 *  2330	Pertains to Canadian ECP only.
		 *  2332	Format of account number does not pass check digit routine for that institution. (For CDN ECP).
		 *  2334	Invalid characters in account number.
		 *  2350	Card has surpassed daily transaction amount limit.
		 *  2351	Surpassed daily transaction amount limit.
		 *  2352	The limit of number of times used for the card has been surpassed.
		 *  2354	Card has surpassed its credit limit.
		 *  2356	Enter a lesser amount.
		 *  2357	Try Lesser Amount / Whole Dollar Only.
		 *  2358	No credit amount.
		 *  2360	Card is limited to one purchase.
		 *  2362	Over Sav limit.
		 *  2364	Over Sav frequency.
		 *  2366	Card not supported.
		 *  2368	Invalid PIN.
		 *  2370	 Allowable PIN tries exceeded.
		 *  2372	PIN required.
		 *  2374	Card failed MOD 10 check verification.
		 *  2380	Account number appears on negative file.
		 *  2382	Stop Payment Issued.
		 *  2384	Enter Whole Dollar Amount.
		 *  2386	Unauthorized Usage.
		 *  2400	PTLF full.
		 *  2401	Fraud suspected.
		 *  2402	Unable to process transaction.
		 *  2403	Duplicate transaction.
		 *  2404	Cutoff in progress.
		 *  2405	Incorrect PIN.
		 *  2406	PIN tries exceeded.
		 *  2407	Exceeds withdrawal frequency.
		 *  2410	Invalid 3D Secure Data.
		 *  2420	There is more than one error.
		 *  2430	Validation Fails Internal Check.
		 *  2431	Validation Fails Name Check.
		 *  2432	Validation Fails Routing Check.
		 *  2440	FirstName or LastName Invalid.
		 *  2610	Timeout waiting for host response.
		 *  2611	Internal timeout.
		 *  2612	Authorization host system is temporarily unavailable.
		 *  2613	Acquirer Cannot Process Transaction at This Time. Please Retry.
		 *  2614	Authorization host network could not reach the bank, which issued the card or Acquirer.
		 *  2615	Bank Timeout error / Re-Send
		 *  2616	Invalid issuer or institution.
		 *  2618	Unidentified error. Unable to process transaction.
		 *  2620	Unable to process transaction due to system malfunction.
		 *  2622	Unable to authorize due to system malfunction.
		 *  2624	Merchant information incomplete.
		 *  2626	Invalid CVN value.
		 *  2627	The track2 format information is incorrect.
		 *  2628	Merchant not Support this transaction.
		 *  2630	No such store ID for the merchant.
		 *  2632	Invalid authcode.
		 *  2634	Invalid format.
		 *  2636	Invalid message type.
		 *  2638	Invalid POS system type.
		 *  2640	A message has be sent to reverse previous time out transaction.
		 *  2642	This TrxSource is not supported by the bank.
		 *  2644	Not enough Terminal IDs at the time of transaction.
		 *  2646	Acquirer cannot process transaction.
		 *  2648	Retain card, no reason specified.
		 *  2649	DOB Does not Match Records.
		 *  2650	Resubmit with DOB.
		 *  2700	General error for PC card.
		 *  2702	Amount is invalid.
		 *  2704	Line items do not add up to summary total.
		 *  2706	Not supported for batch.
		 *  2712	Mandatory field is invalid or missing.
		 *  2714	Total line items do not add up.
		 *  2716	Line items missing.
		 *  2718	Commodity code is invalid or missing.
		 *  2720	Cross border information is invalid or missing.
		 *  2722	Not a purchase card.
		 *  2802	One of the ICC parameters submitted was invalid.
		 *  2804	The requested transaction was not found.
		 *  2830	Request in progress
		 *  2831	Partial Approval
		 *  2832	Restricted Card
		 *  2833	Exceeds Withdrawal Amount Limit
		 *  2844	Cannot Verify PIN
		 *  2845	No Cashback Allowed
		 *  2846	System Error
		 *  2847	Chargeback
		 *  2848	Cannot Route to Issuer
		 *  2849	Max Refund Reached
		 *  2850	Over floor Limit
		 *  2952	Card issuer wants card returned. Call issuer.
		 *  2954	Card reported as lost/stolen.
		 *  2956	Generic decline. No other information is being provided by the issuer.
		 *  2958	Issuer wants voice contact with cardholder.
		 *  2960	Insufficient funds.
		 *  2962	Issuer has declined request because CVV2 edit failed.
		 *  2964	Delinquent account.
		 *  2966	Prepaid card load failed.
		 *  2968	Prepaid card load limit exceeded.
		 *  2970	Velocity limit exceeded.
		 *  2972	Prepaid card process permission denied.
		 *  2974	Prepaid card invalid account ID.
		 *  2990	Cancellation is going to reverse the authorization.
		 *  3050	Transaction pending.
		 *  3051	A new rate is assigned for the transaction.
		 *  3052	Transaction waiting for placement approve then refund.
		 *  3100	FX transaction approved.
		 *  3111	Transaction rate escalated.
		 *  3170	Transaction cancelled successfully.
		 *  3171	Transaction refunded.
		 *  3200	Rate requested has expired and no new rate is available.
		 *  3203	The deposit/refund transaction being cancelled cannot be because it has already been submitted.
		 *  3204	Cancellation disabled in merchant set-up.
		 *  3206	Invalid currency of record.
		 *  3207	Exchange currency not setup in merchant account.
		 *  3208	Conversion to same currency redundant.
		 *  3209	Cannot convert to requested currency.
		 *  3210	Currency submitted does not match the original rate request.
		 *  3216	Invalid amount.
		 *  3217	FXID submitted is invalid.
		 *  3218	Unexpected error.
		 *  3219	Credit card is not valid for this transaction.
		 *  3220	Currency of card not supported.
		 *  3224	One or more required parameters are not present.
		 *  3226	Duplicated transaction.
		 *  3228	Generic error message for invalid transactions.
		 *  3321	Invalid account data.
		 *  3341	Quoted rate is not executable.
		 *  3354	Refund is over the original value of the deal.
		 *  3361	Quoted rate is invalid.
		 *  3362	Expired rate cannot be escalated.
		 *  3371	Rate has been revoked.
		 *  3381	Transaction min/max limits reached.
		 *  3391	Batch size exceeds the Maximum allowable size transaction/payment not written to database.
		 *  3614	FX system cannot be reached.
		 *  3781	Refund disabled in merchant set-up.
		 *  3783	Refund cannot be processed.
		 *  3785	Refund beyond maximum time period.
		 *  4050	Cardholder enrolled for 3D Secure.
		 *  4100	Cardholder answered password/challenge question correctly.
		 *  4101	Cardholder authentication attempted.
		 *  4200	Cardholder not enrolled for 3D Secure.
		 *  4202	Credit card is not recognized as a 3D Secure card.
		 *  4203	Cardholder enrolment not verified.
		 *  4204	Cardholder failed to answer password/challenge question.
		 *  4206	Invalid currency.
		 *  4208	Invalid merchant account number.
		 *  4210	Invalid credit card number.
		 *  4212	Invalid credit card expiration date.
		 *  4216	Invalid amount.
		 *  4224	Specific and relevant data within transaction is inaccurate or missing.
		 *  4225	The MessageId value from the acquirer was not in the expected format.
		 *  4228	Invalid transaction.
		 *  4230	Merchant Not Participating in 3D Secure.
		 *  4240	Enrolment process failed.
		 *  4242	Authentication process failed.
		 *  4614	MPI not available.
		 *  4616	Directory server not available.
		 *  4618	Internal MPI error.
		 *  4626	Invalid SecureId.
		 *  4700	3D Secure transaction already processed.
		 */

		$this->addCodeRange( 'AuthorizePaymentForFraud', 'MessageCode', 'failed', 2000, 2001 );
		$this->addCodeRange( 'AuthorizePaymentForFraud', 'MessageCode', 'failed', 2051 );
		$this->addCodeRange( 'AuthorizePaymentForFraud', 'MessageCode', 'failed', 2061, 2080 );
		$this->addCodeRange( 'AuthorizePaymentForFraud', 'MessageCode', 'failed', 2112 );
		$this->addCodeRange( 'AuthorizePaymentForFraud', 'MessageCode', 'failed', 2200, 2804 );
		$this->addCodeRange( 'AuthorizePaymentForFraud', 'MessageCode', 'failed', 2831, 2990 );
		$this->addCodeRange( 'AuthorizePaymentForFraud', 'MessageCode', 'failed', 3216, 3614 );
		$this->addCodeRange( 'AuthorizePaymentForFraud', 'MessageCode', 'failed', 4206, 4700 );

		$this->addCodeRange( 'AuthorizeAndDepositPayment', 'MessageCode', 'failed', 2000, 2001 );
		$this->addCodeRange( 'AuthorizeAndDepositPayment', 'MessageCode', 'pending', 2040, 2050 );
		$this->addCodeRange( 'AuthorizeAndDepositPayment', 'MessageCode', 'failed', 2051 );
		$this->addCodeRange( 'AuthorizeAndDepositPayment', 'MessageCode', 'pending', 2053, 2055 );
		$this->addCodeRange( 'AuthorizeAndDepositPayment', 'MessageCode', 'failed', 2061, 2080 );
		$this->addCodeRange( 'AuthorizeAndDepositPayment', 'MessageCode', 'failed', 2112 );
		$this->addCodeRange( 'AuthorizeAndDepositPayment', 'MessageCode', 'pending', 2122 );
		$this->addCodeRange( 'AuthorizeAndDepositPayment', 'MessageCode', 'complete', 2150, 2180 );
		$this->addCodeRange( 'AuthorizeAndDepositPayment', 'MessageCode', 'complete', 2100, 2106 );
		$this->addCodeRange( 'AuthorizeAndDepositPayment', 'MessageCode', 'failed', 2200, 2804 );
		$this->addCodeRange( 'AuthorizeAndDepositPayment', 'MessageCode', 'pending', 2830 );
		$this->addCodeRange( 'AuthorizeAndDepositPayment', 'MessageCode', 'failed', 2831, 2990 );
		$this->addCodeRange( 'AuthorizeAndDepositPayment', 'MessageCode', 'pending', 3050 );
		$this->addCodeRange( 'AuthorizeAndDepositPayment', 'MessageCode', 'failed', 3216, 3614 );
		$this->addCodeRange( 'AuthorizeAndDepositPayment', 'MessageCode', 'complete', 3100 );
		$this->addCodeRange( 'AuthorizeAndDepositPayment', 'MessageCode', 'failed', 4206, 4700 );
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
		$this->url = $this->getGlobal( 'URL' );

		switch ( $transaction ) {
			case 'GenerateToken':
				$result = parent::do_transaction( $transaction );
				if ( !$result['errors'] ) {
					// Save the OTT to the session for later
					$this->session_addDonorData();
				}
				return $result;
				break;

			case 'QueryAuthorizeDeposit':
				// Obtain all the form data from tokenization server
				$result = $this->do_transaction( 'QueryTokenData' );
				if ( !$this->getTransactionStatus() ) {
					$this->log( 'Failed transaction because QueryTokenData failed', LOG_ERR );
					$this->finalizeInternalStatus( 'failed' );
					return $result;
				}

				// If we managed to successfully get the token details; perform an authorization
				// with bank verification for fraud checks.
				if ( $this->getGlobal( 'NoFraudIntegrationTest' ) !== true ) {
					$result = $this->do_transaction( 'AuthorizePaymentForFraud' );
					if ( !$this->getTransactionStatus() ) {
						$this->log( 'Failed transaction because AuthorizePaymentForFraud failed' );
						$this->finalizeInternalStatus( 'failed' );
						return $result;
					}
					$code = $result['data']['MessageCode'];
					$result_status = $this->findCodeAction( 'AuthorizePaymentForFraud', 'MessageCode', $code );
					if ( $result_status ) {
						$this->log(
							"Finalizing transaction at AuthorizePaymentForFraud to {$result_status}. Code: {$code}"
						);
						$this->finalizeInternalStatus( $result_status );
						return $result;
					}
				}

				// We've successfully passed fraud checks; authorize and deposit the payment
				$result = $this->do_transaction( 'AuthorizeAndDepositPayment' );
				if ( !$this->getTransactionStatus() ) {
					$this->log( 'Failed transaction because AuthorizeAndDepositPayment failed' );
					$this->finalizeInternalStatus( 'failed' );
					return $result;
				}
				$code = $result['data']['MessageCode'];
				$result_status = $this->findCodeAction( 'AuthorizeAndDepositPayment', 'MessageCode', $code );
				if ( $result_status ) {
					$this->log(
						"Finalizing transaction at AuthorizeAndDepositPayment to {$result_status}. Code: {$code}"
					);
					$this->finalizeInternalStatus( $result_status );
				} else {
					$this->log(
						'Finalizing transaction at AuthorizeAndDepositPayment to failed because MessageCode (' .
						$code .') was unknown.',
						LOG_ERR
					);
					$this->finalizeInternalStatus( 'failed' );
				}
				return $result;
				break;

			case 'AuthorizePaymentForFraud':
				$this->addData( array( 'cvv' => $this->get_cvv() ) );
				$this->store_cvv_in_session( null ); // Remove the CVV from the session
				return parent::do_transaction( $transaction );
				break;

			default:
				return parent::do_transaction( $transaction );
				break;
		}
	}

	/**
	 * Will return true if the $response looks like it has data in it.
	 *
	 * True response processing will happen in processResponse().
	 *
	 * @param DOMDocument $response
	 *
	 * @return bool
	 */
	function getResponseStatus( $response ) {
		foreach( $response->getElementsByTagName( 'MessageCode' ) as $node) {
			return true;
		}
		return false;
	}

	/**
	 * Will return an empty array; errors can only be detected in processData()
	 * @param DOMDocument $response
	 */
	function getResponseErrors( $response ) {
		return array();
	}

	/**
	 *
	 * @param type $response
	 * @param type $retryVars
	 */
	public function processResponse( $response, &$retryVars = null ) {
		$self = $this;
		$addData = function( $pull_vars ) use ( $response, $self ) {
			$emptyVars = array();
			$addme = array ( );
			foreach ( $pull_vars as $theirs => $ours ) {
				if ( isset( $response['data'][$theirs] ) ) {
					$addme[$ours] = $response['data'][$theirs];
				} else {
					$emptyVars[] = $theirs;
				}
			}
			$self->addData( $addme, 'response' );
			return $emptyVars;
		};
		$setFailOnEmpty = function( $emptyVars ) use ( $response, $self ) {
			if ( count( $emptyVars ) !== 0 ) {
				$self->setTransactionResult( false, 'status' );
				$self->setTransactionResult( array(
						'internal-0001' => $self->getErrorMapByCodeAndTranslate( 'internal-0001' ),
						'errors',
					));
				$code = isset( $response['data']['MessageCode'] ) ? $response['data']['MessageCode'] : 'None given';
				$message = isset( $response['data']['Message'] ) ? $response['data']['Message'] : 'None given';
				$self->setTransactionResult(
					"Transaction failed (empty vars): ({$code}) {$message}",
					'message'
				);
			}
		};

		switch ( $this->getCurrentTransaction() ) {
			case 'GenerateToken':
				$setFailOnEmpty( $addData( array(
					'OTT' => 'wp_one_time_token',
					'OTTProcessURL' => 'wp_process_url',
					'RDID' => 'wp_rdid',
				)));
				break;

			case 'QueryTokenData':
				$setFailOnEmpty( $addData( array(
					'CardId' => 'wp_card_id',
					'CreditCardType' => 'payment_submethod',
				)));
				break;

			case 'AuthorizePaymentForFraud':
				$setFailOnEmpty( $addData( array(
					'CVNMatch' => 'cvv_result',
					'AddressMatch' => 'avs_address',
					'PostalCodeMatch' => 'avs_zip',
					'PTTID' => 'wp_pttid'
				)));
				break;
		}
	}

	function getResponseData( $response ) {
		$data = $this->xmlChildrenToArray( $response, 'TMSTN' );
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
			if ( $this->getCurrentTransaction() === 'AuthorizePaymentForFraud' ) {
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

	protected function stage_payment_submethod( $type = 'request' ) {
		if ( $type == 'response' ) {
			$paymentMethod = $this->getData_Unstaged_Escaped( 'payment_method' );
			$paymentSubmethod = $this->getData_Unstaged_Escaped( 'payment_submethod' );
			if ( $paymentMethod == 'cc' ) {
				if ( array_key_exists( $paymentSubmethod, self::$cardTypes ) ) {
					$this->unstaged_data['payment_submethod'] = self::$cardTypes[$paymentSubmethod];
				}
			}
		}
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
	 * @TODO: Once we get some data back from AuthorizePaymentForFraud, addData here
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
	protected function post_process_authorizepaymentforfraud() {
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
