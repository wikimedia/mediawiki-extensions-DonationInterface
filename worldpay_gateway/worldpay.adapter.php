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
use Psr\Log\LogLevel;

/**
 * WorldpayAdapter
 *
 */
class WorldpayAdapter extends GatewayAdapter {

	const GATEWAY_NAME = 'Worldpay Gateway';
	const IDENTIFIER = 'worldpay';
	const GLOBAL_PREFIX = 'wgWorldpayGateway';

	public $redirect = FALSE;
	public $log_outbound = TRUE;

	protected $cdata = array(
		'OTTResultURL'
	);


	/**
	 * @var string[] ISO Currency code letters to numbers (from appendix B of the
	 * integration manual). These are also apparently all the currencies that
	 * Worldpay can support.
	 *
	 * TODO: move to reference data
	 */
	public static $CURRENCY_CODES = array(
		'AED' => 784,
		'ALL' => 8,
		'ANG' => 532,
		'ARS' => 32,
		'AUD' => 36,
		'AWG' => 533,
		'AZN' => 944,
		'BAM' => 977,
		'BBD' => 52,
		'BDT' => 50,
		'BGN' => 975,
		'BHD' => 48,
		'BMD' => 60,
		'BND' => 96,
		'BOB' => 68,
		'BRL' => 986,
		'BSD' => 44,
		'BWP' => 72,
		'BZD' => 84,
		'CAD' => 124,
		'CHF' => 756,
		'CLP' => 152,
		'CNY' => 156,
		'COP' => 170,
		'CRC' => 188,
		'CUP' => 192,
		'CZK' => 203,
		'DJF' => 262,
		'DKK' => 208,
		'DOP' => 214,
		'DZD' => 12,
		'EGP' => 818,
		'ERN' => 232,
		'ETB' => 230,
		'EUR' => 978,
		'FJD' => 242,
		'GBP' => 826,
		'GEL' => 981,
		'GIP' => 292,
		'GTQ' => 320,
		'GYD' => 328,
		'HKD' => 344,
		'HNL' => 340,
		'HRK' => 191,
		'HTG' => 332,
		'HUF' => 348,
		'IDR' => 360,
		'ILS' => 376,
		'INR' => 356,
		'IQD' => 368,
		'JMD' => 388,
		'JOD' => 400,
		'JPY' => 392,
		'KES' => 404,
		'KHR' => 116,
		'KRW' => 410,
		'KWD' => 414,
		'KYD' => 136,
		'KZT' => 398,
		'LBP' => 422,
		'LKR' => 144,
		'LSL' => 426,
		'LTL' => 440,
		'LVL' => 428,
		'MAD' => 504,
		'MKD' => 807,
		'MNT' => 496,
		'MOP' => 446,
		'MRO' => 478,
		'MUR' => 480,
		'MVR' => 462,
		'MWK' => 454,
		'MXN' => 484,
		'MYR' => 458,
		'MZN' => 943,
		'NAD' => 516,
		'NGN' => 566,
		'NIO' => 558,
		'NOK' => 578,
		'NPR' => 524,
		'NZD' => 554,
		'OMR' => 512,
		'PAB' => 590,
		'PEN' => 604,
		'PGK' => 598,
		'PHP' => 608,
		'PKR' => 586,
		'PLN' => 985,
		'PYG' => 600,
		'QAR' => 634,
		'RON' => 946,
		'RSD' => 941,
		'RUB' => 643,
		'RWF' => 646,
		'SAR' => 682,
		'SCR' => 690,
		'SEK' => 752,
		'SGD' => 702,
		'SLL' => 694,
		'SVC' => 222,
		'SYP' => 760,
		'SZL' => 748,
		'THB' => 764,
		'TND' => 788,
		'TRY' => 949,
		'TTD' => 780,
		'TWD' => 901,
		'TZS' => 834,
		'UAH' => 980,
		'USD' => 840,
		'UYU' => 858,
		'UZS' => 860,
		'VEF' => 937,
		'XAF' => 950,
		'XCD' => 951,
		'XOF' => 952,
		'XPF' => 953,
		'YER' => 886,
		'ZAR' => 710,
		'ZMK' => 894,
	);

	public function __construct( $options = array ( ) ) {
		parent::__construct( $options );
	}

	public function getRequiredFields() {
		$fields = parent::getRequiredFields();
		$fields[] = 'payment_method';
		$fields[] = 'payment_submethod';
		return $fields;
	}

	/**
	 * Enhanced Silent Order Post AKA iframe
	 */
	public function isESOP() {
		return $this->dataObj->getVal_Escaped( 'ffname' ) === 'wp-if';
	}

	public function getFormClass() {
		if ( $this->isESOP() ) {
			return parent::getFormClass();
		}
		return 'Gateway_Form_RapidHtml';
	}

	public function getCommunicationType() {
		return 'xml';
	}

	function defineStagedVars() {
		$this->staged_vars = array(
			'returnto',
			'wp_acctname',
			'iso_currency_id',
			'payment_submethod',
			'zip',
			'street',
			'merchant_reference_2',
			'narrative_statement_1',
		);
	}

	function defineAccountInfo() {
		$this->accountInfo = array(
			'IsTest' => $this->account_config[ 'Test' ],
			'TokenizingMerchantID' => $this->account_config[ 'TokenizingMerchantID' ],
			'MerchantIDs' => $this->account_config[ 'MerchantIDs' ],
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

			// MerchantReference2
			'merchant_reference_2' => array( 'type' => 'alphanumeric', 'length' => 60 ),

			// NarrativeStatement1
			'narrative_statement_1' => array( 'type' => 'alphanumeric', 'length' => 50 ),
		);
	}

	/**
	 * Worldpay doesn't check order numbers until settlement at
	 * which point it's too late to do much about it. So; our order
	 * numbers will by the contribution tracking ID with an attempt
	 * number appended, indicated by the ct_id flag.
	 */
	function defineOrderIDMeta() {
		$this->order_id_meta = array (
			'generate' => TRUE,
			'ct_id' => TRUE,
		);
	}

	function setGatewayDefaults() {
		$this->addRequestData( array(
			'region_code'  => 0  // TODO: geolocating this into the right region...
		));
	}

	public function getCurrencies( $options = array() ) {
		return array_keys( self::$CURRENCY_CODES );
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

		// ADDITIONAL NOTE: ESOP needs this value unset, so I've removed it and
		// added logic in do_transaction to set it if the transaction is not ESOP.
		$this->transactions['AuthorizePaymentForFraud'] = array(
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

				'CVN'
			),
			'values' => array(
				'VersionUsed' => 6,
				'TransactionType' => 'PT',  // PaymentTrust
				'Timeout' => 60000,         // 60 seconds
				'RequestType' => 'A',       // Authorize a payment
				'TRXSource' => 4,           // Card not present (web order) transaction
				'MOP' => 'CC',              // Credit card transaction
				'Amount' => '0.10',			// Perform a small amount authorization (just enough to trigger it)
			),
			'never_log' => array (
				'CVN'
			),
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
				'MerchantReference2',
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
		//Well, this is probably going to get annoying as soon as we want to get specific here.
		//We can't just use numbers here: We're going to have to break it out by request and number.
		$this->error_map = array(
			// Internal messages
			'internal-0000' => 'donate_interface-processing-error', // Failed failed pre-process checks.
			'internal-0001' => 'donate_interface-processing-error', // Transaction could not be processed due to an internal error.
			'internal-0002' => 'donate_interface-processing-error', // Communication failure
			'internal-0003' => 'donate_interface-processing-error', // Some error code returned from one of the daisy-chained requests.
		);
	}

	function defineReturnValueMap() {
		/* From the integration manual; this is the list of all possible return codes
		 * (excluding the 3000 series which are foreign exchange system errors which we don't use)
		 *
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
		 *  2370	Allowable PIN tries exceeded.
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

		// PaymentTrust codes
		// Anything other than 2050 or 2100 must be treated as failure for PT-A or PT-S or PT-R.
		// Anything other than 2170 must be treated as failure for PT-C.
		// ***LOOK AT THIS COMMENT: If you add AuthorizePaymentForFraud success statuses here,
		// you will need to whack a finalizeInternalStatus in do_transaction_QueryAuthorizeDeposit
		$this->addCodeRange( 'AuthorizePaymentForFraud', 'MessageCode', FinalStatus::FAILED, 2000, 2049 );
		$this->addCodeRange( 'AuthorizePaymentForFraud', 'MessageCode', FinalStatus::FAILED, 2051, 2099 );
		$this->addCodeRange( 'AuthorizePaymentForFraud', 'MessageCode', FinalStatus::FAILED, 2101, 2999 );

		$this->addCodeRange( 'AuthorizeAndDepositPayment', 'MessageCode', FinalStatus::FAILED, 2000, 2049 );
		$this->addCodeRange( 'AuthorizeAndDepositPayment', 'MessageCode', FinalStatus::COMPLETE, 2050 );
		$this->addCodeRange( 'AuthorizeAndDepositPayment', 'MessageCode', FinalStatus::FAILED, 2051, 2099 );
		$this->addCodeRange( 'AuthorizeAndDepositPayment', 'MessageCode', FinalStatus::COMPLETE, 2100 );
		$this->addCodeRange( 'AuthorizeAndDepositPayment', 'MessageCode', FinalStatus::FAILED, 2101, 2999 );
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
			'LAN'               => 'language',
			'Email'             => 'email',
			'REMOTE_ADDR'       => 'user_ip',
			'StoreID'           => 'wp_storeid',
			'CurrencyId'        => 'iso_currency_id',
			'AcctName'          => 'wp_acctname',
			'CVN'               => 'cvv',
			'PTTID'             => 'wp_pttid',
			'UserName'          => 'username',
			'UserPassword'      => 'user_password',
			'MerchantId'        => 'wp_merchant_id',
			'MerchantReference2'=> 'merchant_reference_2',
			'NarrativeStatement1'=> 'narrative_statement_1',
		);
	}

	public function defineDataTransformers() {
		$this->data_transformers = array_merge( parent::getCoreDataTransformers(), array(
			new WorldpayAccountName(),
			new WorldpayCurrency(),
			new WorldpayEmail(),
			new WorldpayMethodCodec(),
			new WorldpayNarrativeStatement(),
			new WorldpayReturnto(),
		) );
	}

	/**
	 * Check if the currently-staged store ID is configured for special treatment.
	 * Certain store IDs (just FR so far) do not get AVS results, and always get
	 * a 'fail' result for CVV.  These are configured in the account_config's
	 * SpecialSnowflakeStoreIDs array.
	 *
	 * @return bool Whether currently staged account is special
	 */
	private function is_snowflake_account() {
		return array_key_exists( 'SpecialSnowflakeStoreIDs', $this->account_config )
			&& array_key_exists( 'wp_storeid', $this->staged_data )
			&& in_array( $this->staged_data['wp_storeid'], $this->account_config['SpecialSnowflakeStoreIDs'] );
	}

	public function getBasedir() {
		return __DIR__;
	}
	public function doPayment() {
		return PaymentResult::fromResults(
			$this->do_transaction( 'QueryAuthorizeDeposit' ),
			$this->getFinalStatus()
		);
	}

	/**
	 * @param string $transaction
	 * @return PaymentTransactionResponse
	 */
	public function do_transaction( $transaction ) {
		$this->url = $this->getGlobal( 'URL' );

		$this->loadRoutingInfo( $transaction );

		if ( $this->isESOP() ) {
			// This needs to go in every ESOP request because otherwise
			// they return "Transaction NOT Authorized"
			$this->transactions[$transaction]['request'][] = 'IsCVNMEM';
			$this->transactions[$transaction]['values']['IsCVNMEM'] = 1;
		}

		switch ( $transaction ) {
			case 'GenerateToken':
				if ( $this->isESOP() ) {
					// This parameter will cause WP to use the iframe code path.
					$this->transactions[$transaction]['request'][] = 'IsHosted';
					$this->transactions[$transaction]['values']['IsHosted'] = 1;
					// Translate the iframe to our language.
					$this->transactions[$transaction]['request'][] = 'LAN';
				}

				$result = parent::do_transaction( $transaction );
				if ( !$result->getErrors() ) {
					// Save the OTT to the session for later
					$this->session_addDonorData();
				}
				return $result;
				break;

			case 'QueryAuthorizeDeposit':
				$result = $this->do_transaction_QueryAuthorizeDeposit();
				$this->transaction_response->setGatewayTransactionId( $this->getData_Unstaged_Escaped( 'order_id' ) );
				$this->runPostProcessHooks();
				return $result;
				break;

			case 'AuthorizePaymentForFraud':
				$this->addRequestData( array( 'cvv' => $this->get_cvv() ) );
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
	function parseResponseCommunicationStatus( $response ) {
		foreach( $response->getElementsByTagName( 'MessageCode' ) as $node) {
			return true;
		}
		return false;
	}

	/**
	 * Check the response data for error conditions, and return them.
	 *
	 * If the site has $wgDonationInterfaceDisplayDebug = true, then the real
	 * messages will be sent to the client. Messages will not be translated or
	 * obfuscated.  TODO: check DisplayDebug at output, not here
	 *
	 * @param DOMDocument	$response	The XML response data all loaded into a DOMDocument
	 * @return array
	 */
	function parseResponseErrors( $response ) {
		$code = false;
		$message = false;
		$errors = array( );

		//only expecting one code / message. Everything else would be extreme edgecase time
		foreach ( $response->getElementsByTagName( 'MessageCode' ) as $node ) {
			$code = $node->nodeValue;
			break;
		}
		foreach ( $response->getElementsByTagName( 'Message' ) as $node ) {
			$message = $node->nodeValue;
			break;
		}

		if ( $code ) {
			//determine if the response code is, in fact, an error.
			$action = $this->findCodeAction( $this->getCurrentTransaction(), 'MessageCode', $code );
			if ( $action === FinalStatus::FAILED ) {
				//use generic internals, I think.
				//I can't tell if I'm being lazy here, or if we genuinely don't need to get specific with this.
				$errors[$code] = array(
					'logLevel' => LogLevel::DEBUG,
					'message' => $this->getGlobal( 'DisplayDebug' ) ? '*** ' . $message : $this->getErrorMapByCodeAndTranslate( 'internal-0003' ),
					'debugInfo' => $message
				);
			}
		}
		return $errors;
	}

	/**
	 * @param DomDocument $response
	 */
	public function processResponse( $response ) {
		$this->transaction_response->setCommunicationStatus(
			$this->parseResponseCommunicationStatus( $response )
		);
		$errors = $this->parseResponseErrors( $response );
		$this->transaction_response->setErrors( $errors );
		$data = $this->parseResponseData( $response );
		$this->transaction_response->setData( $data );
		switch ( $this->getCurrentTransaction() ) {
			case 'GenerateToken':
				$required = null;
				if ( $this->isESOP() ) {
					$required = array(
						'OTTRedirectURL' => 'wp_redirect_url',
						'RDID' => 'wp_rdid',
					);
				} else {
					$required = array(
						'OTT' => 'wp_one_time_token',
						'OTTProcessURL' => 'wp_process_url',
						'RDID' => 'wp_rdid',
					);
				}
				$this->addRequiredData( $data, $required );
				break;

			case 'QueryTokenData':
				$this->addRequiredData( $data, array(
					'CardId' => 'wp_card_id',
					'CreditCardType' => 'payment_submethod',
				) );
				break;

			case 'AuthorizePaymentForFraud':
				// StoreIDs for certain countries (just FR so far) get XML responses
				// with no AVS results and no 'CVNMatch' node.
				$needfulThings = $this->is_snowflake_account() ? array( 
					'PTTID' => 'wp_pttid',
				) : array(
					'AddressMatch' => 'avs_address',
					'PostalCodeMatch' => 'avs_zip',
					'PTTID' => 'wp_pttid',
					'CVNMatch' => 'cvv_result',
				);
				$this->addRequiredData( $data, $needfulThings );
				$this->dataObj->expunge( 'cvv' );
				break;
		}
		if ( isset( $data['MessageCode'] ) ) {
			$code = $data['MessageCode'];
			// 'Retain card' or 'Card stolen'.  Penalize the IP
			if ( ( $code == '2648' || $code == '2952' || $code == '2954' )
				&& $this->getGlobal( 'EnableIPVelocityFilter' )
			) {
				Gateway_Extras_CustomFilters_IP_Velocity::penalize( $this );
			}
		}
	}

	public function setClientVariables( &$vars ) {
		$vars['wgWorldpayGatewayTokenTimeout'] = $this->getGlobal( 'TokenTimeout' );
	}

	/**
	 * Adds required data from the response to our staged collection
	 * @param array $data parsed out of payment processor API response
	 * @param array $pull_vars required variables. keys are their var names, values are ours
	 * @throws ResponseProcessingException if any required variables are missing
	 */
	protected function addRequiredData( $data, $pull_vars ) {
		$emptyVars = array( );
		$addme = array( );
		foreach ( $pull_vars as $theirs => $ours ) {
			if ( isset( $data[$theirs] ) ) {
				$addme[$ours] = $data[$theirs];
			} else {
				$emptyVars[] = $theirs;
			}
		}
		$this->addResponseData( $addme );
		if ( count( $emptyVars ) !== 0 ) {
			$this->transaction_response->setCommunicationStatus( false );
			$this->transaction_response->setErrors( array(
				'internal-0001' => array(
					'debugInfo' => 'Empty variables ' . implode( ',', $emptyVars ),
					'message' => $this->getErrorMapByCodeAndTranslate( 'internal-0001' ),
					'logLevel' => LogLevel::ERROR
				),
			) );
			$code = isset( $data['MessageCode'] ) ? $data['MessageCode'] : 'None given';
			$message = isset( $data['Message'] ) ? $data['Message'] : 'None given';
			$this->transaction_response->setMessage(
				"Transaction failed (empty vars): ({$code}) {$message}"
			);
			throw new ResponseProcessingException(
				$message,
				$code
			);
		}
	}

	public function parseResponseData( $response ) {
		$data = $this->xmlChildrenToArray( $response, 'TMSTN' );
		return $data;
	}

	// Worldpay is apparently not very worldly in the ways of alphabets
	protected function buildRequestXML( $rootElement = 'TMSTN', $encoding = 'ISO-8859-1' ) {
		$xml = parent::buildRequestXML( $rootElement, $encoding );
		return 'StringIn=' . str_replace( "\n", '', $xml );
	}

	// override the charset from the parent function
	protected function getTransactionSpecificValue( $gateway_field_name, $token = false ) {
		$original = parent::getTransactionSpecificValue( $gateway_field_name, $token );
		return EncodingMangler::singleton()->transliterate( $original );
	}

	// override the charset from the parent function
	function getCurlBaseHeaders() {
		$headers = parent::getCurlBaseHeaders();
		foreach ( $headers as $index => $header ) {
			if ( substr( $header, 0, 13 ) == 'Content-Type:' ) {
				$headers[$index] = preg_replace( '/\bcharset=utf-8\b/', 'charset=iso-8859-1', $header, 1 );
			}
		}
		return $headers;
	}

	/**
	 * WorldPay requires different OrderNumbers for each transaction within a donation.
	 * This will add suffixes to order_id for token generation, token querying, and fraud test.
	 * The real authorization and sale transaction keeps the old order ID format
	 * @param string $order_id - the base order_id for this donation attempt
	 */
	protected function set_transaction_order_ids( $order_id ) {
		if ( empty( $order_id ) ) {
			return;
		}
		$this->transactions['GenerateToken']['values']['OrderNumber'] = $order_id . '.0';
		$this->transactions['QueryTokenData']['values']['OrderNumber'] = $order_id . '.1';
		$this->transactions['AuthorizePaymentForFraud']['values']['OrderNumber'] = $order_id . '.2';
	}

	/**
	 * Can't add order_id to staged_vars without nasty side effects, so we have
	 * to override this to catch changes
	 */
	public function normalizeOrderID( $override = null, $dataObj = null ) {
		$value = parent::normalizeOrderID( $override, $dataObj );
		$this->set_transaction_order_ids( $value );
		return $value;
	}

	/**
	 * Can't add order_id to staged_vars without nasty side effects, so we have
	 * to override this to catch changes
	 */
	public function regenerateOrderID() {
		parent::regenerateOrderID();
		$order_id = $this->getData_Unstaged_Escaped( 'order_id' );
		$this->set_transaction_order_ids( $order_id );
	}

	/**
	 * @throws RuntimeException
	 */
	protected function loadRoutingInfo( $transaction ) {
		switch ( $transaction ) {
			case 'QueryAuthorizeDeposit':
			case 'GenerateToken':
			case 'QueryTokenData':
				$mid = $this->account_config['TokenizingMerchantID'];
				$this->staged_data['wp_merchant_id'] = $mid;
				$this->staged_data['username'] = $this->account_config['MerchantIDs'][$mid]['Username'];
				$this->staged_data['user_password'] = $this->account_config['MerchantIDs'][$mid]['Password'];
				break;
			default:
				$submethod = $this->getData_Unstaged_Escaped( 'payment_submethod' );
				$country = $this->getData_Unstaged_Escaped( 'country' );
				$currency = $this->getData_Unstaged_Escaped( 'currency_code' );

				$merchantId = null;
				$storeId = null;
				foreach( $this->account_config['StoreIDs'] as $storeConfig => $info ) {
					list( $storeSubmethod, $storeCountry, $storeCurrency ) = explode( '/', $storeConfig );
					if ( ( $submethod === $storeSubmethod || $storeSubmethod === '*' ) &&
						( $country === $storeCountry || $storeCountry === '*' ) &&
						$currency === $storeCurrency
					) {
						list( $merchantId, $storeId ) = $info;
						$this->logger->info( "Using MID: {$merchantId}, SID: {$storeId} for " .
							"submethod: {$submethod}, country: {$country}, currency: {$currency}."
						);
						break;
					}
				}

				if ( !$merchantId ) {
					throw new RuntimeException( 'Could not find account information for ' .
						"submethod: {$submethod}, country: {$country}, currency: {$currency}." );
				} else {
					$this->staged_data['wp_merchant_id'] = $merchantId;
					$this->staged_data['username'] = $this->account_config['MerchantIDs'][$merchantId]['Username'];
					$this->staged_data['user_password'] = $this->account_config['MerchantIDs'][$merchantId]['Password'];
					$this->staged_data['wp_storeid'] = $storeId;
				}
				break;
		}
	}

	public function session_addDonorData() {
		parent::session_addDonorData();
		// XXX: We might end up moving this into a STOMP required field,
		// but I don't know yet so kludging it in here so we have it for later
		$donorData = $this->request->getSessionData( 'Donor' );
		$donorData['wp_one_time_token'] = $this->getData_Unstaged_Escaped( 'wp_one_time_token' );
		$this->request->setSessionData( 'Donor', $donorData );
	}

	public function store_cvv_in_session( $cvv ) {
		$donorData = $this->request->getSessionData( 'Donor_protected' );
		if ( !is_null( $cvv ) ) {
			$donorData['cvv'] = $cvv;
		} else if ( is_array( $donorData ) ) {
			unset( $donorData['cvv'] );
		}
		$this->request->setSessionData( 'Donor_protected', $donorData );
	}

	protected function get_cvv() {
		$donorData = $this->request->getSessionData( 'Donor_protected' );
		if ( is_array( $donorData ) && isset( $donorData['cvv'] ) ) {
			return $donorData['cvv'];
		}
		return null;
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
		// Special accounts always return false, but we let them through
		if ( $this->is_snowflake_account() ) {
			return true;
		}

		$cvv_result = '';
		if ( !is_null( $this->getData_Unstaged_Escaped( 'cvv_result' ) ) ) {
			$cvv_result = $this->getData_Unstaged_Escaped( 'cvv_result' );
		}

		$cvv_map = $this->getGlobal( 'CvvMap' );

		if ( !isset( $cvv_map[$cvv_result] ) ) {
			$this->logger->warning( "Unrecognized cvv_result '$cvv_result'" );
			return false;
		}

		$result = $cvv_map[$cvv_result];
		return $result;
	}

	/**
	 * getAVSResult is intended to be used by the functions filter, to
	 * determine if we want to fail the transaction ourselves or not.
	 *
	 * In Worldpay, we get two values back that we get to synthesize
	 * together: One for address, and one for zip.
	 */
	public function getAVSResult() {
		// Special accounts are missing the AVS nodes, but we don't fail them.
		if ( $this->is_snowflake_account() ) {
			return 0;
		}

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

	/**
	 * Wrapper function for the big 'finalize the payment' meta transaction
	 *
	 * Will run the QueryTokenData, AuthorizePaymentForFraud, and
	 * AuthorizeAndDepositPayment API calls.
	 *
	 * @return PaymentTransactionResponse
	 */
	protected function do_transaction_QueryAuthorizeDeposit() {
		// Obtain all the form data from tokenization server
		$result = $this->do_transaction( 'QueryTokenData' );
		if ( !$this->getTransactionStatus() ) {
			$this->logger->error( 'Failed transaction because QueryTokenData failed' );
			$this->finalizeInternalStatus( FinalStatus::FAILED );
			return $result;
		}

		// If we managed to successfully get the token details; perform an authorization
		// with bank verification for fraud checks.
		if ( $this->getGlobal( 'NoFraudIntegrationTest' ) !== true ) {
			$result = $this->do_transaction( 'AuthorizePaymentForFraud' );
			if ( !$this->getTransactionStatus() ) {
				$this->logger->info( 'Failed transaction because AuthorizePaymentForFraud failed' );
				$this->finalizeInternalStatus( FinalStatus::FAILED );
				return $result;
			}
			$data = $result->getData();
			$code = $data[ 'MessageCode' ];
			$result_status = $this->findCodeAction( 'AuthorizePaymentForFraud', 'MessageCode', $code );
			if ( $result_status ) {
				$this->logger->info(
					"Finalizing transaction at AuthorizePaymentForFraud to {$result_status}. Code: {$code}"
				);
				//NOOOOO.
				//Except: Sure. For now. The only reason this works here, though,
				//is that all the success statuses for intermediate transactions
				//are not defined in defineReturnValueMap().
				$this->finalizeInternalStatus( $result_status );
				return $result;
			}
		}

		// We've successfully passed fraud checks; authorize and deposit the payment
		$result = $this->do_transaction( 'AuthorizeAndDepositPayment' );
		if ( !$this->getTransactionStatus() ) {
			$this->logger->info( 'Failed transaction because AuthorizeAndDepositPayment failed' );
			$this->finalizeInternalStatus( FinalStatus::FAILED );
			return $result;
		}
		$data = $result->getData();
		$code = $data['MessageCode'];
		$result_status = $this->findCodeAction( 'AuthorizeAndDepositPayment', 'MessageCode', $code );
		if ( $result_status ) {
			$this->logger->info(
				"Finalizing transaction at AuthorizeAndDepositPayment to {$result_status}. Code: {$code}"
			);
			$this->finalizeInternalStatus( $result_status );
		} else {
			$this->logger->error(
				'Finalizing transaction at AuthorizeAndDepositPayment to failed because MessageCode (' .
				$code . ') was unknown.'
			);
			$this->finalizeInternalStatus( FinalStatus::FAILED );
		}
		return $result;
	}

	/**
	 * For now, keep API call sequence number in sync with numAttempt.
	 * In the future, we may need to increment the sequence number with each API
	 * call to facilitate automatic refunds.
	 */
	protected function incrementNumAttempt() {
		$this->incrementSequenceNumber();
		parent::incrementNumAttempt();
	}
}
