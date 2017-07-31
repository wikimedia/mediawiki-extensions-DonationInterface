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

use ForceUTF8\Encoding;
use MediaWiki\Session\SessionManager;
use Psr\Log\LogLevel;
use SmashPig\Core\UtcDate;
use Symfony\Component\Yaml\Parser;

/**
 * GatewayAdapter
 *
 */
abstract class GatewayAdapter
	implements GatewayType,
		LogPrefixProvider
{
	/**
	 * Don't change these strings without fixing cross-repo usages.
	 */
	const REDIRECT_PREFACE = 'Redirecting for transaction: ';
	const COMPLETED_PREFACE = 'Completed donation: ';

	/**
	 * config tree
	 */
	protected $config = array();

	/**
	 * $dataConstraints provides information on how to handle variables.
	 *
	 * 	 <code>
	 * 		'account_holder'		=> array( 'type' => 'alphanumeric',		'length' => 50, )
	 * 	 </code>
	 *
	 * @var	array	$dataConstraints
	 */
	protected $dataConstraints = array();

	/**
	 * $error_map Reference map from gateway error to client error.
	 *
	 * The key of each error should map to a i18n message key or a callable
	 * By convention, the following three keys have these meanings:
	 *   'internal-0000' => 'message-key-1', // Failed failed pre-process checks.
	 *   'internal-0001' => 'message-key-2', // Transaction could not be processed due to an internal error.
	 *   'internal-0002' => 'message-key-3', // Communication failure
	 * A callable should return the translated error message.
	 * Any undefined key will map to 'donate_interface-processing-error'
	 *
	 * @var	array	$error_map
	 */
	protected $error_map = array();

	/**
	 * @see GlobalCollectAdapter::defineGoToThankYouOn()
	 *
	 * @var	array	$goToThankYouOn
	 */
	protected $goToThankYouOn = array();

	/**
	 * $var_map maps gateway variables to client variables
	 *
	 * @var	array	$var_map
	 */
	protected $var_map = array();

	protected $account_name;
	protected $account_config;
	protected $accountInfo;
	protected $transactions;

	/**
	 * $payment_methods will be defined by the adapter.
	 *
	 * @var	array	$payment_methods
	 */
	protected $payment_methods = array();

	/**
	 * $payment_submethods will be defined by the adapter.
	 *
	 * @var	array	$payment_submethods
	 */
	protected $payment_submethods = array();

	protected $return_value_map;
	protected $staged_data;
	protected $unstaged_data;

	/**
	 * Data transformation helpers.  These implement the StagingHelper interface for now,
	 * and are responsible for staging and unstaging data.
	 */
	protected $data_transformers = array();

	/**
	 * For gateways that speak XML, we use this variable to hold the document
	 * while we build the outgoing request.  TODO: move XML functions out of the
	 * main gateway classes.
	 * @var DomDocument
	 */
	protected $xmlDoc;

	/**
	 * @var DonationData
	 */
	protected $dataObj;

	/**
	 * Standard logger, logs to {type}_gateway
	 * @var \Psr\Log\LoggerInterface
	 */
	protected $logger;

	/**
	 * Logs to {type}_gateway_payment_init
	 * @var \Psr\Log\LoggerInterface
	 */
	protected $payment_init_logger;

	/**
	 * Times and logs various operations
	 * @var DonationProfiler
	 */
	protected $profiler;

	/**
	 * $transaction_response is the member var that keeps track of the results of
	 * the latest discrete transaction with the gateway.
	 * @var PaymentTransactionResponse
	 */
	protected $transaction_response;
	/**
	 * @var string When the smoke clears, this should be set to one of the
	 * constants defined in @see FinalStatus
	 */
	protected $final_status;
	/**
	 * @var ErrorState List of errors preventing this transaction from continuing.
	 */
	protected $errorState;

	/**
	 * Name of the current transaction.  Set via @see setCurrentTransaction
	 * @var string
	 */
	protected $current_transaction;
	protected $action;
	protected $risk_score = 0;
	public $debugarray;

	/**
	 * A boolean that will tell us if we've posted to ourselves. A little more telling than
	 * WebRequest->wasPosted(), as something else could have posted to us.
	 * @var boolean
	 */
	public $posted = false;
	protected $batch = false;

	// ALL OF THESE need to be redefined in the children. Much voodoo depends on the accuracy of these constants.
	const GATEWAY_NAME = 'Donation Gateway';
	const IDENTIFIER = 'donation';
	const GLOBAL_PREFIX = 'wgDonationGateway'; // ...for example.

	// This should be set to true for gateways that don't return the request in the response. @see buildLogXML()
	public $log_outbound = false;

	protected $order_id_candidates;
	protected $order_id_meta;

	/**
	 * Default response type to be the same as communication type.
	 * @return string
	 */
	public function getResponseType() {
		return $this->getCommunicationType();
	}

	/**
	 * Get @see GatewayAdapter::$goToThankYouOn
	 */
	public function getGoToThankYouOn() {

		return $this->goToThankYouOn;
	}

	/**
	 * Constructor
	 *
	 * @param array	$options
	 *   OPTIONAL - You may set options for testing
	 *   - external_data - array, data from unusual sources (such as test fixture)
	 * @see DonationData
	 */
	public function __construct( $options = array() ) {

		$defaults = array(
			'external_data' => null,
		);
		$options = array_merge( $defaults, $options );
		if ( array_key_exists( 'batch_mode', $options ) ) {
			$this->batch = $options['batch_mode'];
			unset( $options['batch_mode'] );
		}
		$this->errorState = new ErrorState();
		$this->logger = DonationLoggerFactory::getLogger( $this );
		$this->payment_init_logger = DonationLoggerFactory::getLogger( $this, '_payment_init' );

		$this->profiler = DonationLoggerFactory::getProfiler( $this );

		$this->logger->info( "Creating a new adapter of type: [{$this->getGatewayName()}]" );

		// The following needs to be set up before we initialize DonationData.
		// TODO: move the rest of the initialization here
		$this->loadConfig();
		$this->defineOrderIDMeta();
		$this->defineDataConstraints();
		$this->definePaymentMethods();

		$this->defineDataTransformers();

		$this->session_resetOnSwitch(); // Need to do this before creating DonationData

		// FIXME: this should not have side effects like setting order_id_meta['final']
		// TODO: On second thought, neither set data nor validate in this constructor.
		$this->dataObj = new DonationData( $this, $options['external_data'] );

		$this->unstaged_data = $this->dataObj->getData();
		$this->staged_data = $this->unstaged_data;

		// checking to see if we have an edit token in the request...
		$this->posted = ( $this->dataObj->wasPosted() && ( !is_null( WmfFramework::getRequestValue( 'wmf_token', null ) ) ) );

		$this->findAccount();
		$this->defineAccountInfo();
		$this->defineTransactions();
		$this->defineErrorMap();
		$this->defineVarMap();
		$this->defineReturnValueMap();

		$this->setGatewayDefaults( $options );

		// FIXME: Same as above, don't validate or stage in the constructor.
		$this->validate();

		$this->stageData();

		BannerHistoryLogIdProcessor::onGatewayReady( $this );
		Gateway_Extras_CustomFilters::onGatewayReady( $this );

		if ( $this->getValidationAction() !== 'process' ) {
			$this->finalizeInternalStatus( FinalStatus::FAILED );
			$this->errorState->addError( new PaymentError(
				'internal-0001',
				'Failed initial filters',
				LogLevel::INFO
			) );
		}
	}

	/**
	 * Get the directory for processor-specific classes and configuration
	 * @return string
	 */
	abstract protected function getBasedir();

	public function loadConfig() {
		$yaml = new Parser();
		foreach ( glob( $this->getBasedir() . "/config/*.yaml" ) as $path ) {
			$pieces = explode( "/", $path );
			$key = substr( array_pop( $pieces ), 0, -5 );
			$this->config[$key] = $yaml->parse( file_get_contents( $path ) );
		}
	}

	public function getConfig( $key = null ) {
		if ( $key === null ) {
			return $this->config;
		}
		if ( array_key_exists( $key, $this->config ) ) {
			return $this->config[$key];
		}
		return null;
	}

	/**
	 * Return the base URL to use for the current transaction.
	 *
	 * Some adapters will append a path component and query parameters.
	 * That variation should be handled by the request controller.  Customize
	 * things like varying server endpoints by overriding this function.
	 */
	protected function getProcessorUrl() {
		if ( !self::getGlobal( 'Test' ) ) {
			$url = self::getGlobal( 'URL' );
		} else {
			$url = self::getGlobal( 'TestingURL' );
		}
		return $url;
	}

	// For legacy support.
	// TODO replace with access to config structure
	public function definePaymentMethods() {
		// All adapters have payment_method(s)
		$this->payment_methods = $this->config['payment_methods'];
		// Some (Pay Pal) do not have any submethods.
		if ( isset( $this->config['payment_submethods'] ) ) {
			$this->payment_submethods = $this->config['payment_submethods'];
		}
	}

	// TODO: see comment on definePaymentMethods
	public function defineVarMap() {
		if ( isset( $this->config['var_map'] ) ) {
			$this->var_map = $this->config['var_map'];
		}
	}

	// TODO: see comment on definePaymentMethods
	public function defineDataConstraints() {
		if ( isset( $this->config['data_constraints'] ) ) {
			$this->dataConstraints = $this->config['data_constraints'];
		}
	}

	// TODO: see comment on definePaymentMethods
	public function defineErrorMap() {
		if ( isset( $this->config['error_map'] ) ) {
			$this->error_map = $this->config['error_map'];
		}
	}

	public function defineDataTransformers() {
		if ( empty( $this->config['transformers'] ) ) {
			return;
		}

		foreach ( $this->config['transformers'] as $className ) {
			// TODO: Pass $this to the constructor so we can take gateway out
			// of the interfaces.
			$this->data_transformers[] = new $className();
		}
	}

	// FIXME: Not convinced we need this.
	public function getDataTransformers() {
		return $this->data_transformers;
	}

	/**
	 * Determine which account to use for this session
	 */
	protected function findAccount() {
		$acctConfig = self::getGlobal( 'AccountInfo' );

		// this is causing warns in Special:SpecialPages
		if ( !$acctConfig ) {
			return;
		}

		//TODO crazy logic to determine which account we want
		$accounts = array_keys( $acctConfig );
		$this->account_name = array_shift( $accounts );

		$this->account_config = $acctConfig[ $this->account_name ];
	}

	/**
	 * Get the log message prefix:
	 * $contribution_tracking_id . ':' . $order_id . ' '
	 *
	 * Now, going to the DonationData object to handle this, because it will
	 * always have less stale data (and we need messages to come out of
	 * there before data exists here)
	 *
	 * @return string
	 */
	public function getLogMessagePrefix() {
		if ( !is_object( $this->dataObj ) ) {
			// please avoid exploding; It's just a log line.
			return 'Constructing! ';
		}
		return $this->dataObj->getLogMessagePrefix();
	}

	/**
	 * Checks the edit tokens in the user's session against the one gathered
	 * from populated form data.
	 * Adds a string to the debugarray, to make it a little easier to tell what
	 * happened if we turn the debug results on.
	 * Only called from the .body pages
	 * @return boolean true if match, else false.
	 */
	public function checkTokens() {
		$checkResult = $this->token_checkTokens();

		if ( $checkResult ) {
			$this->debugarray[] = 'Token Match';
		} else {
			$this->debugarray[] = 'Token MISMATCH';
		}

		$this->refreshGatewayValueFromSource( 'wmf_token' );
		return $checkResult;
	}

	/**
	 * Returns staged data from the adapter object, or null if a key was
	 * specified and no value exsits.
	 * @param string $val An optional specific key you want returned.
	 * @return mixed All the staged data held by the adapter, or if a key was
	 * set, the staged value for that key.
	 */
	protected function getData_Staged( $val = '' ) {
		if ( $val === '' ) {
			return $this->staged_data;
		} else {
			if ( array_key_exists( $val, $this->staged_data ) ) {
				return $this->staged_data[$val];
			} else {
				return null;
			}
		}
	}

	public function getCoreDataTransformers() {
		return array(
			// Always stage email address first, to set default if missing
			new DonorEmail(),
			new DonorFullName(),
			new CountryValidation(),
			new Amount(),
			new AmountInCents(),
			new StreetAddress(),
		);
	}

	/**
	 * A helper function to let us stash extra data after the form has been submitted.
	 *
	 * @param array  $dataArray An associative array of data.
	 */
	public function addRequestData( $dataArray ) {
		$this->dataObj->addData( $dataArray );

		$calculated_fields = $this->dataObj->getCalculatedFields();
		$data_fields = array_keys( $dataArray );
		$data_fields = array_merge( $data_fields, $calculated_fields );

		foreach ( $data_fields as $value ) {
			$this->refreshGatewayValueFromSource( $value );
		}

		// Always restage after changing unstaged_data
		$this->stageData();
	}

	/**
	 * Add data from the processor to staged_data and run any unstaging functions.
	 *
	 * @param array $dataArray An associative array of data, with normalized
	 * keys and raw processor values.
	 */
	public function addResponseData( $dataArray ) {
		foreach ( $dataArray as $key => $value ) {
			$this->staged_data[$key] = $value;
		}

		$this->unstageData();

		// Only copy the affected values back into the normalized data.
		$newlyUnstagedData = array();
		foreach ( $dataArray as $key => $stagedValue ) {
			if ( array_key_exists( $key, $this->unstaged_data ) ) {
				$newlyUnstagedData[$key] = $this->unstaged_data[$key];
			}
		}
		$this->logger->debug( "Adding response data: " . json_encode( $newlyUnstagedData ) );
		$this->dataObj->addData( $newlyUnstagedData );
	}

	/**
	 * Change the keys on this data from processor API names to normalized names.
	 *
	 * @param array $processor_data Response data with raw API keys
	 * @param array $key_map map processor keys to our keys, defaults to
	 *                       $this->var_map
	 * @return array data with normalized keys
	 *
	 * TODO: Figure out why this isn't the default behavior in addResponseData.
	 * Once that's resolved, we might operate on member fields rather than do
	 * this as a function.
	 */
	public function unstageKeys( $processor_data, $key_map = null ) {
		if ( $key_map === null ) {
			$key_map = $this->var_map;
		}

		$staged_data = array();
		foreach ( $key_map as $their_key => $our_key ) {
			if ( isset( $processor_data[$their_key] ) ) {
				$staged_data[$our_key] = $processor_data[$their_key];
			} else {
				// TODO: do any callers care? $emptyVars[] = $their_key;
			}
		}
		return $staged_data;
	}

	public function getData_Unstaged_Escaped( $val = '' ) {
		if ( $val === '' ) {
			return $this->unstaged_data;
		} else {
			if ( array_key_exists( $val, $this->unstaged_data ) ) {
				return $this->unstaged_data[$val];
			} else {
				return null;
			}
		}
	}

	static function getGlobal( $varname ) {
		// adding another layer of depth here, in case you're working with two gateways in the same request.
		// That does, in fact, ruin everything. :/
		$globalname = self::getGlobalPrefix() . $varname;
		// @codingStandardsIgnoreStart
		global $$globalname;
		if ( !isset( $$globalname ) ) {
			$globalname = "wgDonationInterface" . $varname;
			global $$globalname; // set or not. This is fine.
		}
		return $$globalname;
		// @codingStandardsIgnoreEnd
	}

	/**
	 * Gets a global variable according to @see getGlobal rules, then replaces
	 * $country and $language with values from gateway instance data.
	 * @param string $varname
	 */
	public function localizeGlobal( $varname ) {
		$value = self::getGlobal( $varname );
		$language = $this->getData_Unstaged_Escaped( 'language' );
		$country = $this->getData_Unstaged_Escaped( 'country' );
		$value = str_replace( '$language', $language, $value );
		$value = str_replace( '$country', $country, $value );
		return $value;
	}

	/**
	 * getErrorMap
	 *
	 * This will also return an error message if a $code is passed.
	 *
	 * If the error code does not exist, the default message will be returned.
	 *
	 * A default message should always exist with an index of 0.
	 *
	 * NOTE: This method will check to see if the message exists in translation
	 * and use that message instead of the default. This would override error_map.
	 *
	 * @param    string    $code    The error code to look up in the map
	 * @param    array     $options
	 * @return   array|string    Returns @see GatewayAdapter::$error_map
	 */
	public function getErrorMap( $code, $options = array() ) {

		$defaults = array(
			'translate' => false,
		);
		$options = array_merge( $defaults, $options );

		$response_message = $this->getIdentifier() . '_gateway-response-' . $code;

		$translatedMessage = '';
		if ( WmfFramework::messageExists( $response_message ) ) {
			$translatedMessage = WmfFramework::formatMessage( $response_message );
		}

		if ( isset( $this->error_map[ $code ] ) ) {
			$mapped = $this->error_map[ $code ];
			// Errors with complicated formatting can map to a function
			if ( is_callable( $mapped ) ) {
				return $mapped();
			}
			$messageKey = $mapped;
		} else {
			// If the $code does not exist, use the default message
			$messageKey = 'donate_interface-processing-error';
		}

		$translatedMessage = ( $options['translate'] && empty( $translatedMessage ) )
			? WmfFramework::formatMessage( $messageKey )
			: $translatedMessage;

		// Check to see if we return the translated message.
		$message = ( $options['translate'] ) ? $translatedMessage : $messageKey;

		return $message;
	}

	/**
	 * getErrorMapByCodeAndTranslate
	 *
	 * This will take an error code and translate the message.
	 *
	 * @param	string	$code	The error code to look up in the map
	 *
	 * @return	string	Returns the translated message from @see GatewayAdapter::$error_map
	 */
	public function getErrorMapByCodeAndTranslate( $code ) {

		return $this->getErrorMap( $code, array( 'translate' => true, ) );
	}

	/**
	 * This function is used exclusively by the two functions that build
	 * requests to be sent directly to external payment gateway servers. Those
	 * two functions are buildRequestNameValueString, and (perhaps less
	 * obviously) buildRequestXML. As such, unless a valid current transaction
	 * has already been set, this will error out rather hard.
	 * In other words: In all likelihood, this is not the function you're
	 * looking for.
	 * @param string $gateway_field_name The GATEWAY's field name that we are
	 * hoping to populate. Probably not even remotely the way we name the same
	 * data internally.
	 * @param boolean $token This is a throwback to a road we nearly went down,
	 * with ajax and client-side token replacement. The idea was, if this was
	 * set to true, we would simply pass the fully-formed transaction structure
	 * with our tokenized var names in the spots where form values would usually
	 * go, so we could fetch the structure and have some client-side voodoo
	 * populate the transaction so we wouldn't have to touch the data at all.
	 * At this point, very likely cruft that can be removed, but as I'm not 100%
	 * on that point, I'm keeping it for now. If we do kill off this param, we
	 * should also get rid of the function buildTransactionFormat and anything
	 * that calls it.
	 * @throws LogicException
	 * @return mixed The value we want to send directly to the gateway, for the
	 * specified gateway field name.
	 */
	public function getTransactionSpecificValue( $gateway_field_name, $token = false ) {
		if ( empty( $this->transactions ) ) {
			$msg = self::getGatewayName() . ': Transactions structure is empty! No transaction can be constructed.';
			$this->logger->critical( $msg );
			throw new LogicException( $msg );
		}
		// Ensures we are using the correct transaction structure for our various lookups.
		$transaction = $this->getCurrentTransaction();

		if ( !$transaction ){
			return null;
		}

		// If there's a hard-coded value in the transaction definition, use that.
		if ( !empty( $transaction ) ) {
			if ( array_key_exists( $transaction, $this->transactions ) && is_array( $this->transactions[$transaction] ) &&
				array_key_exists( 'values', $this->transactions[$transaction] ) &&
				array_key_exists( $gateway_field_name, $this->transactions[$transaction]['values'] ) ) {
				return $this->transactions[$transaction]['values'][$gateway_field_name];
			}
		}

		// if it's account info, use that.
		// $this->accountInfo;
		if ( array_key_exists( $gateway_field_name, $this->accountInfo ) ) {
			return $this->accountInfo[$gateway_field_name];
		}

		// If there's a value in the post data (name-translated by the var_map), use that.
		if ( array_key_exists( $gateway_field_name, $this->var_map ) ) {
			if ( $token === true ) { // we just want the field name to use, so short-circuit all that mess.
				return '@' . $this->var_map[$gateway_field_name];
			}
			$staged = $this->getData_Staged( $this->var_map[$gateway_field_name] );
			if ( !is_null( $staged ) ) {
				// if it was sent, use that.
				return $staged;
			} else {
				// return blank string
				return '';
			}
		}

		// not in the map, or hard coded. What then?
		// Complain furiously, for your code is faulty.
		$msg = self::getGatewayName() . ': Requested value ' . $gateway_field_name . ' cannot be found in the transactions structure.';
		$this->logger->critical( $msg );
		throw new LogicException( $msg );
	}

	/**
	 * Returns the current transaction request structure if it exists, otherwise
	 * returns false.
	 * Fails nicely if the current transaction is simply not set yet.
	 * @throws LogicException if the transaction is set, but no structure is defined.
	 * @return mixed current transaction's structure as an array, or false
	 */
	protected function getTransactionRequestStructure() {
		$transaction = $this->getCurrentTransaction();
		if ( !$transaction ) {
			return false;
		}

		if ( empty( $this->transactions ) ||
			!array_key_exists( $transaction, $this->transactions ) ||
			!array_key_exists( 'request', $this->transactions[$transaction] )
		) {

			$msg = self::getGatewayName() . ": $transaction request structure is empty! No transaction can be constructed.";
			$this->logger->critical( $msg );
			throw new LogicException( $msg );
		}

		return $this->transactions[$transaction]['request'];
	}

	/**
	 * Builds a set of transaction data in name/value format
	 *		*)The current transaction must be set before you call this function.
	 *		*)Uses getTransactionSpecificValue to assign staged values to the
	 * fields required by the gateway. Look there for more insight into the
	 * heirarchy of all possible data sources.
	 * @return string The raw transaction in name/value format, ready to be
	 * curl'd off to the remote server.
	 */
	protected function buildRequestNameValueString() {
		$data = $this->buildRequestArray();
		$ret = http_build_query( $data );
		return $ret;
	}

	protected function buildRequestArray() {
		// Look up the request structure for our current transaction type in the transactions array
		$structure = $this->getTransactionRequestStructure();
		if ( !is_array( $structure ) ) {
			return array();
		}
		$callback = array( $this, 'getTransactionSpecificValue' );
		return ArrayHelper::buildRequestArray( $callback, $structure );
	}

	/**
	 * Builds a set of transaction data in XML format
	 *		*)The current transaction must be set before you call this function.
	 *		*)(eventually) uses getTransactionSpecificValue to assign staged
	 * values to the fields required by the gateway. Look there for more insight
	 * into the heirarchy of all possible data sources.
	 * @return string The raw transaction in xml format, ready to be
	 * curl'd off to the remote server.
	 */
	protected function buildRequestXML( $rootElement = 'XML', $encoding = 'UTF-8' ) {
		$this->xmlDoc = new DomDocument( '1.0', $encoding );
		$node = $this->xmlDoc->createElement( $rootElement );

		// Look up the request structure for our current transaction type in the transactions array
		$structure = $this->getTransactionRequestStructure();
		if ( !is_array( $structure ) ) {
			return '';
		}

		$this->buildTransactionNodes( $structure, $node );
		$this->xmlDoc->appendChild( $node );
		$return = $this->xmlDoc->saveXML();

		if ( $this->log_outbound ) {
			$message = "Request XML: ";
			$full_structure = $this->transactions[$this->getCurrentTransaction()]; // if we've gotten this far, this exists.
			if ( array_key_exists( 'never_log', $full_structure ) ) { // Danger Zone!
				$message = "Cleaned $message";
				// keep these totally separate. Do not want to risk sensitive information (like cvv) making it anywhere near the log.
				$this->xmlDoc = new DomDocument( '1.0' );
				$log_node = $this->xmlDoc->createElement( $rootElement );
				// remove all never_log nodes from the structure
				$log_structure = $this->cleanTransactionStructureForLogs( $structure, $full_structure['never_log'] );
				$this->buildTransactionNodes( $log_structure, $log_node );
				$this->xmlDoc->appendChild( $log_node );
				$logme = $this->xmlDoc->saveXML();
			} else {
				// ...safe zone.
				$logme = $return;
			}
			$this->logger->info( $message . $logme );
		}


		return $return;
	}

	/**
	 * buildRequestXML helper function.
	 * Builds the XML transaction by recursively crawling the transaction
	 * structure and adding populated nodes by reference.
	 * @param array $structure Current transaction's more leafward structure,
	 * from the point of view of the current XML node.
	 * @param DOMElement $node The current XML node.
	 * @param bool $js More likely cruft relating back to buildTransactionFormat
	 */
	protected function buildTransactionNodes( $structure, &$node, $js = false ) {

		if ( !is_array( $structure ) ) {
			// this is a weird case that shouldn't ever happen. I'm just being... thorough. But, yeah: It's like... the base-1 case.
			$this->appendNodeIfValue( $structure, $node, $js );
		} else {
			foreach ( $structure as $key => $value ) {
				if ( !is_array( $value ) ) {
					//do not use $key, it's the numeric index here and $value is the field name
					// FIXME: make tree traversal more readable.
					$this->appendNodeIfValue( $value, $node, $js );
				} else {
					// Recurse for child
					$keynode = $this->xmlDoc->createElement( $key );
					$this->buildTransactionNodes( $value, $keynode, $js );
					$node->appendChild( $keynode );
				}
			}
		}
		//not actually returning anything. It's all side-effects. Because I suck like that.
	}

	/**
	 * Recursively sink through a transaction structure array to remove all
	 * nodes that we can't have showing up in the server logs.
	 * Mostly for CVV: If we log those, we are all fired.
	 * @param array $structure The transaction structure that we want to clean.
	 * @param array $never_log An array of values we should never log. These values should be the gateway's transaciton nodes, rather than our normal values.
	 * @return array $structure stripped of all references to the values in $never_log
	 */
	protected function cleanTransactionStructureForLogs( $structure, $never_log ) {
		foreach ( $structure as $node => $value ) {
			if ( is_array( $value ) ) {
				$structure[$node] = $this->cleanTransactionStructureForLogs( $value, $never_log );
			} else {
				if ( in_array( $value, $never_log ) ) {
					unset( $structure[$node] );
				}
			}
		}
		return $structure;
	}

	/**
	 * appendNodeIfValue is a helper function for buildTransactionNodes, which
	 * is used by buildRequestXML to construct an XML transaction.
	 * This function will append an XML node to the transaction being built via
	 * the passed-in parent node, only if the current node would have a
	 * non-empty value.
	 * @param string $value The GATEWAY's field name for the current node.
	 * @param DOMElement $node The parent node this node will be contained in, if it
	 *  is determined to have a non-empty value.
	 * @param bool $js Probably cruft at this point. This is connected to the
	 * function buildTransactionFormat.
	 */
	protected function appendNodeIfValue( $value, &$node, $js = false ) {
		$nodevalue = $this->getTransactionSpecificValue( $value, $js );
		if ( $nodevalue !== '' && $nodevalue !== false ) {
			$temp = $this->xmlDoc->createElement( $value );

			$data = null;
			$data = $this->xmlDoc->createTextNode( $nodevalue );

			$temp->appendChild( $data );
			$node->appendChild( $temp );
		}
	}

	/**
	 * Performs a transaction through the gateway. Optionally may reattempt the transaction if
	 * a recoverable gateway error occurred.
	 *
	 * This function provides all functionality to the external world to communicate with a
	 * properly constructed gateway and handle all the return data in an appropriate manner.
	 * -- Appropriateness is determined by the requested $transaction structure and definition.
	 *
	 * @param string $transaction    The specific transaction type, like 'INSERT_ORDERWITHPAYMENT',
	 *  that maps to a first-level key in the $transactions array.
	 *
	 * @return PaymentTransactionResponse
	 */
	public function do_transaction( $transaction ) {
		$this->session_addDonorData();
		$this->setCurrentTransaction( $transaction );
		$this->validate();
		if ( !$this->validatedOK() ){
			//If the data didn't validate okay, prevent all data transmissions.
			$response = $this->getFailedValidationResponse();
			// TODO: should we set $this->transaction_response ?
			$this->logger->info( "Failed Validation. Aborting $transaction " . print_r( $this->errorState, true ) );
			return $response;
		}

		$retryCount = 0;
		$loopCount = $this->getGlobal( 'RetryLoopCount' );

		do {
			$retryVars = null;
			$retval = $this->do_transaction_internal( $transaction, $retryVars );

			if ( !empty( $retryVars ) ) {
				// TODO: Add more intelligence here. Right now timeout is the only one specifically
				// handled, all other cases we just assume it's the order_id
				// and that it is totally OK to just reset it and reroll.
				// FIXME: Only a single value ever gets added to $retryVars before it gets wiped out
				// for the next attempt.  Decide if we really need an array and redo this switch
				// statement accordingly.
				switch( $retryVars[0] ) {
					case 'timeout' :
						// Just retry without changing anything.
						$this->logger->info( "Repeating transaction for timeout" );
						break;

					default :
						$this->logger->info( "Repeating transaction on request for vars: " . implode( ',', $retryVars ) );

							// Force regen of the order_id
						$this->regenerateOrderID();

							// Pull anything changed from dataObj
						$this->unstaged_data = $this->dataObj->getData();
						$this->staged_data = $this->unstaged_data;
						$this->stageData();
						break;
				}
			}

		} while ( ( !empty( $retryVars ) ) && ( ++$retryCount < $loopCount ) );

		if ( $retryCount >= $loopCount ) {
			$this->logger->error( "Transaction canceled after $retryCount retries." );
		}

		return $retval;
	}

	/**
	 * Called from do_transaction() in order to be able to deal with transactions that had
	 * recoverable errors but that do require the entire transaction to be repeated.
	 *
	 * This function has the following extension hooks:
	 *  * pre_process_<strtolower($transaction)>
	 *    Called before the transaction is processed; intended to call setValidationAction()
	 *    if the transaction should not be performed. Anti-fraud can be performed in this
	 *    hook by calling $this->runAntifraudFilters().
	 *
	 *  * post_process_<strtolower($transaction)>
	 *
	 * @param string    $transaction Name of the transaction being performed
	 * @param &string() $retryVars Reference to an array of variables that caused the
	 *                  transaction to fail.
	 *
	 * @return PaymentTransactionResponse
	 * @throws UnexpectedValueException
	 */
	final private function do_transaction_internal( $transaction, &$retryVars = null ) {
		$this->debugarray[] = __FUNCTION__ . " is doing a $transaction.";

		//reset, in case this isn't our first time.
		$this->transaction_response = new PaymentTransactionResponse();
		$this->final_status = false;
		$this->setValidationAction( 'process', true );
		$errCode = null;

		/* --- Build the transaction string for cURL --- */
		try {

			$this->executeIfFunctionExists( 'pre_process_' . $transaction );
			if ( $this->getValidationAction() != 'process' ) {
				$this->logger->info( "Failed pre-process checks for transaction type $transaction." );
				$this->transaction_response->setCommunicationStatus( false );
				$this->transaction_response->setMessage( $this->getErrorMapByCodeAndTranslate( 'internal-0000' ) );
				$this->transaction_response->addError(
					new PaymentError(
						'internal-0000',
						"Failed pre-process checks for transaction type $transaction.",
						LogLevel::INFO
					)
				);
				return $this->transaction_response;
			}

			if ( !$this->isBatchProcessor() ) {
				// TODO: Maybe move this to the pre_process functions?
				$this->dataObj->saveContributionTrackingData();
			}
			$commType = $this->getCommunicationType();
			switch( $commType ) {
				case 'redirect':

					//in the event that we have a redirect transaction that never displays the form,
					//save this most recent one before we leave.
					$this->session_pushFormName( $this->getData_Unstaged_Escaped( 'ffname' ) );

					$this->transaction_response->setCommunicationStatus( true );

					// Build the redirect URL.
					$redirectUrl = $this->getProcessorUrl();
					$redirectParams = $this->buildRequestParams();
					if ( $redirectParams ) {
						// Add GET parameters, if provided.
						$redirectUrl .= '?' . http_build_query( $redirectParams );
					}

					$this->transaction_response->setRedirect( $redirectUrl );

					return $this->transaction_response;

				case 'xml':
					$this->profiler->getStopwatch( "buildRequestXML", true ); // begin profiling
					$curlme = $this->buildRequestXML(); // build the XML
					$this->profiler->saveCommunicationStats( "buildRequestXML", $transaction ); // save profiling data
					break;
				case 'namevalue':
					$this->profiler->getStopwatch( "buildRequestNameValueString", true ); // begin profiling
					$curlme = $this->buildRequestNameValueString(); // build the name/value pairs
					$this->profiler->saveCommunicationStats( "buildRequestNameValueString", $transaction );
					break;
				case 'array':
					$this->profiler->getStopwatch( "buildRequestNameValueString", true ); // begin profiling
					$curlme = $this->buildRequestArray(); // build the name/value pairs
					$this->profiler->saveCommunicationStats( "buildRequestNameValueString", $transaction );
					break;
				default:
					throw new UnexpectedValueException( "Communication type of '{$commType}' unknown" );
			}
		} catch ( Exception $e ) {
			$this->logger->critical( 'Malformed gateway definition. Cannot continue: Aborting.\n' . $e->getMessage() );

			$this->transaction_response->setCommunicationStatus( false );
			$this->transaction_response->setMessage( $this->getErrorMapByCodeAndTranslate( 'internal-0001' ) );
			$this->transaction_response->addError(
				new PaymentError(
					'internal-0001',
					'Malformed gateway definition. Cannot continue: Aborting.\n' . $e->getMessage(),
					LogLevel::CRITICAL
				)
			);

			return $this->transaction_response;
		}

		/* --- Do the cURL request --- */
		$this->profiler->getStopwatch( __FUNCTION__, true );
		$txn_ok = $this->curl_transaction( $curlme );
		if ( $txn_ok === true ) { // We have something to slice and dice.
			$this->logger->info( "RETURNED FROM CURL:" . print_r( $this->transaction_response->getRawResponse(), true ) );

			// Decode the response according to $this->getResponseType
			$formatted = $this->getFormattedResponse( $this->transaction_response->getRawResponse() );

			// Process the formatted response. This will then drive the result action
			try {
				$this->processResponse( $formatted );
			} catch ( ResponseProcessingException $ex ) {
				// TODO: Should we integrate ResponseProcessingException with PaymentError?
				$errCode = $ex->getErrorCode();
				$retryVars = $ex->getRetryVars();
				$this->transaction_response->addError(
					new PaymentError(
						$errCode,
						$ex->getMessage(),
						LogLevel::ERROR
					)
				);
			}

		} elseif ( $txn_ok === false ) { // nothing to process, so we have to build it manually
			$logMessage = 'Transaction Communication failed' . print_r( $this->transaction_response, true );
			$this->logger->error( $logMessage );

			$this->transaction_response->setCommunicationStatus( false );
			$this->transaction_response->setMessage( $this->getErrorMapByCodeAndTranslate( 'internal-0002' ) );
			$this->transaction_response->addError(
				new PaymentError(
					'internal-0002',
					$logMessage,
					LogLevel::ERROR
				)
			);
		}

		// Log out how much time it took for the cURL request
		$this->profiler->saveCommunicationStats( __FUNCTION__, $transaction );

		if ( !empty( $retryVars ) ) {
			$this->logger->critical( "$transaction Communication failed (errcode $errCode), will reattempt!" );

			// Set this by key so that the result object still has all the cURL data
			$this->transaction_response->setCommunicationStatus( false );
			$this->transaction_response->setMessage( $this->getErrorMapByCodeAndTranslate( $errCode ) );
			$this->transaction_response->addError(
				new PaymentError(
					$errCode,
					"$transaction Communication failed (errcode $errCode), will reattempt!",
					LogLevel::CRITICAL
				)
			);
		}

		//if we have set errors by this point, the transaction is not okay
		$errors = $this->transaction_response->getErrors();
		if ( !empty( $errors ) ) {
			$txn_ok = false;
			$this->errorState->addErrors( $errors );
		}
		// If we have any special post-process instructions for this
		// transaction, do 'em.
		// NOTE: If you want your transaction to fire off the post-process
		// logic, you need to run $this->postProcessDonation in a function
		// called
		//	'post_process' . strtolower($transaction)
		// in the appropriate gateway object.
		if ( $txn_ok && empty( $retryVars ) ) {
			$this->executeIfFunctionExists( 'post_process_' . $transaction );
			if ( $this->getValidationAction() != 'process' ) {
				$this->logger->info( "Failed post-process checks for transaction type $transaction." );
				$this->transaction_response->setCommunicationStatus( false );
				$this->transaction_response->setMessage( $this->getErrorMapByCodeAndTranslate( 'internal-0000' ) );
				$this->transaction_response->addError(
					new PaymentError(
						'internal-0000',
						"Failed post-process checks for transaction type $transaction.",
						LogLevel::INFO
					)
				);
				return $this->transaction_response;
			}
		}

		// log that the transaction is essentially complete
		$this->logger->info( 'Transaction complete.' );

		if ( !$this->isBatchProcessor() ) {
			$this->debugarray[] = 'numAttempt = ' . $this->session_getData( 'numAttempt' );
		}

		return $this->transaction_response;
	}

	function getCurlBaseOpts() {
		//I chose to return this as a function so it's easy to override.
		//TODO: probably this for all the junk I currently have stashed in the constructor.
		//...maybe.

		$path = $this->transaction_option( 'path' );
		if ( !$path ) {
			$path = '';
		}
		$opts = array(
			CURLOPT_URL => $this->getProcessorUrl() . $path,
			CURLOPT_USERAGENT => WmfFramework::getUserAgent(),
			CURLOPT_HEADER => 1,
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_TIMEOUT => self::getGlobal( 'Timeout' ),
			CURLOPT_FOLLOWLOCATION => 0,
			CURLOPT_SSL_VERIFYPEER => 1,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_FORBID_REUSE => true,
			CURLOPT_POST => 1,
			CURLOPT_VERBOSE => true
		);

		return $opts;
	}

	function getCurlBaseHeaders() {
		$content_type = 'application/x-www-form-urlencoded';
		if ( $this->getCommunicationType() === 'xml' ) {
			$content_type = 'text/xml';
		}
		$headers = array(
			'Content-Type: ' . $content_type . '; charset=utf-8',
			'X-VPS-Client-Timeout: 45',
			'X-VPS-Request-ID:' . $this->getData_Staged( 'order_id' ),
		);
		return $headers;
	}

	/**
	 * Sets the transaction you are about to send to the payment gateway. This
	 * will throw an exception if you try to set it to something that has no
	 * transaction definition.
	 * @param string $transaction_name This is a specific transaction type like
	 * 'INSERT_ORDERWITHPAYMENT' (if you're GlobalCollect) that maps to a
	 * first-level key in the $transactions array.
	 * @throws UnexpectedValueException
	 */
	public function setCurrentTransaction( $transaction_name ) {
		if ( empty( $this->transactions ) ||
			!is_array( $this->transactions ) ||
			!array_key_exists( $transaction_name, $this->transactions )
		) {
			$msg = self::getGatewayName() . ': Transaction Name "' . $transaction_name . '" undefined for this gateway.';
			$this->logger->alert( $msg );
			throw new UnexpectedValueException( $msg );
		} else {
			$this->current_transaction = $transaction_name;
		}
	}

	public function getCurrentTransaction() {
		if ( is_null( $this->current_transaction ) ) {
			return false;
		} else {
			return $this->current_transaction;
		}
	}

	/**
	 * Get the payment method
	 *
	 * @return	string
	 */
	public function getPaymentMethod() {
		// FIXME: this should return the final calculated method
		return $this->getData_Unstaged_Escaped( 'payment_method' );
	}

	/**
	 * Define payment methods
	 *
	 * Not all payment methods are available within an adapter
	 *
	 * @return	array	Returns the available payment methods for the specific adapter
	 */
	public function getPaymentMethods() {
		return $this->payment_methods;
	}

	public function getPaymentSubmethod() {
		return $this->getData_Unstaged_Escaped( 'payment_submethod' );
	}

	public function getPaymentSubmethods() {
		return $this->payment_submethods;
	}

	function setGatewayDefaults( $options = array ( ) ) {}

	public function getCurrencies( $options = array() ) {
		return $this->config['currencies'];
	}

	/**
	 * Sends a curl request to the gateway server, and gets a response.
	 * Saves that response to the transaction_response's rawResponse;
	 * @param string $data the raw data we want to curl up to a server somewhere.
	 * Should have been constructed with either buildRequestNameValueString, or
	 * buildRequestXML.
	 * @return boolean true if the communication was successful and there is a
	 * parseable response, false if there was a fundamental communication
	 * problem. (timeout, bad URL, etc.)
	 */
	protected function curl_transaction( $data ) {
		$this->profiler->getStopwatch( __FUNCTION__, true );

		// Basic variable init
		$retval = false;    // By default return that we failed

		$gatewayName = self::getGatewayName();
		$email = $this->getData_Unstaged_Escaped( 'email' );

		/**
		 * This log line is pretty important. Usually when a donor contacts us
		 * saying that they have experienced problems donating, the first thing
		 * we have to do is associate a gateway transaction ID and ctid with an
		 * email address. If the cURL function fails, we lose the ability to do
		 * that association outside of this log line.
		 */
		$this->logger->info( "Initiating cURL for donor $email" );

		// Initialize cURL and construct operation (also run filter)
		$ch = curl_init();

		$filterResult = $this->runSessionVelocityFilter();
		if ( $filterResult == false ) {
			return false;
		}

		// assign header data necessary for the curl_setopt() function
		$headers = $this->getCurlBaseHeaders();
		$headers[] = 'Content-Length: ' . strlen( $data );

		$curl_opts = $this->getCurlBaseOpts();
		$curl_opts[CURLOPT_HTTPHEADER] = $headers;
		$curl_opts[CURLOPT_POSTFIELDS] = $data;

		// Always capture the cURL output
		$curlDebugLog = fopen( 'php://temp', 'r+' );
		$curl_opts[CURLOPT_STDERR] = $curlDebugLog;
		$enableCurlVerboseLogging = $this->getGlobal( 'CurlVerboseLog' );

		curl_setopt_array( $ch, $curl_opts );

		// As suggested in the PayPal developer forum sample code, try more than once to get a
		// response in case there is a general network issue
		$continue = true;
		$tries = 0;
		$curl_response = false;
		$loopCount = $this->getGlobal( 'RetryLoopCount' );

		do {
			$this->logger->info( "Preparing to send {$this->getCurrentTransaction()} transaction to $gatewayName" );

			// Execute the cURL operation
			$curl_response = $this->curl_exec( $ch );

			// Always read the verbose output
			rewind( $curlDebugLog );
			$logged = fread( $curlDebugLog, 4096 );

			if ( $curl_response !== false ) {
				// The cURL operation was at least successful, what happened in it?
				// Only log verbose output on success if configured to do so
				if ( $enableCurlVerboseLogging ) {
					$this->logger->info( "cURL verbose logging: $logged" );
				}

				$headers = $this->curl_getinfo( $ch );
				$httpCode = $headers['http_code'];

				switch ( $httpCode ) {
					case 200:   // Everything is AWESOME
						$continue = false;

						$this->logger->debug( "Successful transaction to $gatewayName" );
						$this->transaction_response->setRawResponse( $curl_response );

						$retval = true;
						break;

					case 400:   // Oh noes! Bad request.. BAD CODE, BAD BAD CODE!
						$continue = false;

						$this->logger->error( "$gatewayName returned (400) BAD REQUEST: $curl_response" );

						// Even though there was an error, set the results. Amazon at least gives
						// us useful XML return
						$this->transaction_response->setRawResponse( $curl_response );

						$retval = true;
						break;

					case 403:   // Hmm, forbidden? Maybe if we ask it nicely again...
						$continue = true;
						$this->logger->alert( "$gatewayName returned (403) FORBIDDEN: $curl_response" );
						break;

					default:    // No clue what happened... break out and log it
						$continue = false;
						$this->logger->error( "$gatewayName failed remotely and returned ($httpCode): $curl_response" );
						break;
				}
			} else {
				// Well the cURL transaction failed for some reason or another. Try again!
				$continue = true;

				$errno = $this->curl_errno( $ch );
				$err = curl_error( $ch );

				$this->logger->alert(
					"cURL transaction to $gatewayName failed: ($errno) $err.  " .
					"cURL verbose logging: $logged"
				);
			}
			$tries++;
			if ( $tries >= $loopCount ) {
				$continue = false;
			}
			if ( $continue ) {
				// If we're going to try again, log timing for this particular curl attempt and reset
				$this->profiler->saveCommunicationStats( __FUNCTION__, $this->getCurrentTransaction(), "cURL problems" );
				$this->profiler->getStopwatch( __FUNCTION__, true );
				rewind( $curlDebugLog );
			}
		} while ( $continue ); // End while cURL transaction hasn't returned something useful

		// Clean up and return
		curl_close( $ch );
		fclose( $curlDebugLog );
		$log_results = array(
			'result' => $curl_response,
			'headers' => $headers,
		);
		$this->profiler->saveCommunicationStats( __FUNCTION__, $this->getCurrentTransaction(), "Response: " . print_r( $log_results, true ) );

		return $retval;
	}

	/**
	 * Wrapper for the real curl_exec so we can override with magic for unit tests.
	 * @param resource $ch curl handle (returned from curl_init)
	 * @return mixed True or the result on success (depends if
	 * CURLOPT_RETURNTRANSFER is set or not). False on total failure.
	 */
	protected function curl_exec( $ch ) {
		return curl_exec( $ch );
	}

	/**
	 * Wrapper for the real curl_getinfo so we can override with magic for unit tests.
	 * @param resource $ch curl handle (returned from curl_init)
	 * @return mixed an array, string, or false on total failure.
	 */
	protected function curl_getinfo( $ch ) {
		return curl_getinfo( $ch );
	}

	/**
	 * Wrapper for the real curl_errno so we can override with magic for unit tests.
	 * @param resource $ch curl handle (returned from curl_init)
	 * @return int the error number or 0 if none occurred
	 */
	protected function curl_errno( $ch ) {
		return curl_errno( $ch );
	}

	public function logPending() {
		// Write the donor's details to the log for the audit processor
		$this->logPaymentDetails();
		// Feed the message into the pending queue, so the CRM queue consumer
		// can read it to fill in donor details when it gets a partial message
		$this->sendPendingMessage();

		// Avoid 'bad ffname' logspam on return and try again links.
		// TODO: deprecate
		$this->session_pushFormName( $this->getData_Unstaged_Escaped( 'ffname' ) );
	}

	/**
	 * Whether donation processing depends on additional processing on-wiki
	 * at the donor's return from a payment processor. This is used to
	 * determine whether we should show fail pages on session timeouts.
	 *
	 * @return bool true when on-wiki post-processing is required.
	 */
	public function isReturnProcessingRequired() {
		return false;
	}

	/**
	 * Gateways which return true from isReturnProcessingRequired must
	 * override this with logic to get an ID from the request which will
	 * identify repeated attempts to process the same payment.
	 *
	 * @param array $requestValues
	 * @return int|string Order id
	 */
	public function getRequestProcessId( $requestValues ) {
		return null;
	}

	/**
	 * Process the API response obtained from the payment processor and set
	 * properties of transaction_response.
	 * Default implementation just says we got a response.
	 *
	 * @param array|DomDocument $response Cleaned-up response returned from
	 *        @see getFormattedResponse.  Type depends on $this->getResponseType
	 * @throws ResponseProcessingException with an actionable error code and any
	 *         variables to retry
	 *
	 * TODO: Move response parsing to a separate class.
	 */
	protected function processResponse( $response ) {
		$this->transaction_response->setCommunicationStatus( true );
	}

	/**
	 * Default implementation sets status to complete.
	 * @param array $requestValues all GET and POST values from the request
	 * @return PaymentResult
	 */
	public function processDonorReturn( $requestValues ) {
		$this->finalizeInternalStatus( FinalStatus::COMPLETE );
		return PaymentResult::newSuccess();
	}

	/**
	 * Check the response for general sanity - e.g. correct data format, keys exists
	 * @return boolean true if response looks sane
	 */
	protected function parseResponseCommunicationStatus( $response ) {
		return true;
	}

	/**
	 * Parse the response to get the errors in a format we can log and otherwise deal with.
	 * @return array a key/value array of codes (if they exist) and messages.
	 * TODO: Move to a parsing class, where these are part of an interface
	 * rather than empty although non-abstract.
	 */
	protected function parseResponseErrors( $response ) {
		return array();
	}

	/**
	 * Harvest the data we need back from the gateway.
	 * @return array a key/value array
	 */
	protected function parseResponseData( $response ) {
		return array();
	}

	/**
	 * Take the entire response string, and strip everything we don't care
	 * about.  For instance: If it's XML, we only want correctly-formatted XML.
	 * Headers must be killed off.
	 * @param string $rawResponse hot off the curl
	 * @return string|DomDocument|array depending on $this->getResponseType
	 * @throws InvalidArgumentException
	 * @throws LogicException
	 */
	function getFormattedResponse( $rawResponse ) {
		$type = $this->getResponseType();
		if ( $type === 'xml' ) {
			$xmlString = $this->stripXMLResponseHeaders( $rawResponse );
			$displayXML = $this->formatXmlString( $xmlString );
			$realXML = new DomDocument( '1.0' );
			//DO NOT alter the line below unless you are prepared to also alter the GC audit scripts.
			//...and everything that references "Raw XML Response"
			//@TODO: All three of those things.
			$this->logger->info( "Raw XML Response:\n" . $displayXML ); //I am apparently a huge fibber.
			$realXML->loadXML( trim( $xmlString ) );
			return $realXML;
		}
		// For anything else, delete all the headers and the blank line after
		// Note: the negative lookahead is to ignore PayPal's HTTP continue header.
		$noHeaders = preg_replace( '/^.*?(\r\n\r\n|\n\n)(?!HTTP\/)/ms', '', $rawResponse, 1 );
		$this->logger->info( "Raw Response:" . $noHeaders );
		switch ( $type ) {
		case 'json':
			return json_decode( $noHeaders, true );

		case 'delimited':
			$delimiter = $this->transaction_option( 'response_delimiter' );
			$keys = $this->transaction_option( 'response_keys' );
			if ( !$delimiter || !$keys ) {
				throw new LogicException( 'Delimited transactions must define both response_delimiter and response_keys options' );
			}
			$values = explode( $delimiter, trim( $noHeaders ) );
			$combined = array_combine( $keys, $values );
			if ( $combined === false ) {
				throw new InvalidArgumentException( 'Wrong number of values found in delimited response.');
			}
			return $combined;

		case 'query_string':
			$parsed = array();
			parse_str( $noHeaders, $parsed );
			return $parsed;
		}
		return $noHeaders;
	}

	function stripXMLResponseHeaders( $rawResponse ) {
		$xmlStart = strpos( $rawResponse, '<?xml' );
		if ( $xmlStart === false ) {
			//I totally saw this happen one time. No XML, just <RESPONSE>...
			//...Weaken to almost no error checking.  Buckle up!
			$xmlStart = strpos( $rawResponse, '<' );
		}
		if ( $xmlStart === false ) { //Still false. Your Head Asplode.
			$this->logger->error( "Completely Mangled Response:\n" . $rawResponse );
			return false;
		}
		$justXML = substr( $rawResponse, $xmlStart );
		return $justXML;
	}

	//To avoid reinventing the wheel: taken from http://recursive-design.com/blog/2007/04/05/format-xml-with-php/
	function formatXmlString( $xml ) {
		// add marker linefeeds to aid the pretty-tokeniser (adds a linefeed between all tag-end boundaries)
		$xml = preg_replace( '/(>)(<)(\/*)/', "$1\n$2$3", $xml );

		// now indent the tags
		$token = strtok( $xml, "\n" );
		$result = ''; // holds formatted version as it is built
		$pad = 0; // initial indent
		$matches = array(); // returns from preg_matches()
		// scan each line and adjust indent based on opening/closing tags
		while ( $token !== false ) {

			// test for the various tag states
			// 1. open and closing tags on same line - no change
			if ( preg_match( '/.+<\/\w[^>]*>$/', $token, $matches ) ) {
				$indent = 0;
			} elseif ( preg_match( '/^<\/\w/', $token, $matches ) ) {
				// 2. closing tag - outdent now
				$pad--;
			} elseif ( preg_match( '/^<\w[^>]*[^\/]>.*$/', $token, $matches ) ) {
				// 3. opening tag - don't pad this one, only subsequent tags
				$indent = 1;
			} else {
				// 4. no indentation needed
				$indent = 0;
			}

			// pad the line with the required number of leading spaces
			$line = str_pad( $token, strlen( $token ) + $pad, ' ', STR_PAD_LEFT );
			$result .= $line . "\n"; // add to the cumulative result, with linefeed
			$token = strtok( "\n" ); // get the next token
			$pad += $indent; // update the pad size for subsequent lines
		}

		return $result;
	}

	static function getGatewayName() {
		$c = get_called_class();
		return $c::GATEWAY_NAME;
	}

	static function getGlobalPrefix() {
		$c = get_called_class();
		return $c::GLOBAL_PREFIX;
	}

	static function getIdentifier() {
		$c = get_called_class();
		return $c::IDENTIFIER;
	}

	static function getLogIdentifier() {
		return self::getIdentifier() . '_gateway';
	}

	function xmlChildrenToArray( $xml, $nodename ) {
		$data = array();
		foreach ( $xml->getElementsByTagName( $nodename ) as $node ) {
			foreach ( $node->childNodes as $childnode ) {
				if ( trim( $childnode->nodeValue ) != '' ) {
					$data[$childnode->nodeName] = $childnode->nodeValue;
				}
			}
		}
		return $data;
	}

	/**
	 * addCodeRange is used to define ranges of response codes for major
	 * gateway transactions, that let us know what status bucket to sort
	 * them into.
	 * DO NOT DEFINE OVERLAPPING RANGES!
	 * TODO: Make sure it won't let you add overlapping ranges. That would
	 * probably necessitate the sort moving to here, too.
	 * @param string $transaction The transaction these codes map to.
	 * @param string $key The (incoming) field name containing the numeric codes
	 * we're defining here.
	 * @param string $action One of the constants defined in @see FinalStatus.
	 * @param int $lower The integer value of the lower-bound in this code range.
	 * @param int $upper Optional: The integer value of the upper-bound in the
	 * code range. If omitted, it will make a range of one value: The lower bound.
	 * @throws UnexpectedValueException
	 * @return void
	 */
	protected function addCodeRange( $transaction, $key, $action, $lower, $upper = null ) {
		if ( $upper === null ) {
			$this->return_value_map[$transaction][$key][$lower] = $action;
		} else {
			$this->return_value_map[$transaction][$key][$upper] = array( 'action' => $action, 'lower' => $lower );
		}
	}

	/**
	 * findCodeAction
	 *
	 * @param	string			$transaction
	 * @param	string			$key			The key to lookup in the transaction such as STATUSID
	 * @param	integer|string	$code			This gets converted to an integer if the values is numeric.
	 * FIXME: We should be pulling $code out of the current transaction fields, internally.
	 * FIXME: Rename to reflect that these are Final Status values, not validation actions
	 * @return	null|string	Returns the code action if a valid code is supplied. Otherwise, the return is null.
	 */
	public function findCodeAction( $transaction, $key, $code ) {

		$this->profiler->getStopwatch( __FUNCTION__, true );

		// Do not allow anything that is not numeric
		if ( !is_numeric( $code ) ) {
			return null;
		}

		// Cast the code as an integer
		settype( $code, 'integer');

		// Check to see if the transaction is defined
		if ( !array_key_exists( $transaction, $this->return_value_map ) ) {
			return null;
		}

		// Verify the key exists within the transaction
		if ( !array_key_exists( $key, $this->return_value_map[ $transaction ] ) || !is_array( $this->return_value_map[ $transaction ][ $key ] ) ) {
			return null;
		}

		//sort the array so we can do this quickly.
		ksort( $this->return_value_map[ $transaction ][ $key ], SORT_NUMERIC );

		$ranges = $this->return_value_map[ $transaction ][ $key ];
		// so, you have a code, which is a number. You also have a numerically sorted array.
		// loop through until you find an upper >= your code.
		// make sure it's in the range, and return the action.
		foreach ( $ranges as $upper => $val ) {
			if ( $upper >= $code ) { // you've arrived. It's either here or it's nowhere.
				if ( is_array( $val ) ) {
					if ( $val['lower'] <= $code ) {
						return $val['action'];
					} else {
						return null;
					}
				} else {
					if ( $upper === $code ) {
						return $val;
					} else {
						return null;
					}
				}
			}
		}
		// if we walk straight off the end...
		return null;
	}

	/**
	 * Saves a stomp frame to the configured server and queue, based on the
	 * outcome of our current transaction.
	 * The big tricky thing here, is that we DO NOT SET a FinalStatus,
	 * unless we have just learned what happened to a donation in progress,
	 * through performing the current transaction.
	 * To put it another way, getFinalStatus should always return
	 * false, unless it's new data about a new transaction. In that case, the
	 * outcome will be assigned and the proper queue selected.
	 *
	 * Probably called in postProcessDonation(), which is itself most likely to
	 * be called through executeFunctionIfExists, later on in do_transaction.
	 */
	protected function doStompTransaction() {
		$status = $this->getFinalStatus();
		switch ( $status ) {
			case FinalStatus::COMPLETE:
				// This transaction completed successfully.  Send to the CRM
				// for filing.
				$this->logCompletedPayment();
				$this->pushMessage( 'donations' );
				break;

			case FinalStatus::PENDING:
			case FinalStatus::PENDING_POKE:
				// Don't consider this a done deal.  Send to a database where
				// various workers can clean up and correlate pending data as
				// new information arrives from the processor.
				$this->pushMessage( 'pending' );
				break;

			default:
				// No action.  FIXME: But don't callers assume that we've done
				// something responsible with this message?  Throw a not sent
				// exception?
				$this->logger->info( "Not sending queue message for status {$status}." );
		}
	}

	/**
	 * Collect donation details and normalize keys for pending or
	 * donations queue
	 *
	 * @return array
	 */
	protected function getQueueDonationMessage() {
		$queueMessage = array(
			'gateway_txn_id' => $this->getTransactionGatewayTxnID(),
			'response' => $this->getTransactionMessage(),
			'gateway_account' => $this->account_name,
			'fee' => 0, // FIXME: don't we know this for some gateways?
		);

		$messageKeys = DonationData::getMessageFields();

		$requiredKeys = array(
			'amount',
			'contribution_tracking_id',
			'country',
			'gateway',
			'language',
			'order_id',
			'payment_method',
			'payment_submethod',
			'user_ip',
			'utm_source',
		);

		$remapKeys = array(
			'amount' => 'gross',
		);

		// Add the rest of the relevant data
		// FIXME: This is "normalized" data.  We should refer to it as such,
		// and rename the getData_Unstaged_Escaped function.
		$data = $this->getData_Unstaged_Escaped();
		foreach ( $messageKeys as $key ) {
			if ( isset( $queueMessage[$key] ) ) {
				// don't clobber the pre-sets
				continue;
			}
			if ( !isset( $data[$key] ) ) {
				if ( in_array( $key, $requiredKeys ) ) {
					throw new RuntimeException( "Missing required message key $key" );
				}
				continue;
			}
			$value = Encoding::toUTF8( $data[$key] );
			if ( isset( $remapKeys[$key] ) ) {
				$queueMessage[$remapKeys[$key]] = $value;
			} else {
				$queueMessage[$key] = $value;
			}
		}
		// FIXME: Note that we're not using any existing date or ts fields.  Why is that?
		$queueMessage['date'] = time();

		return $queueMessage;
	}

	public function addStandardMessageFields( $transaction ) {
		// basically, add all the stuff we have come to take for granted, because syslog.
		$transaction['gateway_txn_id'] = $this->getTransactionGatewayTxnId();
		$transaction['date'] = UtcDate::getUtcTimestamp();
		$transaction['server'] = gethostname();

		$these_too = array (
			'gateway',
			'contribution_tracking_id',
			'order_id',
			'payment_method',
		);
		foreach ( $these_too as $field ) {
			$transaction[$field] = $this->getData_Unstaged_Escaped( $field );
		}

		return $transaction;
	}

	/**
	 * Executes the specified function in $this, if one exists.
	 * NOTE: THIS WILL LCASE YOUR FUNCTION_NAME.
	 * ...I like to keep the voodoo functions tidy.
	 * @param string $function_name The name of the function you're hoping to
	 * execute.
	 * @param mixed $parameter That's right: For now you only get one.
	 * @return bool True if a function was found and executed.
	 */
	function executeIfFunctionExists( $function_name, $parameter = null ) {
		$function_name = strtolower( $function_name ); //Because, that's why.
		if ( method_exists( $this, $function_name ) ) {
			$this->{$function_name}( $parameter );
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Run any staging DataTransformers configured for the adapter
	 */
	protected function stageData() {
		// Copy data, the default is to not change the values.
		// Reset from our normalized unstaged data so we never double-stage.
		$this->staged_data = $this->unstaged_data;

		foreach ( $this->data_transformers as $transformer ) {
			if ( $transformer instanceof StagingHelper ) {
				$transformer->stage( $this, $this->unstaged_data, $this->staged_data );
			}
		}

		// Format the staged data
		$this->formatStagedData();
	}

	/**
	 * Run any unstaging functions to decode processor responses
	 */
	protected function unstageData() {

		$this->unstaged_data = $this->staged_data;

		foreach ( $this->data_transformers as $transformer ) {
			if ( $transformer instanceof UnstagingHelper ) {
				$transformer->unstage( $this, $this->staged_data, $this->unstaged_data );
			}
		}
	}

	/**
	 * Format staged data
	 *
	 * Formatting:
	 * - trim - all strings
	 * - truncate - all strings to the maximum length permitted by the gateway
	 */
	public function formatStagedData() {

		foreach ( $this->staged_data as $field => $value ) {

			// Trim all values if they are a string
			$value = is_string( $value ) ? trim( $value ) : $value;

			if ( isset( $this->dataConstraints[ $field ] ) && is_string( $value ) ) {

				// Truncate the field if it has a length specified
				if ( isset( $this->dataConstraints[ $field ]['length'] ) ) {
					$length = (integer) $this->dataConstraints[ $field ]['length'];
				} else {
					$length = false;
				}

				if ( !empty( $length ) && !empty( $value ) ) {
					//Note: This is the very last resort. This should already have been dealt with thoroughly in staging.
					$value = substr( $value, 0, $length );
				}

			}

			$this->staged_data[ $field ] = $value;
		}
	}

	/**
	 * Build the parameters sent with the next request.
	 *
	 * @return array Parameters as a map.
	 */
	public function buildRequestParams() {
		// Look up the request structure for our current transaction type in the transactions array
		$structure = $this->getTransactionRequestStructure();
		if ( !is_array( $structure ) ) {
			return '';
		}

		$queryparams = array();

		//we are going to assume a flat array, because... namevalue.
		foreach ( $structure as $fieldname ) {
			$fieldvalue = $this->getTransactionSpecificValue( $fieldname );
			if ( $fieldvalue !== '' && $fieldvalue !== false ) {
				$queryparams[ $fieldname ] = $fieldvalue;
			}
		}

		return $queryparams;
	}

	public function getTransactionResponse() {
		return $this->transaction_response;
	}

	/**
	 * Returns the transaction communication status, or false if not set
	 * present.
	 * @return mixed
	 */
	public function getTransactionStatus() {
		if ( $this->transaction_response && $this->transaction_response->getCommunicationStatus() ) {
			return $this->transaction_response->getCommunicationStatus();
		}
		return false;
	}

	/**
	 * If it has been set: returns the final payment status in the $final_status
	 * member variable. This is the one we care about for switching
	 * on overall behavior. Otherwise, returns false.
	 * @return mixed Final Transaction results status, or false if not set.
	 * Should be one of the constants defined in @see FinalStatus
	 */
	public function getFinalStatus() {
		if ( $this->final_status ) {
			return $this->final_status;
		} else {
			return false;
		}
	}

	/**
	 * Sets the final payment status. This is the one we care about for
	 * switching on behavior.
	 * DO NOT SET THE FINAL STATUS unless you've just taken an entire donation
	 * process to completion: This status being set at all, denotes the very end
	 * of the donation process on our end. Further attempts by the same user
	 * will be seen as starting over.
	 * @param string $status The final status of one discrete donation attempt,
	 * can be one of constants defined in @see FinalStatus
	 * @throws UnexpectedValueException
	 */
	public function finalizeInternalStatus( $status ) {

		/**
		 * Handle session stuff!
		 * -Behavior-
		 * * Always, always increment numAttempt.
		 * * complete/pending/pending-poke: Reset for potential totally
		 * new payment, but keep numAttempt and other antifraud things
		 * (velocity data) around.
		 * * failed: KEEP all donor data around unless numAttempt has
		 * hit its max, but kill the ctid (in the likely case that it
		 * was an honest mistake)
		 */
		$this->incrementNumAttempt();
		$force = false;
		switch ( $status ) {
			case FinalStatus::COMPLETE:
			case FinalStatus::PENDING:
			case FinalStatus::PENDING_POKE:
				$force = true;
				break;
			case FinalStatus::FAILED:
			case FinalStatus::REVISED:
				$force = false;
				break;
		}
		$this->session_resetForNewAttempt( $force );

		$this->logFinalStatus( $status );

		$this->sendFinalStatusMessage( $status );

		$this->final_status = $status;
	}

	/**
	 * Easily-child-overridable log component of setting the final
	 * transaction status, which will only ever be set at the very end of a
	 * transaction workflow.
	 * @param string $status one of the constants defined in @see FinalStatus
	 */
	public function logFinalStatus( $status ) {
		$action = $this->getValidationAction();

		$msg = " FINAL STATUS: '$status:$action' - ";

		//what do we want in here?
		//Attempted payment type, country of origin, $status, amount... campaign?
		//error message if one exists.
		$keys = array(
			'payment_submethod',
			'payment_method',
			'country',
			'utm_campaign',
			'amount',
			'currency',
		);

		foreach ( $keys as $key ) {
			$msg .= $this->getData_Unstaged_Escaped( $key ) . ', ';
		}

		$txn_message = $this->getTransactionMessage();
		if ( $txn_message ) {
			$msg .= " $txn_message";
		}

		$this->payment_init_logger->info( $msg );
	}

	/**
	 * Build and send a message to the payments-init queue, once the initial workflow is complete.
	 */
	public function sendFinalStatusMessage( $status ) {
		$transaction = array(
			'validation_action' => $this->getValidationAction(),
			'payments_final_status' => $status,
		);

		//add more keys here if you want it in the db equivalent of the payments-init queue.
		//for now, though, just taking the ones that make it to the logs.
		$keys = array(
			'payment_submethod',
			'country',
			'amount',
			'currency',
		);

		foreach ( $keys as $key ) {
			$transaction[$key] = $this->getData_Unstaged_Escaped( $key );
		}

		$transaction = $this->addStandardMessageFields( $transaction );

		try {
			// FIXME: Dispatch "freeform" messages transparently as well.
			// TODO: write test
			$this->logger->info( 'Pushing transaction to payments-init queue.' );
			DonationQueue::instance()->push( $transaction, 'payments-init' );
		} catch ( Exception $e ) {
			$this->logger->error( 'Unable to send payments-init message' );
		}
	}

	/**
	 * @deprecated
	 * @return string|boolean
	 */
	public function getTransactionMessage() {
		if ( $this->transaction_response && $this->transaction_response->getTxnMessage() ) {
			return $this->transaction_response->getTxnMessage();
		}
		return false;
	}

	/**
	 * @deprecated
	 * @return string|boolean
	 */
	public function getTransactionGatewayTxnID() {
		if ( $this->transaction_response && $this->transaction_response->getGatewayTransactionId() ) {
			return $this->transaction_response->getGatewayTransactionId();
		}
		return false;
	}

	/**
	 * Returns the FORMATTED data harvested from the reply, or false if it is not set.
	 * @return mixed An array of returned data, or false.
	 */
	public function getTransactionData() {
		if ( $this->transaction_response && $this->transaction_response->getData() ) {
			return $this->transaction_response->getData();
		}
		return false;
	}

	public function getFormClass() {
		$ffname = $this->dataObj->getVal( 'ffname' );
		if ( strpos( $ffname, 'error') === 0
			|| strpos( $ffname, 'maintenance') === 0 ) {
			return 'MustacheErrorForm';
		}
		return 'Gateway_Form_Mustache';
	}

	public function getGatewayAdapterClass() {
		return get_called_class();
	}

	/**
	 * Return any errors that prevent this transaction from continuing.
	 * @return ErrorState
	 */
	public function getErrorState() {
		return $this->errorState;
	}

	/**
	 * Adds one to the 'numAttempt' field we use to keep track of how many
	 * times a donor has attempted a payment, in a session.
	 * When they first show up (or get their token/session reset), it should
	 * be set to '0'.
	 */
	protected function incrementNumAttempt() {
		if ( $this->isBatchProcessor() ) {
			return;
		}
		$this->session_ensure();
		$attempts = $this->session_getData( 'numAttempt' ); //intentionally outside the 'Donor' key.
		if ( is_numeric( $attempts ) ) {
			$attempts += 1;
		} else {
			//assume garbage = 0, so...
			$attempts = 1;
		}

		WmfFramework::setSessionValue( 'numAttempt', $attempts );
	}

	/**
	 * Some payment gateways require a distinct identifier for each API call
	 * or for each new payment attempt, even if retrying an attempt that failed
	 * validation.  This is slightly different from numAttempt, which is only
	 * incremented when setting a final status for a payment attempt.
	 * It is the child class's responsibility to increment this at the
	 * appropriate time.
	 */
	protected function incrementSequenceNumber() {
		$this->session_ensure();
		$sequence = $this->session_getData( 'sequence' ); //intentionally outside the 'Donor' key.
		if ( is_numeric( $sequence ) ) {
			$sequence += 1;
		} else {
			$sequence = 1;
		}

		WmfFramework::setSessionValue( 'sequence', $sequence );
	}

	public function setHash( $hashval ) {
		$this->dataObj->setVal( 'data_hash', $hashval );
	}

	public function unsetHash() {
		$this->dataObj->expunge( 'data_hash' );
	}

	/**
	 * Runs all the fraud filters that have been enabled and configured in
	 * donationdata.php and/or LocalSettings.php
	 * This function is most likely to be called through
	 * executeFunctionIfExists, early on in do_transaction.
	 */
	function runAntifraudFilters() {
		//extra layer of Stop Doing This.
		if ( $this->errorState->hasErrors() ) {
			$this->logger->info( 'Skipping antifraud filters: Transaction is already in error' );
			return;
		}
		// allow any external validators to have their way with the data
		$this->logger->info( 'Preparing to run custom filters' );
		Gateway_Extras_CustomFilters::onValidate( $this );
		$this->logger->info( 'Finished running custom filters' );
	}

	/**
	 * Runs all the post-process logic that has been enabled and configured in
	 * donationdata.php and/or LocalSettings.php, including the ActiveMQ/Stomp
	 * queue message.
	 * This function is most likely to be called through
	 * executeFunctionIfExists, later on in do_transaction.
	 */
	protected function postProcessDonation() {
		Gateway_Extras_CustomFilters_IP_Velocity::onPostProcess( $this );
		Gateway_Extras_ConversionLog::onPostProcess( $this );

		try {
			$this->doStompTransaction();
		}
		catch ( Exception $ex ) {
			$this->logger->alert( "Failure queueing final status message: {$ex->getMessage()}" );
		}
	}

	protected function pushMessage( $queue ) {
		$this->logger->info( "Pushing transaction to queue [$queue]" );
		DonationQueue::instance()->push( $this->getQueueDonationMessage(), $queue );
	}

	protected function sendPendingMessage() {
		$order_id = $this->getData_Unstaged_Escaped( 'order_id' );
		$this->logger->info( "Sending donor details for $order_id to pending queue" );
		DonationQueue::instance()->push( $this->getQueueDonationMessage(), 'pending' );
	}

	/**
	 * If there are things about a transaction that we need to stash in the
	 * transaction's definition (defined in a local defineTransactions() ), we
	 * can recall them here. Currently, this is only being used to determine if
	 * we have a transaction whose transmission would require multiple attempts
	 * to wait for a certain status (or set of statuses), but we could do more
	 * with this mechanism if we need to.
	 * @param string $option_value the name of the key we're looking for in the
	 * transaction definition.
	 * @return mixed the transaction's value for that key if it exists, or NULL.
	 */
	protected function transaction_option( $option_value ) {
		// ooo, ugly.
		$transaction = $this->getCurrentTransaction();
		if ( !$transaction ) {
			return NULL;
		}
		if ( array_key_exists( $option_value, $this->transactions[$transaction] ) ) {
			return $this->transactions[$transaction][$option_value];
		}
		return NULL;
	}

	/**
	 * Instead of pulling all the DonationData back through to update one local
	 * value, use this. It updates both staged_data (which is intended to be
	 * staged and used _just_ by the gateway) and unstaged_data, which is actually
	 * just normalized and sanitized form data as entered by the user.
	 * You should restage the data after running this.
	 *
	 * Not doing this right now, though, because it's not yet necessary for
	 * anything we have at the moment.
	 *
	 * @param string $val The field name that we are looking to retrieve from
	 * our DonationData object.
	 */
	function refreshGatewayValueFromSource( $val ) {
		$refreshed = $this->dataObj->getVal( $val );
		if ( !is_null($refreshed) ){
			$this->staged_data[$val] = $refreshed;
			$this->unstaged_data[$val] = $refreshed;
		} else {
			unset( $this->staged_data[$val] );
			unset( $this->unstaged_data[$val] );
		}
	}

	public function setRiskScore( $score ) {
		$this->risk_score = $score;
	}

	public function setValidationAction( $action, $reset = false ) {
		//our choices are:
		$actions = array(
			'process' => 0,
			'review' => 1,
			'challenge' => 2,
			'reject' => 3,
		);
		if ( !isset( $actions[$action] ) ) {
			throw new UnexpectedValueException( "Action $action is invalid." );
		}

		if ( $reset ) {
			$this->action = $action;
			return;
		}

		if ( ( int ) $actions[$action] > ( int ) $actions[$this->getValidationAction()] ) {
			$this->action = $action;
		}
	}

	public function getValidationAction() {
		if ( !isset( $this->action ) ) {
			$this->action = 'process';
		}
		return $this->action;
	}

	public function isBatchProcessor() {
		return $this->batch;
	}

	/**
	 * Build list of required fields
	 * TODO: Determine if this ever needs to be overridden per gateway, or if
	 * all the per-country / per-gateway cases can be expressed declaratively
	 * in payment method / submethod metadata.  If that's the case, move this
	 * function (to DataValidator?)
	 * @param array|null $knownData if provided, used to determine fields that
	 *  depend on country or payment method. Falls back to unstaged data.
	 * @return array of field names (empty if no payment method set)
	 */
	public function getRequiredFields( $knownData = null ) {
		if ( $knownData === null ) {
			$knownData = $this->getData_Unstaged_Escaped();
		}
		$required_fields = array();
		$validation = array();

		// Add any country-specific required fields
		if (
			isset( $this->config['country_fields'] ) &&
			!empty( $knownData['country'] )
		) {
			$country = $knownData['country'];
			if ( isset( $this->config['country_fields'][$country] ) ) {
				$validation = $this->config['country_fields'][$country];
			}
		}

		if ( !empty( $knownData['payment_method'] ) ) {
			$methodMeta = $this->getPaymentMethodMeta( $knownData['payment_method'] );
			if ( isset( $methodMeta['validation'] ) ) {
				$validation = $methodMeta['validation'] + $validation;
			}
		}

		if ( !empty( $knownData['payment_submethod'] ) ) {
			$submethodMeta = $this->getPaymentSubmethodMeta( $knownData['payment_submethod'] );
			if ( isset( $submethodMeta['validation'] ) ) {
				// submethod validation can override method validation
				// TODO: child method anything should supersede parent method
				// anything, and PaymentMethod should handle that.
				$validation = $submethodMeta['validation'] + $validation;
			}
		}

		foreach ( $validation as $type => $enabled ) {
			if ( $enabled !== true ) {
				continue;
			}

			switch ( $type ) {
				case 'address' :
					$check_not_empty = array(
						'street_address',
						'city',
						'country',
						'postal_code', //this should really be added or removed, depending on the country and/or gateway requirements.
						//however, that's not happening in this class in the code I'm replacing, so...
						//TODO: Something clever in the DataValidator with data groups like these.
					);
					if ( !empty( $knownData['country'] ) ) {
						$country = $knownData['country'];
						if ( $country && Subdivisions::getByCountry( $country ) ) {
							$check_not_empty[] = 'state_province';
						}
					}
					break;
				case 'creditCard' :
					$check_not_empty = array(
						'card_num',
						'cvv',
						'expiration',
						'card_type'
					);
					break;
				case 'name' :
					$check_not_empty = array(
						'first_name',
						'last_name'
					);
					break;
				default:
					$check_not_empty = array( $type );
					continue;
			}
			$required_fields = array_unique( array_merge( $required_fields, $check_not_empty ) );
		}

		return $required_fields;
	}

	/**
	 * Check donation data for validity and set errors.
	 *
	 * This function will go through all the data we have pulled from wherever
	 * we've pulled it, and make sure it's safe and expected and everything.
	 * If it is not, it will return an array of errors ready for any
	 * DonationInterface form class derivative to display.
	 *
	 * @return boolean true if validation passes
	 */
	public function validate() {
		$normalized = $this->dataObj->getData();

		if ( $this->transaction_option( 'check_required' ) ) {
			// The fields returned by getRequiredFields only make sense
			// for certain transactions. TODO: getRequiredFields should
			// actually return different things for different transactions
			$check_not_empty = $this->getRequiredFields();
		} else {
			$check_not_empty = array();
		}
		$this->errorState->addErrors(
			DataValidator::validate( $this, $normalized, $check_not_empty )
		);

		// Run modular validations.
		$transformers = $this->getDataTransformers();
		foreach ( $transformers as $transformer ) {
			if ( $transformer instanceof ValidationHelper ) {
				$transformer->validate( $this, $normalized, $this->errorState );
			}
		}

		// TODO: Rewrite as something modular?  It's in-between validation and normalization...
		if ( $this->errorState->hasValidationError( 'currency' ) ) {
			// Try to fall back to a default currency, clearing the error if
			// successful.
			$this->fallbackToDefaultCurrency();
			// FIXME: This is part of the same wart.
			$this->unstaged_data = $this->dataObj->getData();
		}

		return $this->validatedOK();
	}

	/**
	 * @return boolean True if submitted data is valid and sufficient to proceed to the next step.
	 * TODO: Were we also trying to indicate whether the validation step has succeeded here, by distinguishing array() != false?
	 */
	public function validatedOK() {
		return !$this->errorState->hasValidationError();
	}

	/**
	 * Called when a currency code error exists. If a fallback currency
	 * conversion is enabled for this adapter, convert intended amount to
	 * default currency.
	 *
	 * TODO: In the future, we might want to switch gateways.
	 *
	 * @throws DomainException
	 */
	protected function fallbackToDefaultCurrency() {
		$defaultCurrency = null;
		if ( $this->getGlobal( 'FallbackCurrencyByCountry' ) ) {
			$country = $this->dataObj->getVal( 'country' );
			if ( $country !== null ) {
				$defaultCurrency = NationalCurrencies::getNationalCurrency( $country );
			}
		} else {
			$defaultCurrency = $this->getGlobal( 'FallbackCurrency' );
		}
		if ( !$defaultCurrency ) {
			return;
		}
		// Our conversion rates are all relative to USD, so use that as an
		// intermediate currency if converting between two others.
		$oldCurrency = $this->dataObj->getVal( 'currency' );
		if ( $oldCurrency === $defaultCurrency ) {
			$adapterClass = $this->getGatewayAdapterClass();
			throw new DomainException( __FUNCTION__ . " Unsupported currency $defaultCurrency set as fallback for $adapterClass." );
		}
		$oldAmount = $this->dataObj->getVal( 'amount' );
		$usdAmount = 0.0;
		$newAmount = 0;

		$conversionRates = CurrencyRates::getCurrencyRates();
		if ( $oldCurrency === 'USD' ) {
			$usdAmount = $oldAmount;
		} elseif ( array_key_exists( $oldCurrency, $conversionRates ) ) {
			$usdAmount = $oldAmount / $conversionRates[$oldCurrency];
		} else {
			// We can't convert from this unknown currency.
			$this->logger->warning( "Currency conversion not available for {$oldCurrency}" );
			return;
		}

		if ( $defaultCurrency === 'USD' ) {
			$newAmount = floor( $usdAmount );
		} elseif ( array_key_exists( $defaultCurrency, $conversionRates ) ) {
			$newAmount = floor( $usdAmount * $conversionRates[$defaultCurrency] );
		} else {
			// No conversion available.
			$this->logger->error( "Default fallback currency {$defaultCurrency} has no conversion available" );
			return;
		}

		$formData = array(
			'amount' => $newAmount,
			'currency' => $defaultCurrency,
		);
		$this->dataObj->addData( $formData );

		$this->logger->info( "Unsupported currency $oldCurrency forced to $defaultCurrency" );

		// Clear the currency error.
		$this->errorState->clearValidationError( 'currency' );

		$notify = $this->getGlobal( 'NotifyOnConvert' );

		// If we're configured to notify, or if there are already other errors,
		// add a notification message.
		if ( $notify || $this->errorState->hasErrors() ) {
			$this->errorState->addError(
				new ValidationError(
					'currency',
					'donate_interface-fallback-currency-notice',
					array( $defaultCurrency )
				)
			);
		}
	}

	/**
	 * This custom filter function checks the global variable:
	 * wgDonationInterfaceNameFilterRules
	 * Each entry in that array has keys
	 *   KeyMapA, KeyMapB: define keyboard zones
	 *   GibberishWeight: threshold fraction of name letters in a single zone
	 *   Score: added to the total fraud score when this threshold is exceeded
	 *
	 * How the score is tabulated:
	 *  - If the configurable portion letters in a name come from the same zone points are added.
	 *  - Returns an integer: 0 <= $score <= 100
	 *
	 * @see $wgDonationInterfaceCustomFiltersFunctions
	 * @see $wgDonationInterfaceNameFilterRules
	 *
	 * @return integer
	 */
	public function getScoreName(){
		$fName = $this->getData_Unstaged_Escaped( 'first_name' );
		$lName = $this->getData_Unstaged_Escaped( 'last_name' );

		$nameArray = str_split( strtolower( $fName . $lName ) );
		$rules = $this->getGlobal( 'NameFilterRules' );
		$score = 0;

		foreach( $rules as $rule ) {
			$keyMapA = $rule['KeyMapA'];
			$keyMapB = $rule['KeyMapB'];

			$gibberishWeight = $rule['GibberishWeight'];

			$failScore = $rule['Score'];

			$points = 0;

			if ( is_array( $nameArray ) && !empty( $nameArray ) ) {
				foreach ( $nameArray as $letter ) {
					// For each char in zone A add a point, zone B subtract.
					if ( in_array( $letter, $keyMapA ) ) {
						$points++;
					}
					if ( in_array( $letter, $keyMapB ) ) {
						$points--;
					}
				}

				if ( abs( $points ) / count( $nameArray ) >= $gibberishWeight ) {
					$score += $failScore;
				}
			}
		}
		return $score;
	}

	/**
	 * This custom filter function checks the global variable:
	 *
	 * CountryMap
	 *
	 * How the score is tabulated:
	 *  - If a country is not defined, a score of zero will be generated.
	 *  - Generates a score based on the defined value.
	 *  - Returns an integer: 0 <= $score <= 100
	 *
	 * @see $wgDonationInterfaceCustomFiltersFunctions
	 * @see $wgDonationInterfaceCountryMap
	 *
	 * @return integer
	 */
	public function getScoreCountryMap() {

		$score = 0;

		$country = $this->getData_Unstaged_Escaped( 'country' );

		$countryMap = $this->getGlobal( 'CountryMap' );

		$msg = self::getGatewayName() . ': Country map: '
			. print_r( $countryMap, true );

		$this->logger->debug( $msg );

		// Lookup a score if it is defined
		if ( isset( $countryMap[ $country ] ) ) {
			$score = (integer) $countryMap[ $country ];
		}

		// @see $wgDonationInterfaceDisplayDebug
		$this->debugarray[] = 'custom filters function: get country [ '
			. $country . ' ] map score = ' . $score;

		return $score;
	}

	/**
	 * This custom filter function checks the global variable:
	 *
	 * EmailDomainMap
	 *
	 * How the score is tabulated:
	 *  - If a emailDomain is not defined, a score of zero will be generated.
	 *  - Generates a score based on the defined value.
	 *  - Returns an integer: 0 <= $score <= 100
	 *
	 * @see $wgDonationInterfaceCustomFiltersFunctions
	 * @see $wgDonationInterfaceEmailDomainMap
	 *
	 * @return integer
	 */
	public function getScoreEmailDomainMap() {

		$score = 0;

		$email = $this->getData_Unstaged_Escaped( 'email' );

		$emailDomain = substr( strstr( $email, '@' ), 1 );

		$emailDomainMap = $this->getGlobal( 'EmailDomainMap' );

		$msg = self::getGatewayName() . ': Email Domain map: '
			. print_r( $emailDomainMap, true );

		// TODO: Remove this weaksalsa debug message...
		$this->logger->debug( $msg );

		// Lookup a score if it is defined
		if ( isset( $emailDomainMap[ $emailDomain ] ) ) {
			$score = (integer) $emailDomainMap[ $emailDomain ];
		}

		// @see $wgDonationInterfaceDisplayDebug
		$this->debugarray[] = 'custom filters function: get email domain [ '
			. $emailDomain . ' ] map score = ' . $score;

		return $score;
	}

	/**
	 * This custom filter function checks the global variable:
	 *
	 * UtmCampaignMap
	 *
	 * @TODO: All these regex map matching functions that are identical with
	 * different internal var names are making me rilly mad. Collapse.
	 *
	 * How the score is tabulated:
	 *  - Add the score(value) associated with each regex(key) in the map var.
	 *
	 * @see $wgDonationInterfaceCustomFiltersFunctions
	 * @see $wgDonationInterfaceUtmCampaignMap
	 *
	 * @return integer
	 */
	public function getScoreUtmCampaignMap() {

		$score = 0;

		$campaign = $this->getData_Unstaged_Escaped( 'utm_campaign' );
		$campaignMap = $this->getGlobal( 'UtmCampaignMap' );

		$msg = self::getGatewayName() . ': UTM Campaign map: '
			. print_r( $campaignMap, true );

		$this->logger->debug( $msg );

		// If any of the defined regex patterns match, add the points.
		if ( is_array( $campaignMap ) && !empty( $campaignMap ) ){
			foreach ( $campaignMap as $regex => $points ){
				if ( preg_match( $regex, $campaign ) ) {
					$score = (integer) $points;
				}
			}
		}

		// @see $wgDonationInterfaceDisplayDebug
		$this->debugarray[] = 'custom filters function: get utm campaign [ '
			. $campaign . ' ] score = ' . $score;

		return $score;
	}

	/**
	 * This custom filter function checks the global variable:
	 *
	 * UtmMediumMap
	 *
	 * @TODO: Again. Regex map matching functions, identical, with minor
	 * internal var names. Collapse.
	 *
	 * How the score is tabulated:
	 *  - Add the score(value) associated with each regex(key) in the map var.
	 *
	 * @see $wgDonationInterfaceCustomFiltersFunctions
	 * @see $wgDonationInterfaceUtmMediumMap
	 *
	 * @return integer
	 */
	public function getScoreUtmMediumMap() {

		$score = 0;

		$medium = $this->getData_Unstaged_Escaped( 'utm_medium' );
		$mediumMap = $this->getGlobal( 'UtmMediumMap' );

		$msg = self::getGatewayName() . ': UTM Medium map: '
			. print_r( $mediumMap, true );

		$this->logger->debug( $msg );

		// If any of the defined regex patterns match, add the points.
		if ( is_array( $mediumMap ) && !empty( $mediumMap ) ) {
			foreach ( $mediumMap as $regex => $points ){
				if ( preg_match( $regex, $medium ) ) {
					$score = (integer) $points;
				}
			}
		}

		// @see $wgDonationInterfaceDisplayDebug
		$this->debugarray[] = 'custom filters function: get utm medium [ '
			. $medium . ' ] score = ' . $score;

		return $score;
	}

	/**
	 * This custom filter function checks the global variable:
	 *
	 * UtmSourceMap
	 *
	 * @TODO: Argharghargh, inflated code! Collapse!
	 *
	 * How the score is tabulated:
	 *  - Add the score(value) associated with each regex(key) in the map var.
	 *
	 * @see $wgDonationInterfaceCustomFiltersFunctions
	 * @see $wgDonationInterfaceUtmSourceMap
	 *
	 * @return integer
	 */
	public function getScoreUtmSourceMap() {

		$score = 0;

		$source = $this->getData_Unstaged_Escaped( 'utm_source' );
		$sourceMap = $this->getGlobal( 'UtmSourceMap' );

		$msg = self::getGatewayName() . ': UTM Source map: '
			. print_r( $sourceMap, true );

		$this->logger->debug( $msg );

		// If any of the defined regex patterns match, add the points.
		if ( is_array( $sourceMap ) && !empty( $sourceMap ) ){
			foreach ( $sourceMap as $regex => $points ) {
				if ( preg_match( $regex, $source ) ) {
					$score = (integer) $points;
				}
			}
		}

		// @see $wgDonationInterfaceDisplayDebug
		$this->debugarray[] = 'custom filters function: get utm source [ '
			. $source . ' ] score = ' . $score;

		return $score;
	}

	public function getAccountConfig( $key ) {
		return $this->account_config[$key];
	}

	/**
	 * For places that might need the merchant ID outside of the adapter
	 * @deprecated
	 */
	public function getMerchantID() {
		return $this->account_config[ 'MerchantID' ];
	}

	public function session_ensure() {
		WmfFramework::setupSession();
	}

	/**
	 * Retrieve data from the sesion if it's set, and null if it's not.
	 * @param string $key The array key to return from the session.
	 * @param string $subkey Optional: The subkey to return from the session.
	 * Only really makes sense if $key is an array.
	 * @return mixed The session value if present, or null if it is not set.
	 */
	public function session_getData( $key, $subkey = null ) {
		$data = WmfFramework::getSessionValue( $key );
		if ( !is_null( $data ) ) {
			if ( is_null( $subkey ) ) {
				return $data;
			} else if ( is_array( $data ) && array_key_exists( $subkey, $data ) ) {
				return $data[$subkey];
			}
		}
		return null;
	}

	/**
	 * Checks to see if we have donor data in our session.
	 * This can be useful for determining if a user should be at a certain point
	 * in the workflow for certain gateways. For example: This is used on the
	 * outside of the adapter in resultswitcher pages, to determine if the user
	 * is actually in the process of making a credit card transaction.
	 *
	 * @return boolean true if the session contains donor data
	 */
	public function session_hasDonorData() {
		return !is_null( $this->session_getData( 'Donor' ) );
	}

	/**
	 * Unsets the session data, in the case that we've saved it for gateways
	 * like GlobalCollect that require it to persist over here through their
	 * iframe experience.
	 */
	public function session_unsetDonorData() {
		if ( $this->session_hasDonorData() ) {
			WmfFramework::setSessionValue( 'Donor', null );
		}
	}

	/**
	 * Removes any old donor data from the session, and adds the current set.
	 * This will be used internally every time we call do_transaction.
	 */
	public function session_addDonorData() {
		if ( $this->isBatchProcessor() ) {
			return;
		}
		$this->session_ensure();
		$sessionId = WmfFramework::getSessionId();
		$this->logger->info( __FUNCTION__ . ": Refreshing all donor data in session '$sessionId''" );
		$sessionFields = DonationData::getSessionFields();

		$data = array();
		foreach ( $sessionFields as $field ) {
			$data[$field] = $this->getData_Unstaged_Escaped( $field );
		}
		WmfFramework::setSessionValue( 'Donor', $data );
	}

	/**
	 * This should kill the session as hard as possible.
	 * It will leave the cookie behind, but everything it could possibly
	 * reference will be gone.
	 */
	public function session_killAllEverything() {
		if ( $this->isBatchProcessor() ) {
			return;
		}
		SessionManager::getGlobalSession()->clear();
	}

	/**
	 * Destroys the session completely.
	 * ...including session velocity data, and the form stack. So, you
	 * probably just shouldn't. Please consider session_reset instead. Please.
	 * Note: This will leave the cookie behind! It just won't go to anything at
	 * all.
	 * FIXME: This is silly and redundant and should probably be killed.
	 */
	public function session_unsetAllData() {
		$this->session_killAllEverything();
		$this->debugarray[] = 'Killed all the session everything.';
	}

	/**
	 * For those times you want to have the user functionally start over
	 * without, you know, cutting your entire head off like you do with
	 * session_unsetAllData().
	 * @param bool $force Behavior Description:
	 * $force = true: Reset for potential totally new payment, but keep
	 * numAttempt and other antifraud things (velocity data) around.
	 * $force = false: Keep all donor data around unless numAttempt has hit
	 * its max, but kill the ctid (in the likely case that it was an honest
	 * mistake)
	 */
	public function session_resetForNewAttempt( $force = false ) {
		if ( $this->isBatchProcessor() ) {
			return;
		}
		$reset = $force;
		if ( $this->session_getData( 'numAttempt' ) > 3 ) {
			$reset = true;
			WmfFramework::setSessionValue( 'numAttempt', 0 );
		}

		if ( $reset ) {
			$this->logger->info( __FUNCTION__ . ': Unsetting session donor data' );
			$this->session_unsetDonorData();
			//leave the payment forms and antifraud data alone.
			//but, under no circumstances should the gateway edit
			//token appear in the preserve array...
			$preserveKeys = array(
				'DonationInterface_SessVelocity',
				'PaymentForms',
				'numAttempt',
				'order_status', //for post-payment activities
				'sequence',
			);
			$preservedData = array();
			$msg = '';
			foreach ( $preserveKeys as $keep ) {
				$value = WmfFramework::getSessionValue( $keep );
				if ( !is_null( $value ) ) {
					$preservedData[$keep] = $value;
					$msg .= "$keep, "; //always one extra comma; Don't care.
				}
			}
			$this->session_unsetAllData();
			foreach( $preservedData as $keep => $value ) {
				WmfFramework::setSessionValue( $keep, $value );
			}
			if ( $msg === '' ) {
				$this->logger->info( __FUNCTION__ . ": Reset session, nothing to preserve" );
			} else {
				$this->logger->info( __FUNCTION__ . ": Reset session, preserving the following keys: $msg" );
			}
		} else {
			//I'm sure we could put more here...
			$soft_reset = array (
				'order_id',
			);
			$donorData = $this->session_getData( 'Donor' );
			foreach ( $soft_reset as $reset_me ) {
				unset( $donorData[$reset_me] );
			}
			WmfFramework::setSessionValue( 'Donor', $donorData );
			$this->logger->info( __FUNCTION__ . ': Soft reset, order_id only' );
		}
	}

	/**
	 * Check to see if donor is making a repeated attempt that is incompatible
	 * with the previous attempt, such as a gateway changes.  Reset certain
	 * things if so.  Prevents order_id leakage, log spam, and recur problems.
	 * FIXME: this all has to be special cases because we need to compare
	 * session values with request values that are normalized by DonationData,
	 * and DonationData's idea of normalization includes some stuff we don't
	 * want to do yet, like assigning order ID and saving contribution tracking.
	 */
	protected function session_resetOnSwitch() {
		if ( $this->isBatchProcessor() ) {
			return;
		}
		$oldData = $this->session_getData( 'Donor' );
		if ( !is_array( $oldData ) ) {
			return;
		}

		// If the gateway has changed, reset everything
		$newGateway = $this->getIdentifier();
		if ( !empty( $oldData['gateway'] ) && $oldData['gateway'] !== $newGateway ) {
			$this->logger->info(
				"Gateway changed from {$oldData['gateway']} to $newGateway.  Resetting session."
			);
			$this->session_resetForNewAttempt( true );
			return;
		}

		// Now compare session with current request parameters
		// Reset submethod when method changes to avoid form mismatch errors
		if ( !empty( $oldData['payment_method'] ) && !empty( $oldData['payment_submethod'] ) ) {
			$newMethod = WmfFramework::getRequestValue( 'payment_method', null );
			if ( $newMethod ) {
				$parts = explode( '.', $newMethod );
				$newMethod = $parts[0];
				if ( $newMethod !== $oldData['payment_method'] ) {
					$this->logger->info(
						"Payment method changed from {$oldData['payment_method']} to $newMethod.  Unsetting submethod."
					);
					unset( $oldData['payment_submethod'] );
					WmfFramework::setSessionValue( 'Donor', $oldData );
				}
			}
		}

		// Don't reuse order IDs between recurring and non-recurring donations
		// Recurring is stored in session as '1' for true and '' for false
		// Only reset if there is an explicit querystring parameter.
		if ( isset( $oldData['recurring'] ) && !empty( $oldData['order_id'] ) ) {
			$newRecurring = '';
			$hasRecurParam = false;
			foreach( array( 'recurring_paypal', 'recurring' ) as $key ) {
				$newVal = WmfFramework::getRequestValue( $key, null );
				if ( $newVal !== null ) {
					$hasRecurParam = true;
				}
				if ( $newVal === '1' || $newVal === 'true' ) {
					$newRecurring = '1';
				}
			}
			if ( $hasRecurParam && ( $newRecurring !== $oldData['recurring'] ) ) {
				$this->logger->info(
					"Recurring changed from '{$oldData['recurring']}' to '$newRecurring'.  Unsetting order ID."
				);
				unset( $oldData['order_id'] );
				WmfFramework::setSessionValue( 'Donor', $oldData );
			}
		}
	}

	/**
	 * Add a form name (ffname) to this abridged history of where we've
	 * been in this session. This lets us do things like construct useful
	 * "back" links that won't crush all session everything.
	 * @param string $form_key The 'ffname' that the form layer uses to load a
	 * payments form. Additional: ffname maps to a first-level key in
	 * $wgDonationInterfaceAllowedHtmlForms
	 */
	public function session_pushFormName( $form_key ) {
		if ( !$form_key ) {
			return;
		}

		$this->session_ensure();

		$paymentForms = $this->session_getData( 'PaymentForms' );
		if ( !is_array( $paymentForms ) ) {
			$paymentForms = array();
		}

		//don't want duplicates
		if ( $this->session_getLastFormName() != $form_key ) {
			$paymentForms[] = $form_key;
			WmfFramework::setSessionValue( 'PaymentForms', $paymentForms );
		}
	}

	/**
	 * Get the 'ffname' of the last payment form that successfully loaded
	 * for this session.
	 * @return mixed ffname of the last valid payments form if there is one,
	 * otherwise false.
	 */
	public function session_getLastFormName() {
		$this->session_ensure();
		$paymentForms = $this->session_getData( 'PaymentForms' );
		if ( !is_array( $paymentForms ) ) {
			return false;
		}
		$ffname = end( $paymentForms );
		if ( !$ffname ) {
			return false;
		}
		$data = $this->getData_Unstaged_Escaped();
		//have to check to see if the last loaded form is *still* valid.
		if ( GatewayFormChooser::isValidForm( $ffname, $data ) ) {
			return $ffname;
		} else {
			return false;
		}
	}

	/**
	 * token_applyMD5AndSalt
	 * Takes a clear-text token, and returns the MD5'd result of the token plus
	 * the configured gateway salt.
	 * @param string $clear_token The original, unsalted, unencoded edit token.
	 * @return string The salted and MD5'd token.
	 */
	protected static function token_applyMD5AndSalt( $clear_token ) {
		$salt = self::getGlobal( 'Salt' );

		if ( is_array( $salt ) ) {
			$salt = implode( "|", $salt );
		}

		$salted = md5( $clear_token . $salt ) . User::EDIT_TOKEN_SUFFIX;
		return $salted;
	}

	/**
	 * token_generateToken
	 * Generate a random string to be used as an edit token.
	 * @param string $padding A string with which we could pad out the random hex
	 * further.
	 * @return string
	 */
	public static function token_generateToken( $padding = '' ) {
		$token = dechex( mt_rand() ) . dechex( mt_rand() );
		return md5( $token . $padding );
	}

	public function token_getSaltedSessionToken() {
		// make sure we have a session open for tracking a CSRF-prevention token
		$this->session_ensure();

		$tokenKey = self::getIdentifier() . 'EditToken';

		$token = WmfFramework::getSessionValue( $tokenKey );
		if ( is_null( $token ) ) {
			// generate unsalted token to place in the session
			$token = self::token_generateToken();
			WmfFramework::setSessionValue( $tokenKey, $token );
		}

		return self::token_applyMD5AndSalt( $token );
	}

	/**
	 * token_refreshAllTokenEverything
	 * In the case where we have an expired session (token mismatch), we go
	 * ahead and fix it for 'em for their next post. We do this by refreshing
	 * everything that has to do with the edit token.
	 */
	protected function token_refreshAllTokenEverything() {
		$unsalted = self::token_generateToken();
		$gateway_ident = self::getIdentifier();
		$this->session_ensure();
		WmfFramework::setSessionValue( $gateway_ident . 'EditToken', $unsalted );
		$salted = $this->token_getSaltedSessionToken();

		$this->addRequestData( array ( 'wmf_token' => $salted ) );
	}

	/**
	 * token_matchEditToken
	 * Determine the validity of a token by checking it against the salted
	 * version of the clear-text token we have already stored in the session.
	 * On failure, it resets the edit token both in the session and in the form,
	 * so they will match on the user's next load.
	 *
	 * @var string $val
	 * @return bool
	 */
	protected function token_matchEditToken( $val ) {
		// When fetching the token from the URL (like we do for Worldpay), the last
		// portion may be mangled by + being substituted for ' '. Normally this is
		// valid URL unescaping, but not in this case.
		$val = str_replace( ' ', '+', $val );

		// fetch a salted version of the session token
		$sessionSaltedToken = $this->token_getSaltedSessionToken();
		if ( $val != $sessionSaltedToken ) {
			$this->logger->debug( __FUNCTION__ . ": broken session data\n" );
			//and reset the token for next time.
			$this->token_refreshAllTokenEverything();
		}
		return $val === $sessionSaltedToken;
	}

	/**
	 * token_checkTokens
	 * The main function to check the salted and MD5'd token we should have
	 * saved and gathered from the request, against the clear-text token we
	 * should have saved to the user's session.
	 * token_getSaltedSessionToken() will start off the process if this is a
	 * first load, and there's no saved token in the session yet.
	 * @staticvar string $match
	 * @return bool
	 */
	protected function token_checkTokens() {
		static $match = null; //because we only want to do this once per load.

		if ( $match === null ) {
			// establish the edit token to prevent csrf
			$token = $this->token_getSaltedSessionToken();

			$this->logger->debug( 'editToken: ' . $token );

			// match token
			if ( !$this->dataObj->isSomething( 'wmf_token' ) ) {
				$this->addRequestData( array ( 'wmf_token' => $token ) );
			}
			$token_check = $this->getData_Unstaged_Escaped( 'wmf_token' );

			$match = $this->token_matchEditToken( $token_check );
			if ( $this->dataObj->wasPosted() ) {
				$this->logger->debug( 'Submitted edit token: ' . $token_check );
				$this->logger->debug( 'Token match: ' . ($match ? 'true' : 'false' ) );
			}
		}

		return $match;
	}

	/**
	 * buildOrderIDSources: Uses the 'alt_locations' array in the order id
	 * metadata, to build an array of all possible candidates for order_id.
	 * This will also weed out candidates that do not meet the
	 * gateway-specific data constraints for that field, and are therefore
	 * invalid.
	 *
	 * @TODO: Data Item Class. There should be a class that keeps track of
	 * the metadata for every field we use (everything that currently comes
	 * back from DonationData), that can be overridden per gateway. Revisit
	 * this in a more universal way when that time comes.
	 */
	public function buildOrderIDSources() {
		static $built = false;

		if ( $built && isset( $this->order_id_candidates ) ) { //once per request is plenty
			return;
		}

		//pull all order ids and variants from all their usual locations
		$locations = array (
			'request' => 'order_id',
			'session' => array ( 'Donor' => 'order_id' ),
		);

		$alt_locations = $this->getOrderIDMeta( 'alt_locations' );
		if ( $alt_locations && is_array( $alt_locations ) ) {
			foreach ( $alt_locations as $var => $key ) {
				$locations[$var] = $key;
			}
		}

		if ( $this->isBatchProcessor() ) {
			// Can't use request or session from here.
			$locations = array_diff_key( $locations, array_flip( array(
				'request',
				'session',
			) ) );
		}

		//Now pull all the locations and populate the candidate array.
		$oid_candidates = array ( );

		foreach ( $locations as $var => $key ) {
			switch ( $var ) {
				case "request" :
					$value = WmfFramework::getRequestValue( $key, '' );
					if ( $value !== '' ) {
						$oid_candidates[$var] = $value;
					}
					break;
				case "session" :
					if ( is_array( $key ) ) {
						foreach ( $key as $subkey => $subvalue ) {
							$parentVal = WmfFramework::getSessionValue( $subkey );
							if ( is_array( $parentVal ) && array_key_exists( $subvalue, $parentVal ) ) {
								$oid_candidates['session' . $subkey . $subvalue] = $parentVal[$subvalue];
							}
						}
					} else {
						$val = WmfFramework::getSessionValue( $key );
						if ( !is_null( $val ) ) {
							$oid_candidates[$var] = $val;
						}
					}
					break;
				default :
					if ( !is_array( $key ) && array_key_exists( $key, $$var ) ) {
						//simple case first. This is a direct key in $var.
						$oid_candidates[$var] = $$var[$key];
					}
					if ( is_array( $key ) ) {
						foreach ( $key as $subkey => $subvalue ) {
							if ( array_key_exists( $subkey, $$var ) && array_key_exists( $subvalue, $$var[$subkey] ) ) {
								$oid_candidates[$var . $subkey . $subvalue] = $$var[$subkey][$subvalue];
							}
						}
					}
					break;
			}
		}

		//unset every invalid candidate
		foreach ( $oid_candidates as $source => $value ) {
			if ( empty( $value ) || !$this->validateDataConstraintsMet( 'order_id', $value ) ) {
				unset( $oid_candidates[$source] );
			}
		}

		$this->order_id_candidates = $oid_candidates;
		$built = true;
	}

	public function getDataConstraints( $field ) {
		if ( array_key_exists( $field, $this->dataConstraints ) ) {
			return $this->dataConstraints[$field];
		}
		return array();
	}

	/**
	 * Validates that the gateway-specific data constraints for this field
	 * have been met.
	 * @param string $field The field name we're checking
	 * @param mixed $value The candidate value of the field we want to check
	 * @return boolean True if it's a valid value for that field, false if it isn't.
	 */
	function validateDataConstraintsMet( $field, $value ) {
		$met = true;

		if ( is_array( $this->dataConstraints ) && array_key_exists( $field, $this->dataConstraints ) ) {
			$type = $this->dataConstraints[$field]['type'];
			$length = $this->dataConstraints[$field]['length'];
			switch ( $type ) {
				case 'numeric' :
					//@TODO: Determine why the DataValidator's type validation functions are protected.
					//There is no good answer, use those.
					//In fact, we should probably just port the whole thing over there. Derp.
					if ( !is_numeric( $value ) ) {
						$met = false;
					} elseif ( $field === 'order_id' && $this->getOrderIDMeta( 'disallow_decimals' ) ) { //haaaaaack...
						//it's a numeric string, so all the number functions (like is_float) always return false. Because, string.
						if ( strpos( $value, '.' ) !== false ) {
							//we don't want decimals. Something is wrong. Regen.
							$met = false;
						}
					}
					break;
				case 'alphanumeric' :
					//TODO: Something better here.
					break;
				default:
					//fail closed.
					$met = false;
			}

			if ( strlen( $value ) > $length ) {
				$met = false;
			}
		}
		return $met;
	}

	/**
	 * This function is meant to be run by the DonationData class, both
	 * before and after any communication has been done that might retrieve
	 * an order ID.
	 * To put it another way: If we are meant to be getting the OrderID from
	 * a piece of gateway communication that hasn't been done yet, this
	 * should return NULL. I think.
	 * @param string $override The pre-determined value of order_id.
	 * When you want to normalize an order_id to something you have already
	 * sorted out (anything running in batch mode is a good candidate - you
	 * have probably grabbed a preexisting order_id from some external data
	 * source in that case), short-circuit the hunting process and just take
	 * the override's word for order_id's final value.
	 * Also used when receiving the order_id from external sources
	 * (example: An API response)
	 *
	 * @param DonationData $dataObj Reference to the donation data object when
	 * we're creating the order ID in the constructor of the object (and thus
	 * do not yet have a reference to it.)
	 * @return string The normalized value of order_id
	 */
	public function normalizeOrderID( $override = null, $dataObj = null ) {
		$selected = false;
		$source = null;
		$value = null;
		if ( !is_null( $override ) && $this->validateDataConstraintsMet( 'order_id', $override ) ) {
			//just do it.
			$selected = true;
			$source = 'override';
			$value = $override;
		} else {
			//we are not overriding. Exit if we've been here before and decided something.
			if ( $this->getOrderIDMeta( 'final' ) ) {
				return $this->getOrderIDMeta( 'final' );
			}
		}

		$this->buildOrderIDSources(); //make sure all possible preexisting data is ready to go

		//If there's anything in the candidate array, take it. It's already in default order of preference.
		if ( !$selected && is_array( $this->order_id_candidates ) && !empty( $this->order_id_candidates ) ) {
			$selected = true;
			reset( $this->order_id_candidates );
			$source = key( $this->order_id_candidates );
			$value = $this->order_id_candidates[$source];
		}

		if ( !$selected && !array_key_exists( 'generated', $this->order_id_candidates ) && $this->getOrderIDMeta( 'generate' ) ) {
			$selected = true;
			$source = 'generated';
			$value = $this->generateOrderID( $dataObj );
			$this->order_id_candidates[$source] = $value; //so we don't regen accidentally
		}

		if ( $selected ) {
			$this->setOrderIDMeta( 'final', $value );
			$this->setOrderIDMeta( 'final_source', $source );
			return $value;
		} elseif ( $this->getOrderIDMeta( 'generate' ) ) {
			//I'd dump the whole oid meta array here, but it's pretty much guaranteed to be empty if we're here at all.
			$this->logger->error( __FUNCTION__ . ": Unable to determine what oid to use, in generate mode." );
		}

		return null;
	}

	/**
	 * Default orderID generation
	 * This used to be done in DonationData, but gateways should control
	 * the format here. Override this in child classes.
	 *
	 * @param DonationData $dataObj Reference to the donation data object
	 * when we are forced to create the order ID during construction of it
	 * and thus do not already have a reference. THIS IS A HACK! /me vomits
	 *
	 * @return int A freshly generated order ID
	 */
	public function generateOrderID( $dataObj = null ) {
		if ( $this->getOrderIDMeta( 'ct_id' ) ) {
			// This option means use the contribution tracking ID with the
			// sequence number tacked on to the end for uniqueness
			$dataObj = ( $dataObj ) ?: $this->dataObj;

			$ctid = $dataObj->getVal( 'contribution_tracking_id' );
			if ( !$ctid ) {
				$ctid = $dataObj->saveContributionTrackingData();
			}

			$this->session_ensure();
			$sequence = $this->session_getData( 'sequence' ) ?: 0;

			return "{$ctid}.{$sequence}";
		}
		$order_id = ( string ) mt_rand( 1000, 9999999999 );
		return $order_id;
	}

	public function regenerateOrderID() {
		$id = null;
		if ( $this->getOrderIDMeta( 'generate' ) ) {
			$id = $this->generateOrderID(); // should we pass $this->dataObj?
			$source = 'regenerated';  //This implies the try number is > 1.
			$this->order_id_candidates[$source] = $id;
			//alter the meta with the new data
			$this->setOrderIDMeta( 'final', $id );
			$this->setOrderIDMeta( 'final_source', 'regenerated' );
		} else {
			//we are not regenerating ourselves, but we need a new one...
			//so, blank it and wait.
			$this->order_id_candidates = array ( );
			unset( $this->order_id_meta['final'] );
			unset( $this->order_id_meta['final_source'] );
		}

		//tell DonationData about it
		$this->addRequestData( array ( 'order_id' => $id ) );
		// Add new Order ID to the session.
		$this->session_addDonorData();
		return $id;
	}

	public function getOrderIDMeta( $key = false ) {
		$data = $this->order_id_meta;
		if ( !is_array( $data ) ) {
			return false;
		}

		if ( $key ) {
			//just return the key if it exists
			if ( array_key_exists( $key, $data ) ) {
				return $data[$key];
			}
			return false;
		} else {
			return $data;
		}
	}

	/**
	 * sets more orderID Meta, so we can remember things about what we chose
	 * to go with in later logic.
	 * @param string $key The key to set.
	 * @param mixed $value The value to set.
	 */
	public function setOrderIDMeta( $key, $value ) {
		$this->order_id_meta[$key] = $value;
	}

	public function getPaymentMethodMeta( $payment_method = null ) {
		if ( $payment_method === null ) {
			$payment_method = $this->getPaymentMethod();
		}

		if ( isset( $this->payment_methods[ $payment_method ] ) ) {

			return $this->payment_methods[ $payment_method ];
		}
		else {
			$message = "The payment method [{$payment_method}] was not found.";
			throw new OutOfBoundsException( $message );
		}
	}

	public function getPaymentSubmethodMeta( $payment_submethod = null ) {
		if ( is_null( $payment_submethod ) ) {
			$payment_submethod = $this->getPaymentSubmethod();
		}

		if ( isset( $this->payment_submethods[ $payment_submethod ] ) ) {
			$this->logger->debug( 'Getting metadata for payment submethod: ' . ( string ) $payment_submethod );

			// Ensure that the validation index is set.
			if ( !isset( $this->payment_submethods[ $payment_submethod ]['validation'] ) ) {
				$this->payment_submethods[ $payment_submethod ]['validation'] = array();
			}

			return $this->payment_submethods[ $payment_submethod ];
		}
		else {
			$msg = "The payment submethod [{$payment_submethod}] was not found.";
			$this->logger->error( $msg );
			throw new OutOfBoundsException( $msg );
		}
	}

	/**
	 * Get metadata for all available submethods, given current method / country
	 * TODO: A PaymentMethod should be able to list its child options.  Probably
	 * still need some gateway-specific logic to prune the list by country and
	 * currency.
	 * TODO: Make it possible to override availability by currency and currency
	 * in LocalSettings.  Idea: same metadata array structure as used in
	 * definePaymentMethods, overrides cascade from
	 * methodMeta -> submethodMeta -> settingsMethodMeta -> settingsSubmethodMeta
	 * @return array with available submethods
	 *	'visa' => array( 'label' => 'Visa' )
	 */
	function getAvailableSubmethods() {
		$method = $this->getPaymentMethod();

		$submethods = array();
		foreach( $this->payment_submethods as $key => $available_submethod ) {
			$group = $available_submethod['group'];
			if ( $method !== $group ) {
				continue; // skip anything not part of the selected method
			}
			if (
				$this->unstaged_data // need data for country filter
				&& isset( $available_submethod['countries'] )
				// if the list exists, the current country key needs to exist and have a true value
				&& empty( $available_submethod['countries'][$this->getData_Unstaged_Escaped( 'country' )] )
			) {
				continue; // skip 'em if they're not allowed round here
			}
			$submethods[$key] = $available_submethod;
		}
		return $submethods;
	}

	/**
	 * Returns some useful debugging JSON we can append to loglines for
	 * increaded debugging happiness.
	 * This is working pretty well for debugging FormChooser problems, so
	 * let's use it other places. Still, this should probably still be used
	 * sparingly...
	 * @return string JSON-encoded donation data
	 */
	public function getLogDebugJSON() {
		$logObj = array (
			'amount',
			'ffname',
			'country',
			'currency',
			'payment_method',
			'payment_submethod',
			'recurring',
			'gateway',
			'utm_source',
			'referrer',
		);

		foreach ( $logObj as $key => $value ) {
			$logObj[$value] = $this->getData_Unstaged_Escaped( $value );
			unset( $logObj[$key] );
		}

		return json_encode( $logObj );
	}

	protected function logPaymentDetails( $preface = self::REDIRECT_PREFACE ) {
		$details = $this->getQueueDonationMessage();
		$json = json_encode( $details );
		$this->logger->info( $preface . $json );
	}

	protected function logCompletedPayment() {
		if ( $this->getGlobal( 'LogCompleted' ) ) {
			$this->logPaymentDetails( self::COMPLETED_PREFACE );
		}
	}

	protected function runSessionVelocityFilter() {
		$result = Gateway_Extras_SessionVelocityFilter::onProcessorApiCall( $this );

		if ( $result == false ) {
			$this->logger->info( 'Processor API call aborted on Session Velocity filter' );
			$this->setValidationAction( 'reject' );
		}
		return $result;
	}

	/**
	 * Returns an array of rules used to validate data before submission.
	 * Each entry's key should correspond to the id of the target field, and
	 * the value should be a list of rules with keys as described in
	 * @see ClientSideValidationHelper::getClientSideValidation
	 */
	public function getClientSideValidationRules() {
		// Start with the server required field validations.
		$requiredRules = array();
		foreach ( $this->getRequiredFields() as $field ) {
			$key = 'donate_interface-error-msg-' . $field;
			$requiredRules[$field] = array(
				array(
					'required' => true,
					'messageKey' => $key,
				)
			);
		};

		$transformerRules = array();
		foreach ( $this->data_transformers as $transformer ) {
			if ( $transformer instanceof ClientSideValidationHelper ) {
				$transformer->getClientSideValidation(
					$this->unstaged_data,
					$transformerRules
				);
			}
		}
		return array_merge_recursive( $requiredRules, $transformerRules );
	}

	/**
	 * @return PaymentTransactionResponse
	 */
	protected function getFailedValidationResponse() {
		$return = new PaymentTransactionResponse();
		$return->setCommunicationStatus( false );
		$return->setMessage( 'Failed data validation' );
		foreach ( $this->errorState->getErrors() as $error ) {
			$return->addError( $error );
		}
		return $return;
	}
}
