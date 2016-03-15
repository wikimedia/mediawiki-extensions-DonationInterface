<?php

/**
 * GatewayType Interface
 *
 */
interface GatewayType {
	// all the particulars of the child classes. Aaaaall.

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
	 * Should be 'xml' // TODO: json
	 *
	 * @return string
	 */
	function getResponseType();
}
