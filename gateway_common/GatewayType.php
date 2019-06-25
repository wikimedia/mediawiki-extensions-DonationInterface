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
	 * Get a general purpose identifier for this processor, e.g. paypal
	 * @return string
	 */
	public static function getIdentifier();

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
	 * Perform any additional processing required when donor returns from
	 * payment processor site. Should set the final status.
	 * @param array $requestValues all GET and POST values from the request
	 * @return PaymentResult
	 */
	public function processDonorReturn( $requestValues );

	/**
	 * defineTransactions will define the $transactions array.
	 * The array will contain everything we need to know about the request structure for all the transactions we care about,
	 * for the current gateway.
	 * First array key: Some way for us to id the transaction. Doesn't actually have to be the gateway's name for it, but I'm going with that until I have a reason not to.
	 * Second array key:
	 * 		'request' contains the structure of that request. Leaves in the array tree will eventually be mapped to actual values of ours,
	 * 		according to the precedence established in the getTransactionSpecificValue function.
	 * 		'values' contains default values for the transaction. Things that are typically not overridden should go here.
	 * 		'check_required' should be set to true for transactions that require donor information,
	 * 		  like initial payment setup. TODO: different required fields per transaction
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
	 * Sets up the $data_transformers array.
	 */
	function defineDataTransformers();

	/**
	 * Sets up the $order_id_meta array.
	 * @todo Data Item Class. There should be a class that keeps track of
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
	 * 'alt_locations' => [ $dataset_name, $dataset_key ]
	 * 	** alt_locations is intended to contain a list of arrays that
	 * 	are always available (or should be), from which we can pull the
	 * 	order_id.
	 * 	** Examples of valid things to throw in $dataset_name are 'request'
	 * 	and 'session'
	 * 	** $dataset_key : The key in the associated dataset that is
	 * 	expected to contain the order_id. Probably going to be order_id
	 * 	if we are generating the dataset internally. Probably something
	 * 	else if a gateway is posting or getting back to us in a
	 * 	resultswitcher situation.
	 * 	** These should be expressed in $order_id_meta in order of
	 * 	preference / authority.
	 * 'generate' => boolean value. True if we will be generating our own
	 * 	order IDs, false if we are deferring order_id generation to the
	 * 	gateway.
	 * 'ct_id' => boolean value.  If True, when generating order ID use
	 * the contribution tracking ID with the sequence number appended
	 *
	 * Will eventually contain the following keys/values:
	 * 'final'=> The value that we have chosen as the valid order ID for
	 * 	this request.
	 * 'final_source' => Where we ultimately decided to grab the value we
	 * 	chose to stuff in 'final'.
	 */
	function defineOrderIDMeta();

	/**
	 * Called in the constructor, this function should be used to define
	 * pieces of default data particular to the gateway. It will be up to
	 * the child class to poke the data through to the data object
	 * (probably with $this->addRequestData()).
	 * DO NOT set default payment information here (or anywhere, really).
	 * That would be naughty.
	 * @param array $options associative array of values as given to the
	 *  GateWayType constructor.
	 */
	function setGatewayDefaults( $options = [] );

	/**
	 * @param array $options If given, try to filter supported currencies by:
	 *                       'country' ISO 3166 2 letter country code
	 *                       'payment_method'
	 *                       'payment_submethod'
	 * @return array of ISO 4217 currency codes supported by this adapter for
	 * the given options. If options are not given, the adapter may return
	 * all supported currencies of filter by the unstaged data.
	 */
	function getCurrencies( $options = [] );

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
	 * Data format for responses coming back from the processor, from
	 * getFormattedResponse.  Should be one of:
	 *   'xml': Parse XML to a DomDocument.
	 *   'json': Parse JSON into an array tree.
	 *   'delimited': Parse a character-delimited list into an array.
	 *   'query_string': Otherwise known as application/x-www-form-urlencoded.
	 *       Parse a query string and urldecode into a map array.
	 *
	 * @return string
	 */
	function getResponseType();

	/**
	 * This is the ONLY getData type function anything should be using
	 * outside the adapter.
	 * Short explanation of the data population up to now:
	 * 	*) When the gateway adapter is constructed, it constructs a DonationData
	 * 		object.
	 * 	*) On construction, the DonationData object pulls donation data from an
	 * 		appropriate source, and normalizes the entire data set for storage.
	 * 	*) The gateway adapter pulls normalized, html escaped data out of the
	 * 		DonationData object, as the base of its own data set.
	 * @param string $val The specific key you're looking for (if any)
	 * @return mixed An array of all the raw, unstaged (but normalized and
	 * sanitized) data sent to the adapter, or if $val was set, either the
	 * specific value held for $val, or null if none exists.
	 */
	public function getData_Unstaged_Escaped( $val = '' );

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
	 * Get the name of the currently selected payment submethod
	 *
	 * @return string
	 */
	public function getPaymentSubmethod();

	/**
	 * Get metadata for the specified payment submethod
	 *
	 * @param string|null $payment_submethod Defaults to the current submethod
	 * @return array
	 * @throws OutOfBoundsException
	 */
	public function getPaymentSubmethodMeta( $payment_submethod = null );

	/**
	 * Get the entire list of payment submethod definitions
	 *
	 * Not all payment submethods are available within an adapter
	 *
	 * @return array Returns the available payment submethods for the specific adapter
	 */
	public function getPaymentSubmethods();

	/**
	 * Get any known constraints on the field's value.
	 * @param string $field
	 * @return array The field's constraints, or an empty array if none are available.
	 */
	public function getDataConstraints( $field );

	/**
	 * Set the adapter's fraud score
	 *
	 * @param float $score
	 */
	public function setRiskScore( $score );

	/**
	 * Returns the current validation action.
	 * This will typically get set and altered by the fraud filters.
	 *
	 * @return string the current process action.
	 */
	public function getValidationAction();

	/**
	 * Returns the response object with the details of the latest
	 * transaction, or null if the adapter has not yet performed one.
	 *
	 * @return PaymentTransactionResponse|null
	 */
	public function getTransactionResponse();

	/**
	 * Sets the current validation action. This is meant to be used by the
	 * fraud filters, and as such, by default, only worse news than was already
	 * being stored will be retained for the final result.
	 * @param string $action the value you want to set as the action.
	 * @param bool $reset set to true to do a hard set on the action value.
	 * Otherwise, the status will only change if it fails harder than it already
	 * was.
	 * @throws UnexpectedValueException
	 */
	public function setValidationAction( $action, $reset = false );

	/**
	 * Lets the outside world (particularly filters that accumulate points scores)
	 * know if we are a batch processor.
	 * @return bool
	 */
	public function isBatchProcessor();

	/**
	 * Set a value used to determine whether data has changed
	 * @param string $hashval
	 */
	public function setHash( $hashval );

	/**
	 * Clear the data hash value
	 */
	public function unsetHash();

	/**
	 * session_ensure
	 * Ensure that we have a session set for the current user.
	 * If we do not have a session set for the current user,
	 * start the session.
	 */
	public function session_ensure();

	/**
	 * Gets the currently set transaction name. This value should only ever be
	 * set with setCurrentTransaction: A function that ensures the current
	 * transaction maps to a first-level key that is known to exist in the
	 * $transactions array, defined in the child gateway.
	 * @return string|false The name of the properly set transaction, or false if none
	 * has been set.
	 */
	public function getCurrentTransaction();

	/**
	 * For making freeform stomp messages.
	 * As these are all non-critical, we don't need to be as strict as we have been with the other stuff.
	 * But, we've got to have some standards.
	 * @param array $transaction The fields that we are interested in sending.
	 * @return array The fields that will actually be sent. So, $transaction ++ some other things we think we're likely to always need.
	 */
	public function addStandardMessageFields( $transaction );

	/**
	 * Returns information about how to manage the Order ID, either a specific
	 * value or the whole associative array.
	 * @param string|false $key The key to retrieve. Optional.
	 * @return mixed|false Data requested, or false if it is not set.
	 */
	function getOrderIDMeta( $key = false );

	/**
	 * Establish an 'edit' token to help prevent CSRF, etc.
	 *
	 * We use this in place of $wgUser->editToken() b/c currently
	 * $wgUser->editToken() is broken (apparently by design) for
	 * anonymous users.  Using $wgUser->editToken() currently exposes
	 * a security risk for non-authenticated users.  Until this is
	 * resolved in $wgUser, we'll use our own methods for token
	 * handling.
	 *
	 * Public so the api can get to it.
	 *
	 * @return string
	 */
	function token_getSaltedSessionToken();

	/**
	 * Get settings loaded from adapter's config directory
	 * @param string|null $key setting to retrieve, or null for all
	 * @return mixed the setting requested, or the config array
	 */
	public function getConfig( $key = null );

	/**
	 * Get globals-based configuration setting
	 *
	 * @param string $key setting to retrieve
	 * @return mixed the setting requested
	 */
	public function getAccountConfig( $key );

	/**
	 * Build the parameters sent with the next request.
	 *
	 * @return array Parameters as a map.
	 */
	public function buildRequestParams();

	/**
	 * Dump info about a transaction in logs and pending queues before
	 * sending the donor off to complete it.
	 */
	public function logPending();

	/**
	 * Returns true if we should try to ask one-time donors to upgrade
	 * their donation to a monthly donation. This is only possible with
	 * certain processors, and may depend on a variant parameter passed
	 * in on the query string.
	 *
	 * @return boolean
	 */
	public function showRecurringUpsell();
}
