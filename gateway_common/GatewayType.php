<?php

/**
 * GatewayType Interface
 *
 */
interface GatewayType {
	// all the particulars of the child classes. Aaaaall.

	/**
	 * Gets the name of the payment processor, e.g. Adyen
	 * @return string
	 */
	public static function getGatewayName();

	/**
	 * Get a tag to use to identify this adapter in logs, e.g. amazon_gateway
	 * @return string
	 */
	public static function getLogIdentifier();

	/**
	 * This function is important.
	 * All the globals in Donation Interface should be accessed in this manner
	 * if they are meant to have a default value, but can be overridden by any
	 * of the gateways. It will check to see if a gateway-specific global
	 * exists, and if one is not set, it will pull the default from the
	 * wgDonationInterface definitions. Through this function, it is no longer
	 * necessary to define gateway-specific globals in LocalSettings unless you
	 * wish to override the default value for all gateways.
	 * If the variable exists in {prefix}AccountInfo[currentAccountName],
	 * that value will override the default settings.
	 *
	 * @param string $varname The global value we're looking for. It will first
	 * look for a global named for the instantiated gateway's GLOBAL_PREFIX,
	 * plus the $varname value. If that doesn't come up with anything that has
	 * been set, it will use the default value for all of donation interface,
	 * stored in $wgDonationInterface . $varname.
	 * @return mixed The configured value for that gateway if it exists. If not,
	 * the configured value for Donation Interface if it exists or not.
	 */
	public static function getGlobal( $varname );

	/**
	 * Process the API response obtained from the payment processor and set
	 * properties of transaction_response
	 * @param array|DomDocument $response Cleaned-up response returned from
	 *        @see getFormattedResponse.  Type depends on $this->getResponseType
	 * @throws ResponseProcessingException with an actionable error code and any
	 *         variables to retry
	 */
	public function processResponse( $response );

	/**
	 * Should be a list of our variables that need special staging.
	 * @see $this->staged_vars
	 */
	function defineStagedVars();

	/**
	 * defineTransactions will define the $transactions array.
	 * The array will contain everything we need to know about the request structure for all the transactions we care about,
	 * for the current gateway.
	 * First array key: Some way for us to id the transaction. Doesn't actually have to be the gateway's name for it, but I'm going with that until I have a reason not to.
	 * Second array key:
	 * 		'request' contains the structure of that request. Leaves in the array tree will eventually be mapped to actual values of ours,
	 * 		according to the precidence established in the getTransactionSpecificValue function.
	 * 		'values' contains default values for the transaction. Things that are typically not overridden should go here.
	 */
	function defineTransactions();

	/**
	 * Define the message keys used to display errors to the user.  Should set
	 * @see $this->error_map to an array whose keys are error codes and whose
	 * values are i18n keys or callables that return a translated error message.
	 * Any unmapped error code will use 'donate_interface-processing-error'
	 */
	function defineErrorMap();

	/**
	 * defineVarMap needs to set up the $var_map array.
	 * Keys = the name (or node name) value in the gateway transaction
	 * Values = the mediawiki field name for the corresponding piece of data.
	 */
	function defineVarMap();

	/**
	 */
	function defineDataConstraints();

	/**
	 * defineAccountInfo needs to set up the $accountInfo array.
	 * Keys = the name (or node name) value in the gateway transaction
	 * Values = The actual values for those keys. Probably have to access a global or two. (use getGlobal()!)
	 */
	function defineAccountInfo();

	/**
	 * defineReturnValueMap sets up the $return_value_map array.
	 * Keys = The different constants that may be contained as values in the gateway's response.
	 * Values = what that string constant means to mediawiki.
	 */
	function defineReturnValueMap();

	/**
	 * Sets up the $payment_methods array.
	 * Keys = unique name for this method
	 * Values = metadata about the method
	 *   'validation' should be an array whose keys are field names and
	 *                whose values indicate whether the field is required
	 *   FIXME: 'label' is often set (untranslated) but never used
	 */
	function definePaymentMethods();

	/**
	 * Sets up the $order_id_meta array.
	 * @TODO: Data Item Class. There should be a class that keeps track of
	 * the metadata for every field we use (everything that currently comes
	 * back from DonationData), that can be overridden per gateway. Revisit
	 * this in a more universal way when that time comes.
	 *
	 * In general, $order_id_meta contains default data about how we
	 * handle/create/gather order_id, which needs to be defined on a
	 * per-gateway basis. Once $order_id_meta has been used to decide the
	 * order_id for the current request, it will also be used to keep
	 * information about the origin and state of the order_id data.
	 *
	 * Should contain the following keys/values:
	 * 'alt_locations' => array( $dataset_name, $dataset_key )
	 *	** alt_locations is intended to contain a list of arrays that
	 *	are always available (or should be), from which we can pull the
	 *	order_id.
	 *	** Examples of valid things to throw in $dataset_name are 'request'
	 *	and 'session'
	 *	** $dataset_key : The key in the associated dataset that is
	 *	expected to contain the order_id. Probably going to be order_id
	 *	if we are generating the dataset internally. Probably something
	 *	else if a gateway is posting or getting back to us in a
	 *	resultswitcher situation.
	 *	** These should be expressed in $order_id_meta in order of
	 *	preference / authority.
	 * 'generate' => boolean value. True if we will be generating our own
	 *	order IDs, false if we are deferring order_id generation to the
	 *	gateway.
	 * 'ct_id' => boolean value.  If True, when generating order ID use
	 * the contribution tracking ID with the sequence number appended
	 *
	 * Will eventually contain the following keys/values:
	 * 'final'=> The value that we have chosen as the valid order ID for
	 *	this request.
	 * 'final_source' => Where we ultimately decided to grab the value we
	 *	chose to stuff in 'final'.
	 */
	function defineOrderIDMeta();

	/**
	 * Called in the constructor, this function should be used to define
	 * pieces of default data particular to the gateway. It will be up to
	 * the child class to poke the data through to the data object
	 * (probably with $this->addRequestData()).
	 * DO NOT set default payment information here (or anywhere, really).
	 * That would be naughty.
	 */
	function setGatewayDefaults();

	/**
	 * @param array $options If given, try to filter supported currencies by:
	 *                       'country' ISO 3166 2 letter country code
	 *                       'payment_method'
	 *                       'payment_submethod'
	 * @return array of ISO 4217 currency codes supported by this adapter for
	 * the given options. If options are not given, the adapter may return
	 * all supported currencies of filter by the unstaged data.
	 */
	function getCurrencies( $options = array() );

	/**
	 * Attempt the default transaction for the current DonationData
	 *
	 * @return PaymentResult hints for the next donor interaction
	 */
	function doPayment();

	/**
	 * Data format for outgoing requests to the processor.
	 * Must be one of 'xml', 'namevalue' (for POST), or 'redirect'.
	 * May depend on current transaction.
	 *
	 * @return string
	 */
	function getCommunicationType();

	/**
	 * Data format for responses coming back from the processor.
	 * Should be 'xml', 'json', or 'delimited'
	 *
	 * @return string
	 */
	function getResponseType();

	/**
	 * This is the ONLY getData type function anything should be using
	 * outside the adapter.
	 * Short explanation of the data population up to now:
	 *	*) When the gateway adapter is constructed, it constructs a DonationData
	 *		object.
	 *	*) On construction, the DonationData object pulls donation data from an
	 *		appropriate source, and normalizes the entire data set for storage.
	 *	*) The gateway adapter pulls normalized, html escaped data out of the
	 *		DonationData object, as the base of its own data set.
	 * @param string $val The specific key you're looking for (if any)
	 * @return mixed An array of all the raw, unstaged (but normalized and
	 * sanitized) data sent to the adapter, or if $val was set, either the
	 * specific value held for $val, or null if none exists.
	 */
	public function getData_Unstaged_Escaped( $val = '' );

	/**
	 * Retrieve the data we will need in order to retry a payment.
	 * This is useful in the event that we have just killed a session before
	 * the next retry.
	 * @return array Data required for a payment retry.
	 */
	public function getRetryData();

	/**
	 * Get metadata for the specified payment method as set in
	 * @see definePaymentMethods
	 *
	 * @param string|null $payment_method Defaults to the current method
	 * @return array
	 * @throws OutOfBoundsException
	 */
	public function getPaymentMethodMeta( $payment_method = null );

	/**
	 * Get metadata for the specified payment submethod
	 *
	 * @param string|null $payment_submethod Defaults to the current submethod
	 * @return array
	 * @throws OutOfBoundsException
	 */
	public function getPaymentSubmethodMeta( $payment_submethod = null );

}
