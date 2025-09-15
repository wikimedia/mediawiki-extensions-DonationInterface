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
use MediaWiki\Config\Config;
use MediaWiki\Request\WebRequest;
use MediaWiki\Session\SessionManager;
use MediaWiki\Session\Token;
use Psr\Log\LogLevel;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\PaymentError;
use SmashPig\Core\UtcDate;
use SmashPig\Core\ValidationError;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\ReferenceData\CurrencyRates;
use SmashPig\PaymentData\ReferenceData\NationalCurrencies;
use SmashPig\PaymentData\ValidationAction;

/**
 * GatewayAdapter
 */
abstract class GatewayAdapter implements GatewayType {
	/**
	 * Don't change these strings without fixing cross-repo usages.
	 */
	const REDIRECT_PREFACE = 'Redirecting for transaction: ';
	const COMPLETED_PREFACE = 'Completed donation: ';

	/**
	 * config tree
	 * @var array
	 */
	protected $config = [];

	/**
	 * $dataConstraints provides information on how to handle variables.
	 *
	 * 	 <code>
	 * 		'account_holder'		=> [ 'type' => 'alphanumeric',		'length' => 50, ]
	 * 	 </code>
	 *
	 * @var array
	 */
	protected $dataConstraints = [];

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
	 * When formatted, each message key will be given the ProblemEmail global
	 * as a first parameter. Error messages that use other parameters should
	 * use the callable.
	 *
	 * @var array
	 */
	protected $error_map = [];

	/**
	 * $var_map maps gateway variables to client variables
	 *
	 * @var array
	 */
	protected $var_map = [];

	/** @var string */
	protected $account_name;
	/** @var array */
	protected $account_config;
	/** @var array */
	protected $accountInfo;
	/** @var array */
	protected $transactions;

	/**
	 * $payment_methods will be defined by the adapter.
	 *
	 * @var array
	 */
	protected $payment_methods = [];

	/**
	 * $payment_submethods will be defined by the adapter (or will default to an empty
	 * array if none are defined).
	 *
	 * @var array
	 */
	protected $payment_submethods = [];

	/** @var array */
	protected $staged_data;
	/** @var array */
	protected $unstaged_data;

	/**
	 * Data transformation helpers.  These implement the StagingHelper interface for now,
	 * and are responsible for staging and unstaging data.
	 * @var (StagingHelper|ValidationHelper)[]
	 */
	protected $data_transformers = [];

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
	 * @var string|false When the smoke clears, this should be set to one of the
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
	/** @var string */
	protected $action;
	/** @var float */
	protected $risk_score = 0;
	/** @var string[] */
	public $debugarray;

	/**
	 * A boolean that will tell us if we've posted to ourselves. A little more telling than
	 * WebRequest->wasPosted(), as something else could have posted to us.
	 * @var bool
	 */
	public $posted = false;

	// ALL OF THESE need to be redefined in the children. Much voodoo depends on the accuracy of these constants.
	const GATEWAY_NAME = 'Donation Gateway';
	const IDENTIFIER = 'donation';
	const GLOBAL_PREFIX = 'wgDonationGateway'; // ...for example.
	const DONOR = 'Donor';
	const DONOR_BKUP = 'Donor_BKUP';

	/** @var array */
	protected $order_id_candidates;
	/** @var array */
	protected $order_id_meta;

	/**
	 * Constructor
	 *
	 * @param array	$options
	 *   OPTIONAL - You may set options for testing
	 *   - external_data - array, data from unusual sources (such as test fixture)
	 * @see DonationData
	 */
	public function __construct( $options = [] ) {
		$defaults = [
			'external_data' => null,
			'variant' => null,
		];
		$options = array_merge( $defaults, $options );
		$this->errorState = new ErrorState();
		$this->logger = DonationLoggerFactory::getLogger( $this );
		$this->payment_init_logger = DonationLoggerFactory::getLogger( $this, '_payment_init' );

		$this->profiler = DonationLoggerFactory::getProfiler( $this );

		$this->logger->info( "Creating a new adapter of type: [{$this->getGatewayName()}]" );

		// The following needs to be set up before we initialize DonationData.
		// TODO: move the rest of the initialization here
		$this->loadConfig( $options['variant'] );
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
		$this->posted = ( $this->dataObj->wasPosted() && ( WmfFramework::getRequestValue( 'wmf_token', null ) !== null ) );

		$this->logBasicInfoAboutDonorWebRequest();

		$this->findAccount();
		$this->defineAccountInfo();
		$this->defineTransactions();
		$this->defineErrorMap();
		$this->defineVarMap();

		$this->setGatewayDefaults( $options );

		// FIXME: Same as above, don't validate or stage in the constructor.
		$this->validate();

		$this->stageData();
	}

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
	abstract protected function defineTransactions();

	/**
	 * defineAccountInfo needs to set up the $accountInfo array.
	 * Keys = the name (or node name) value in the gateway transaction
	 * Values = The actual values for those keys. Probably have to access a global or two. (use getGlobal()!)
	 */
	abstract protected function defineAccountInfo();

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
	 * 'alt_locations' (optional) => [ $dataset_name, $dataset_key ]
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
	abstract protected function defineOrderIDMeta();

	public function loadConfig( ?string $variant = null ) {
		$configurationReader = ConfigurationReader::createForGateway(
			static::getIdentifier(), $variant, WmfFramework::getConfig()
		);
		$this->config = $configurationReader->readConfiguration();
	}

	/** @inheritDoc */
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
	 * @return string
	 */
	protected function getProcessorUrl() {
		if ( !self::getGlobal( 'Test' ) ) {
			$url = self::getGlobal( 'URL' );
		} else {
			$url = self::getGlobal( 'TestingURL' );
		}
		return $url;
	}

	/**
	 * Sets up the $payment_methods array.
	 * Keys = unique name for this method
	 * Values = metadata about the method
	 *   'validation' should be an array whose keys are field names and
	 *                whose values indicate whether the field is required
	 * For legacy support.
	 * TODO replace with access to config structure
	 */
	public function definePaymentMethods() {
		// All adapters have payment_method(s)
		$this->payment_methods = $this->config['payment_methods'];
		// Some (Pay Pal) do not have any submethods.
		if ( isset( $this->config['payment_submethods'] ) ) {
			$this->payment_submethods = $this->config['payment_submethods'];
		}
	}

	/**
	 * defineVarMap needs to set up the $var_map array.
	 * Keys = the name (or node name) value in the gateway transaction
	 * Values = the mediawiki field name for the corresponding piece of data.
	 * TODO: see comment on definePaymentMethods
	 */
	protected function defineVarMap() {
		if ( isset( $this->config['var_map'] ) ) {
			$this->var_map = $this->config['var_map'];
		}
	}

	/**
	 * TODO: see comment on definePaymentMethods
	 */
	protected function defineDataConstraints() {
		if ( isset( $this->config['data_constraints'] ) ) {
			$this->dataConstraints = $this->config['data_constraints'];
		}
	}

	/**
	 * Define the message keys used to display errors to the user.  Should set
	 * @see $this->error_map to an array whose keys are error codes and whose
	 * values are i18n keys or callables that return a translated error message.
	 * Any unmapped error code will use 'donate_interface-processing-error'
	 * TODO: see comment on definePaymentMethods
	 */
	protected function defineErrorMap() {
		if ( isset( $this->config['error_map'] ) ) {
			$this->error_map = $this->config['error_map'];
		}
	}

	/**
	 * Sets up the $data_transformers array.
	 */
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

	/**
	 * FIXME: Not convinced we need this.
	 * @return array
	 */
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

		// TODO crazy logic to determine which account we want
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
	 * @return bool true if match, else false.
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
	 * specified and no value exists.
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

	/**
	 * A helper function to let us stash extra data after the form has been submitted.
	 *
	 * @param array $dataArray An associative array of data.
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
	 *
	 * Only keys that are included in $dataArray will be persisted to the stored
	 * normalized data. This prevents us from overwriting items which should
	 * only ever hold data determined in this extension, not data from the
	 * processor or return querystring.
	 */
	public function addResponseData( $dataArray ) {
		foreach ( $dataArray as $key => $value ) {
			$this->staged_data[$key] = $value;
		}

		$originalNormalizedData = $this->unstaged_data;
		$this->unstageData();

		// Only copy the affected values back into the normalized data.
		$newlyUnstagedData = [];
		foreach ( $dataArray as $key => $stagedValue ) {
			if ( array_key_exists( $key, $this->unstaged_data ) ) {
				$newlyUnstagedData[$key] = $this->unstaged_data[$key];
				$originalNormalizedData[$key] = $this->unstaged_data[$key];
			}
		}
		$this->logger->debug( "Adding response data: " . json_encode( $newlyUnstagedData ) );
		$this->dataObj->addData( $newlyUnstagedData );
		$this->unstaged_data = $originalNormalizedData;
	}

	/**
	 * Change the keys on this data from processor API names to normalized names.
	 *
	 * @param array $processor_data Response data with raw API keys
	 * @param array|null $key_map map processor keys to our keys, defaults to
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

		$staged_data = [];
		foreach ( $key_map as $their_key => $our_key ) {
			if ( isset( $processor_data[$their_key] ) ) {
				$staged_data[$our_key] = $processor_data[$their_key];
			} else {
				// TODO: do any callers care? $emptyVars[] = $their_key;
			}
		}
		return $staged_data;
	}

	/** @inheritDoc */
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

	public function getDataSources(): array {
		return $this->dataObj->getDataSources();
	}

	/** @inheritDoc */
	public static function getGlobal( $varname ) {
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
	 * @param string $varname Name of setting to retrieve
	 * @return string Localized setting
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
	 * @param string $code The error code to look up in the map
	 * @param array $options
	 * @return array|string Returns @see GatewayAdapter::$error_map
	 */
	public function getErrorMap( $code, $options = [] ) {
		$defaults = [
			'translate' => false,
		];
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
				// FIXME: not respecting when $options['translate'] = false
				return $mapped();
			}
			$messageKey = $mapped;
		} else {
			// If the $code does not exist, use the default message
			$messageKey = 'donate_interface-processing-error';
		}

		$translatedMessage = ( $options['translate'] && $translatedMessage === '' )
			? WmfFramework::formatMessage( $messageKey, $this->getGlobal( 'ProblemsEmail' ) )
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
	 * @param string $code The error code to look up in the map
	 *
	 * @return string Returns the translated message from @see GatewayAdapter::$error_map
	 */
	public function getErrorMapByCodeAndTranslate( $code ) {
		return $this->getErrorMap( $code, [ 'translate' => true, ] );
	}

	/**
	 * This function is used exclusively by the two functions that build
	 * requests to be sent directly to external payment gateway servers. As
	 * such, unless a valid current transaction has already been set, this
	 * will error out rather hard.
	 * In other words: In all likelihood, this is not the function you're
	 * looking for.
	 * @param string $gateway_field_name The GATEWAY's field name that we are
	 * hoping to populate. Probably not even remotely the way we name the same
	 * data internally.
	 * @throws LogicException
	 * @return mixed The value we want to send directly to the gateway, for the specified gateway field name.
	 */
	public function getTransactionSpecificValue( $gateway_field_name ) {
		if ( !$this->transactions ) {
			$msg = self::getGatewayName() . ': Transactions structure is empty! No transaction can be constructed.';
			$this->logger->critical( $msg );
			throw new LogicException( $msg );
		}
		// Ensures we are using the correct transaction structure for our various lookups.
		$transaction = $this->getCurrentTransaction();

		if ( !$transaction ) {
			return null;
		}

		// If there's a hard-coded value in the transaction definition, use that.
		if ( array_key_exists( $transaction, $this->transactions ) && is_array( $this->transactions[$transaction] ) &&
			array_key_exists( 'values', $this->transactions[$transaction] ) &&
			array_key_exists( $gateway_field_name, $this->transactions[$transaction]['values'] ) ) {
			// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
			$value = $this->transactions[$transaction]['values'][$gateway_field_name];
			return $this->trimFieldToConstraints( $value, $gateway_field_name );
		}

		// if it's account info, use that.
		// $this->accountInfo;
		if ( array_key_exists( $gateway_field_name, $this->accountInfo ) ) {
			return $this->accountInfo[$gateway_field_name];
		}

		// If there's a value in the post data (name-translated by the var_map), use that.
		if ( array_key_exists( $gateway_field_name, $this->var_map ) ) {
			$staged = $this->getData_Staged( $this->var_map[$gateway_field_name] );
			if ( $staged !== null ) {
				// if it was sent, use that.
				return $staged;
			} else {
				// return blank string
				return '';
			}
		}

		// not in the map, or hard coded. What then?
		// Complain furiously, for your code is faulty.
		// TODO maybe just assume the name doesn't need to be var_mapped?
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

		if ( !$this->transactions ||
			!array_key_exists( $transaction, $this->transactions ) ||
			!array_key_exists( 'request', $this->transactions[$transaction] )
		) {
			$msg = self::getGatewayName() . ": $transaction request structure is empty! No transaction can be constructed.";
			$this->logger->critical( $msg );
			throw new LogicException( $msg );
		}

		return $this->transactions[$transaction]['request'];
	}

	protected function buildRequestArray(): array {
		// Look up the request structure for our current transaction type in the transactions array
		$structure = $this->getTransactionRequestStructure();
		if ( !is_array( $structure ) ) {
			return [];
		}
		$callback = [ $this, 'getTransactionSpecificValue' ];
		return ArrayHelper::buildRequestArray( $callback, $structure );
	}

	protected function setFailedValidationTransactionResponse( string $transaction, string $phase = 'pre-process' ): PaymentTransactionResponse {
		$this->logger->info( "Failed $phase checks for transaction type $transaction." );
		if ( !$this->transaction_response ) {
			$this->transaction_response = new PaymentTransactionResponse();
		}
		$this->transaction_response->setCommunicationStatus( false );
		$this->transaction_response->setMessage( $this->getErrorMapByCodeAndTranslate( 'internal-0000' ) );
		$this->transaction_response->addError(
			new PaymentError(
				'internal-0000',
				"Failed $phase checks for transaction type $transaction.",
				LogLevel::INFO
			)
		);
		return $this->transaction_response;
	}

	/**
	 * Sets the transaction you are about to send to the payment gateway. This
	 * will throw an exception if you try to set it to something that has no
	 * transaction definition.
	 * @param string $transaction_name This is a specific transaction type like
	 * 'createPaymentSession' (if you're Ingenico) that maps to a
	 * first-level key in the $transactions array.
	 * @throws UnexpectedValueException
	 */
	public function setCurrentTransaction( $transaction_name ) {
		if ( !$this->transactions ||
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

	/**
	 * @return string|false
	 */
	public function getCurrentTransaction() {
		if ( $this->current_transaction === null ) {
			return false;
		} else {
			return $this->current_transaction;
		}
	}

	/**
	 * Get the payment method
	 *
	 * @return string
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
	 * @return array Returns the available payment methods for the specific adapter
	 */
	public function getPaymentMethods() {
		return $this->payment_methods;
	}

	/**
	 * Get the default payment method from payment_methods.yaml
	 *
	 * @return string
	 */
	public function getDefaultPaymentMethod() {
		foreach ( $this->payment_methods as $payment_method => $object ) {
			if ( isset( $object['is_default'] ) ) {
				return $payment_method;
			}
		}
		return '';
	}

	public function getPaymentSubmethod(): string {
		return $this->getData_Unstaged_Escaped( 'payment_submethod' );
	}

	public function getPaymentSubmethods(): array {
		return $this->payment_submethods;
	}

	/**
	 * Called in the constructor, this function should be used to define
	 * pieces of default data particular to the gateway. It will be up to
	 * the child class to poke the data through to the data object
	 * (probably with $this->addRequestData()).
	 * DO NOT set default payment informati
	 * on here (or anywhere, really).
	 * That would be naughty.
	 * @param array $options associative array of values as given to the
	 *  GateWayType constructor.
	 */
	protected function setGatewayDefaults( $options = [] ) {
	}

	/**
	 * Add donation rules for the users country & currency combo.
	 *
	 * @return array
	 */
	public function getDonationRules(): array {
		$rules = $this->config['donation_rules'];
		foreach ( $rules as $rule ) {
			// Do our $params match all the conditions for this rule?
			$ruleMatches = true;
			if ( isset( $rule['conditions'] ) ) {
				// Loop over all the conditions looking for any that don't match
				foreach ( $rule['conditions'] as $conditionName => $conditionValue ) {
					$realValue = $this->getData_Unstaged_Escaped( $conditionName );
					// If the key of a condition is not in the params, the rule does not match like recurring
					if ( $realValue === null ) {
						$ruleMatches = false;
						break;
					}
					// Condition value is a scalar, just check it against the param value like country or payment_method
					if ( $realValue == $conditionValue ) {
						continue;
					} else {
						$ruleMatches = false;
						break;
					}
				}
			}
			if ( $ruleMatches ) {
				return $rule;
			}
		}
		$this->logger->warning( "Please set a default rule in donation_rules.yaml" );
		return [];
	}

	/** @inheritDoc */
	public function getCurrencies( $options = [] ) {
		return $this->config['currencies'];
	}

	public function logPending() {
		// Write the donor's details to the log for the audit processor
		$this->logPaymentDetails();
		// Feed the message into the pending queue, so the CRM queue consumer
		// can read it to fill in donor details when it gets a partial message
		$this->sendPendingMessage();
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
	 * @return int|string|null Order id
	 */
	public function getRequestProcessId( $requestValues ) {
		return null;
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
	 * @param string $stringToCheck
	 * @return int|mixed
	 */
	public function calculateKeyMashScore( $stringToCheck ) {
		$letters = str_split( strtolower( $stringToCheck ) );
		$rules = $this->getGlobal( 'NameFilterRules' );
		$score = 0;

		foreach ( $rules as $rule ) {
			$keyMapA = $rule['KeyMapA'];
			$keyMapB = $rule['KeyMapB'];

			$gibberishWeight = $rule['GibberishWeight'];

			$minimumLength = $rule['MinimumLength'];

			$failScore = $rule['Score'];

			$points = 0;

			if ( is_array( $letters ) && count( $letters ) > $minimumLength ) {
				foreach ( $letters as $letter ) {
					// For each char in zone A add a point, zone B subtract.
					if ( in_array( $letter, $keyMapA ) ) {
						$points++;
					}
					if ( in_array( $letter, $keyMapB ) ) {
						$points--;
					}
				}

				if ( abs( $points ) / count( $letters ) >= $gibberishWeight ) {
					$score += $failScore;
				}
			}
		}
		return $score;
	}

	/** @inheritDoc */
	public static function getGatewayName() {
		$c = get_called_class();
		return $c::GATEWAY_NAME;
	}

	public static function getGlobalPrefix(): string {
		$c = get_called_class();
		return $c::GLOBAL_PREFIX;
	}

	/** @inheritDoc */
	public static function getIdentifier() {
		$c = get_called_class();
		return $c::IDENTIFIER;
	}

	/** @inheritDoc */
	public static function getLogIdentifier() {
		return self::getIdentifier() . '_gateway';
	}

	/**
	 * Return an array of all the currently enabled gateways.
	 *
	 * @param Config $mwConfig MediaWiki Config
	 *
	 * @return array of gateway identifiers.
	 */
	public static function getEnabledGateways( Config $mwConfig ): array {
		$gatewayClasses = $mwConfig->get( 'DonationInterfaceGatewayAdapters' );

		$enabledGateways = [];
		foreach ( $gatewayClasses as $identifier => $gatewayClass ) {
			if ( $gatewayClass::getGlobal( 'Enabled' ) ) {
				$enabledGateways[] = $identifier;
			}
		}
		return $enabledGateways;
	}

	/**
	 * Sends a queue message to the configured server and queue, based on the
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
	protected function doQueueTransaction() {
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

	protected function getQueueContactMessage(): array {
		$queueMessage = [];
		foreach ( DonationData::getContactFields() as $field ) {
			$queueMessage[$field] = $this->getData_Unstaged_Escaped( $field );
		}
		$queueMessage = $this->addContactMessageFields( $queueMessage );
		return $queueMessage;
	}

	/**
	 * Collect donation details and normalize keys for pending or
	 * donations queue
	 *
	 * @return array
	 */
	protected function getQueueDonationMessage(): array {
		$gatewayTxnId = $this->getData_Unstaged_Escaped( 'gateway_txn_id' );
		if ( $gatewayTxnId === null ) {
			$gatewayTxnId = false;
		}
		$queueMessage = [
			'gateway_txn_id' => $gatewayTxnId,
			'response' => $this->getTransactionMessage(),
			'gateway_account' => $this->account_name,
			'fee' => 0, // FIXME: don't we know this for some gateways?
		];

		$messageKeys = DonationData::getMessageFields();

		// only includes these keys if recurring = 1
		$recurringKeys = [
			'recurring_payment_token',
			'processor_contact_id',
			'fiscal_number'
		];

		$requiredKeys = [
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
		];

		$remapKeys = [
			'amount' => 'gross',
		];

		// Add the rest of the relevant data
		// FIXME: This is "normalized" data.  We should refer to it as such,
		// and rename the getData_Unstaged_Escaped function.
		$data = $this->getData_Unstaged_Escaped();
		$isRecurring = $data['recurring'];

		foreach ( $messageKeys as $key ) {
			// skip the keys in the queueMessage in recurring keys when we are not doing recurring
			if ( !$isRecurring && in_array( $key, $recurringKeys ) ) {
				continue;
			}

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
				$queueMessage[$remapKeys[$key]] = Amount::round( (float)$value, $data['currency'] );
			} else {
				$queueMessage[$key] = $value;
			}
		}

		// FIXME: Note that we're not using any existing date or ts fields.  Why is that?
		$queueMessage['date'] = time();

		$queueMessage = $this->addContactMessageFields( $queueMessage );
		return $queueMessage;
	}

	/**
	 * IMPORTANT: only add the contact_id to a message if the contact_hash
	 * is preset. We don't want to allow overwriting arbitrary CiviCRM
	 * contacts.
	 *
	 * @param array $message
	 * @return array
	 */
	protected function addContactMessageFields( $message ) {
		$contactId = $this->getData_Unstaged_Escaped( 'contact_id' );
		$contactHash = $this->getData_Unstaged_Escaped( 'contact_hash' );
		if ( $contactId && $contactHash ) {
			$message['contact_id'] = $contactId;
			$message['contact_hash'] = $contactHash;
		}
		return $message;
	}

	/** @inheritDoc */
	public function addStandardMessageFields( $transaction ) {
		// basically, add all the stuff we have come to take for granted, because syslog.
		$transaction['date'] = UtcDate::getUtcTimestamp();
		$transaction['server'] = gethostname();

		$these_too = [
			'gateway',
			'gateway_txn_id',
			'contribution_tracking_id',
			'order_id',
			'payment_method',
		];
		foreach ( $these_too as $field ) {
			$transaction[$field] = $this->getData_Unstaged_Escaped( $field );
		}

		return $transaction;
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
			// Note: This is the very last resort. This should already have been dealt with thoroughly in staging.
			$this->staged_data[ $field ] = $this->trimFieldToConstraints( $value, $field );
		}
	}

	/**
	 * Trims a single field according to length constraints in data_constraints.yaml
	 *
	 * @param mixed $value
	 * @param string $field the name of the field specified in data_constraints
	 * @return mixed|string
	 */
	protected function trimFieldToConstraints( $value, $field ) {
		// Trim all values if they are a string
		$value = is_string( $value ) ? trim( $value ) : $value;

		if ( isset( $this->dataConstraints[$field] ) && is_string( $value ) ) {
			// Truncate the field if it has a length specified
			if ( isset( $this->dataConstraints[$field]['length'] ) ) {
				$length = (int)$this->dataConstraints[$field]['length'];
			} else {
				$length = false;
			}

			if ( $length && $value !== '' ) {
				$value = mb_substr( $value, 0, $length, 'UTF-8' );
			}

		}
		return $value;
	}

	/** @inheritDoc */
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
	 * @inheritDoc
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
			case FinalStatus::CANCELLED:
			case FinalStatus::REVISED:
				if (
					$this->getData_Unstaged_Escaped( 'opt_in' ) == '1' &&
					$this->getData_Unstaged_Escaped( 'email' ) &&
					$this->getGlobal( 'SendOptInOnFailure' )
				) {
					// When a donation fails but the donor has opted in to emails,
					// just send the donor contact data to the opt-in queue.
					$this->pushMessage( 'opt-in', true );
				}
				break;
		}
		// If we're asking the donor to convert their donation to recurring,
		// don't delete everything from session just yet.
		if ( $this->showMonthlyConvert() ) {
			// @phan-suppress-next-line PhanUndeclaredMethod RecurringConversion is checked in the if condition
			$this->session_MoveDonorDataToBackupForRecurringConversion( $force );
		} else {
			$this->session_resetForNewAttempt( $force );
		}

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

		// what do we want in here?
		// Attempted payment type, country of origin, $status, amount... campaign?
		// error message if one exists.
		$keys = [
			'payment_submethod',
			'payment_method',
			'country',
			'utm_campaign',
			'amount',
			'currency',
		];

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
	 * @param string $status one of the constants in @see SmashPig\PaymentData\FinalStatus
	 */
	public function sendFinalStatusMessage( $status ) {
		$transaction = [
			'validation_action' => $this->getValidationAction(),
			'payments_final_status' => $status,
		];

		// add more keys here if you want it in the db equivalent of the payments-init queue.
		// for now, though, just taking the ones that make it to the logs.
		$keys = [
			'payment_submethod',
			'country',
			'amount',
			'currency',
		];

		foreach ( $keys as $key ) {
			$transaction[$key] = $this->getData_Unstaged_Escaped( $key );
		}

		$transaction = $this->addStandardMessageFields( $transaction );

		try {
			// FIXME: Dispatch "freeform" messages transparently as well.
			// TODO: write test
			$this->logger->info( 'Pushing transaction to payments-init queue.' );
			QueueWrapper::push( 'payments-init', $transaction );
		} catch ( Exception ) {
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
	 * Returns the FORMATTED data harvested from the reply, or false if it is not set.
	 * @return mixed An array of returned data, or false.
	 */
	public function getTransactionData() {
		if ( $this->transaction_response && $this->transaction_response->getData() ) {
			return $this->transaction_response->getData();
		}
		return false;
	}

	public function getGatewayAdapterClass(): string {
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
		$this->session_ensure();
		$attempts = $this->session_getData( 'numAttempt' ); // intentionally outside the 'Donor' key.
		if ( is_numeric( $attempts ) ) {
			$attempts += 1;
		} else {
			// assume garbage = 0, so...
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
		$sequence = $this->session_getData( 'sequence' ); // intentionally outside the 'Donor' key.
		if ( is_numeric( $sequence ) ) {
			$sequence += 1;
		} else {
			$sequence = 1;
		}

		WmfFramework::setSessionValue( 'sequence', $sequence );
	}

	/** @inheritDoc */
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
	public function runAntifraudFilters() {
		// extra layer of Stop Doing This.
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
	 * donationdata.php and/or LocalSettings.php, including the queue message.
	 * This function is most likely to be called through
	 * executeFunctionIfExists, later on in do_transaction.
	 */
	protected function postProcessDonation() {
		Gateway_Extras_ConversionLog::onPostProcess( $this );

		try {
			$this->doQueueTransaction();
		} catch ( Exception $ex ) {
			$this->logger->alert( "Failure queueing final status message: {$ex->getMessage()}" );
		}
	}

	/**
	 * NOTE: Adyen Checkout has it's own pushMessage that does not push recurring
	 * iDEALs to the donations queue
	 *
	 * @param string $queue What queue to send the message to
	 * @param bool $contactOnly If we only have the donor's contact information
	 */
	protected function pushMessage( $queue, $contactOnly = false ) {
		$this->logger->info( "Pushing transaction to queue [$queue]" );
		if ( $contactOnly ) {
			$message = $this->getQueueContactMessage();
		} else {
			$message = $this->getQueueDonationMessage();
		}
		QueueWrapper::push( $queue, $message );
	}

	protected function sendPendingMessage() {
		$order_id = $this->getData_Unstaged_Escaped( 'order_id' );
		$this->logger->info( "Sending donor details for $order_id to pending queue" );
		QueueWrapper::push( 'pending', $this->getQueueDonationMessage() );
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
			return null;
		}
		if ( array_key_exists( $option_value, $this->transactions[$transaction] ) ) {
			return $this->transactions[$transaction][$option_value];
		}
		return null;
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
	protected function refreshGatewayValueFromSource( $val ) {
		$refreshed = $this->dataObj->getVal( $val );
		if ( $refreshed !== null ) {
			$this->staged_data[$val] = $refreshed;
			$this->unstaged_data[$val] = $refreshed;
		} else {
			unset( $this->staged_data[$val] );
			unset( $this->unstaged_data[$val] );
		}
	}

	/** @inheritDoc */
	public function setRiskScore( $score ) {
		$this->risk_score = $score;
	}

	/** @inheritDoc */
	public function setValidationAction( $action, $reset = false ) {
		// our choices are:
		$actions = [
			ValidationAction::PROCESS => 0,
			ValidationAction::REVIEW => 1,
			ValidationAction::CHALLENGE => 2,
			ValidationAction::REJECT => 3,
		];
		if ( !isset( $actions[$action] ) ) {
			throw new UnexpectedValueException( "Action $action is invalid." );
		}

		if ( $reset ) {
			$this->action = $action;
			return;
		}

		if ( (int)$actions[$action] > (int)$actions[$this->getValidationAction()] ) {
			$this->action = $action;
		}
	}

	public function getValidationAction(): string {
		if ( !isset( $this->action ) ) {
			$this->action = ValidationAction::PROCESS;
		}
		return $this->action;
	}

	/**
	 * Build list of form fields
	 * TODO: Determine if this ever needs to be overridden per gateway, or if
	 * all the per-country / per-gateway cases can be expressed declaratively
	 * in payment method / submethod metadata.  If that's the case, move this
	 * function (to DataValidator?)
	 * @param array|null $knownData if provided, used to determine fields that
	 *  depend on country or payment method. Falls back to unstaged data.
	 * @return array of field names (empty if no payment method set)
	 */
	public function getFormFields( ?array $knownData = null ): array {
		if ( $knownData === null ) {
			$knownData = $this->getData_Unstaged_Escaped();
		}
		$fields = [];
		$fieldsConfig = [];

		// Add any country-specific required fields
		if (
			isset( $this->config['country_fields'] ) &&
			!empty( $knownData['country'] )
		) {
			$country = $knownData['country'];
			if ( isset( $this->config['country_fields'][$country] ) ) {
				$fieldsConfig = $this->config['country_fields'][$country];
			}
		}

		if ( !empty( $knownData['payment_method'] ) ) {
			$methodMeta = $this->getPaymentMethodMeta( $knownData['payment_method'] );
			if ( isset( $methodMeta['validation'] ) ) {
				$fieldsConfig = $methodMeta['validation'] + $fieldsConfig;
			}
		}

		if ( !empty( $knownData['payment_submethod'] ) ) {
			$submethodMeta = $this->getPaymentSubmethodMeta( $knownData['payment_submethod'], $knownData['payment_method'] ?? null );
			if ( isset( $submethodMeta['validation'] ) ) {
				// submethod validation can override method validation
				// TODO: child method anything should supersede parent method
				// anything, and PaymentMethod should handle that.
				$fieldsConfig = $submethodMeta['validation'] + $fieldsConfig;
			}
		}

		foreach ( $fieldsConfig as $fieldName => $requirementFlag ) {
			if ( $requirementFlag === false ) {
				continue;
			}

			switch ( $fieldName ) {
				case 'address':
					$field = [
						'street_address' => $requirementFlag,
						'city' => $requirementFlag,
						'country' => $requirementFlag,
						'postal_code' => $requirementFlag,
						// 'postal_code' this should really be added or removed, depending on the country and/or gateway requirements.
						// however, that's not happening in this class in the code I'm replacing, so...
						// TODO: Something clever in the DataValidator with data groups like these.
					];
					if ( !empty( $knownData['country'] ) ) {
						$country = $knownData['country'];
						if ( $country && Subdivisions::getByCountry( $country ) ) {
							$field['state_province'] = $requirementFlag;
						}
					}
					break;
				case 'name':
					$field = [
						'first_name' => $requirementFlag,
						'last_name'  => $requirementFlag
					];
					break;
				default:
					$field = [ $fieldName => $requirementFlag ];
					break;
			}
			$fields = array_merge( $fields, $field );
		}

		return $fields;
	}

	/**
	 * @param array|null $knownData
	 * @return array
	 */
	public function getRequiredFields( $knownData = null ) {
		$all_fields = $this->getFormFields( $knownData );
		$required_fields = array_filter( $all_fields, static function ( $val ) {
			return $val === true;
		} );

		return array_keys( $required_fields );
	}

	/**
	 * Check donation data for validity and set errors.
	 *
	 * This function will go through all the data we have pulled from wherever
	 * we've pulled it, and make sure it's safe and expected and everything.
	 * If it is not, it will return an array of errors ready for any
	 * DonationInterface form class derivative to display.
	 *
	 * @return bool true if validation passes
	 */
	public function validate() {
		$normalized = $this->dataObj->getData();

		if ( $this->transaction_option( 'check_required' ) ) {
			// The fields returned by getRequiredFields only make sense
			// for certain transactions. TODO: getRequiredFields should
			// actually return different things for different transactions
			$check_not_empty = $this->getRequiredFields();
		} else {
			$check_not_empty = [];
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

		// during validate, if payment method not defined, set to the default one
		if ( $this->getData_Unstaged_Escaped( 'payment_method' ) == null ) {
			$this->unstaged_data['payment_method'] = $this->getDefaultPaymentMethod();
			if ( $this->unstaged_data['utm_source'] == '..' ) {
				$this->unstaged_data['utm_source'] .= $this->unstaged_data['payment_method'];
			}
		}

		return $this->validatedOK();
	}

	/**
	 * @return bool True if submitted data is valid and sufficient to proceed to the next step.
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
			if ( is_numeric( $oldAmount ) && $oldAmount > 0 ) {
				$usdAmount = $oldAmount / $conversionRates[$oldCurrency];
			} else {
				$this->handleInvalidAmount( $oldAmount );
			}
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

		$formData = [
			'amount' => $newAmount,
			'currency' => $defaultCurrency,
		];
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
					[ $defaultCurrency ]
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
	 * @return int
	 */
	public function getScoreName() {
		$fName = $this->getData_Unstaged_Escaped( 'first_name' );
		$lName = $this->getData_Unstaged_Escaped( 'last_name' );

		$fullName = $fName . $lName;
		return $this->calculateKeyMashScore( $fullName );
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
	 * @return int
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
			$score = (int)$countryMap[ $country ];
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
	 * @return int
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
			$score = (int)$emailDomainMap[ $emailDomain ];
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
	 * @todo All these regex map matching functions that are identical with
	 * different internal var names are making me rilly mad. Collapse.
	 *
	 * How the score is tabulated:
	 *  - Add the score(value) associated with each regex(key) in the map var.
	 *
	 * @see $wgDonationInterfaceCustomFiltersFunctions
	 * @see $wgDonationInterfaceUtmCampaignMap
	 *
	 * @return int
	 */
	public function getScoreUtmCampaignMap() {
		$score = 0;

		$campaign = $this->getData_Unstaged_Escaped( 'utm_campaign' );
		$campaignMap = $this->getGlobal( 'UtmCampaignMap' );

		$msg = self::getGatewayName() . ': UTM Campaign map: '
			. print_r( $campaignMap, true );

		$this->logger->debug( $msg );

		// If any of the defined regex patterns match, add the points.
		if ( $campaign !== null && is_array( $campaignMap ) ) {
			foreach ( $campaignMap as $regex => $points ) {
				if ( preg_match( $regex, $campaign ) ) {
					$score = (int)$points;
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
	 * @todo Again. Regex map matching functions, identical, with minor
	 * internal var names. Collapse.
	 *
	 * How the score is tabulated:
	 *  - Add the score(value) associated with each regex(key) in the map var.
	 *
	 * @see $wgDonationInterfaceCustomFiltersFunctions
	 * @see $wgDonationInterfaceUtmMediumMap
	 *
	 * @return int
	 */
	public function getScoreUtmMediumMap() {
		$score = 0;

		$medium = $this->getData_Unstaged_Escaped( 'utm_medium' );
		$mediumMap = $this->getGlobal( 'UtmMediumMap' );

		$msg = self::getGatewayName() . ': UTM Medium map: '
			. print_r( $mediumMap, true );

		$this->logger->debug( $msg );

		// If any of the defined regex patterns match, add the points.
		if ( $medium !== null && is_array( $mediumMap ) ) {
			foreach ( $mediumMap as $regex => $points ) {
				if ( preg_match( $regex, $medium ) ) {
					$score = (int)$points;
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
	 * @todo Argharghargh, inflated code! Collapse!
	 *
	 * How the score is tabulated:
	 *  - Add the score(value) associated with each regex(key) in the map var.
	 *
	 * @see $wgDonationInterfaceCustomFiltersFunctions
	 * @see $wgDonationInterfaceUtmSourceMap
	 *
	 * @return int
	 */
	public function getScoreUtmSourceMap() {
		$score = 0;

		$source = $this->getData_Unstaged_Escaped( 'utm_source' );
		$sourceMap = $this->getGlobal( 'UtmSourceMap' );

		$msg = self::getGatewayName() . ': UTM Source map: '
			. print_r( $sourceMap, true );

		$this->logger->debug( $msg );

		// If any of the defined regex patterns match, add the points.
		if ( $source !== null && is_array( $sourceMap ) ) {
			foreach ( $sourceMap as $regex => $points ) {
				if ( preg_match( $regex, $source ) ) {
					$score = (int)$points;
				}
			}
		}

		// @see $wgDonationInterfaceDisplayDebug
		$this->debugarray[] = 'custom filters function: get utm source [ '
			. $source . ' ] score = ' . $score;

		return $score;
	}

	/** @inheritDoc */
	public function getAccountConfig( $key ) {
		return $this->account_config[$key];
	}

	public function session_ensure() {
		WmfFramework::setupSession();
	}

	/**
	 * Retrieve data from the session if it's set, and null if it's not.
	 * @param string $key The array key to return from the session.
	 * @param string|null $subkey Optional: The subkey to return from the session.
	 * Only really makes sense if $key is an array.
	 * @return mixed The session value if present, or null if it is not set.
	 */
	public function session_getData( $key, $subkey = null ) {
		$data = WmfFramework::getSessionValue( $key );
		if ( $data !== null ) {
			if ( $subkey === null ) {
				return $data;
			} elseif ( is_array( $data ) && array_key_exists( $subkey, $data ) ) {
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
	 * @return bool true if the session contains donor data
	 */
	public function session_hasDonorData() {
		return $this->session_getData( 'Donor' ) !== null;
	}

	/**
	 * Saves a backup of the Donor Data in session
	 *
	 * @param array $donorData
	 */
	public function session_setDonorBackupData( array $donorData ) {
		WmfFramework::setSessionValue( self::DONOR_BKUP, $donorData );
	}

	/**
	 * Unsets the session data, in the case that we've saved it for gateways
	 * like Ingenico that require it to persist over here through their
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
		$this->session_ensure();
		$sessionId = WmfFramework::getSessionId();
		$this->logger->info( __FUNCTION__ . ": Refreshing all donor data in session '$sessionId'" );
		$sessionFields = DonationData::getSessionFields();

		$data = [];
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
		$this->logger->debug( 'Killed all the session everything.' );
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
		$reset = $force;
		if ( $this->session_getData( 'numAttempt' ) > 3 ) {
			$reset = true;
			WmfFramework::setSessionValue( 'numAttempt', 0 );
		}

		if ( $reset ) {
			$this->logger->info( __FUNCTION__ . ': Unsetting session donor data' );
			$this->session_unsetDonorData();
			// leave the payment forms and antifraud data alone.
			// but, under no circumstances should the gateway edit
			// token appear in the preserve array...
			$preserveKeys = [
				'DonationInterface_SessVelocity',
				'PaymentForms',
				'numAttempt',
				'order_status', // for post-payment activities
				'sequence',
				'risk_scores',
				self::DONOR_BKUP,
				self::getIdentifier() . 'EditToken',
				'variant', // FIXME: this is actually a sub-key of Donor :(
			];
			$preservedData = [];
			$msg = '';
			foreach ( $preserveKeys as $keep ) {
				$value = WmfFramework::getSessionValue( $keep );
				if ( $value !== null ) {
					$preservedData[$keep] = $value;
					$msg .= "$keep, "; // always one extra comma; Don't care.
				}
			}
			$this->session_unsetAllData();
			foreach ( $preservedData as $keep => $value ) {
				WmfFramework::setSessionValue( $keep, $value );
			}
			if ( $msg === '' ) {
				$this->logger->info( __FUNCTION__ . ": Reset session, nothing to preserve" );
			} else {
				$this->logger->info( __FUNCTION__ . ": Reset session, preserving the following keys: $msg" );
			}
		} else {
			// I'm sure we could put more here...
			$soft_reset = [
				'order_id',
			];
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
	 * with the previous attempt, such as a gateway/country changes.  Reset certain
	 * things if so.  Prevents order_id leakage, log spam, and recur problems.
	 * FIXME: this all has to be special cases because we need to compare
	 * session values with request values that are normalized by DonationData,
	 * and DonationData's idea of normalization includes some stuff we don't
	 * want to do yet, like assigning order ID and saving contribution tracking.
	 */
	protected function session_resetOnSwitch() {
		$oldData = $this->session_getData( 'Donor' );
		if ( !is_array( $oldData ) ) {
			return;
		}

		// If the gateway has changed or country changed, reset everything
		$newGateway = $this->getIdentifier();
		$newCountry = WmfFramework::getRequestValue( 'country', null );
		if ( !empty( $oldData['gateway'] ) && $oldData['gateway'] !== $newGateway ) {
			$this->logger->info(
				"Gateway changed from {$oldData['gateway']} to $newGateway.  Resetting session."
			);
			$this->session_resetForNewAttempt( true );
			return;
		} elseif ( $newCountry && !empty( $oldData['country'] ) && $oldData['country'] !== $newCountry ) {
			$this->logger->info(
				"Country changed from {$oldData['country']} to $newCountry.  Resetting session."
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
			foreach ( [ 'recurring_paypal', 'recurring' ] as $key ) {
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
				// Order ID is derived from contribution tracking ID, so wipe them both
				// out to ensure we get fresh values.
				unset( $oldData['contribution_tracking_id'] );
				unset( $oldData['order_id'] );
				WmfFramework::setSessionValue( 'Donor', $oldData );
			}
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

		$salted = md5( $clear_token . $salt ) . Token::SUFFIX;
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

	public function token_getSaltedSessionToken(): string {
		// make sure we have a session open for tracking a CSRF-prevention token
		$this->session_ensure();

		$tokenKey = self::getIdentifier() . 'EditToken';

		$token = WmfFramework::getSessionValue( $tokenKey );
		if ( $token === null ) {
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

		$this->addRequestData( [ 'wmf_token' => $salted ] );
	}

	/**
	 * token_matchEditToken
	 * Determine the validity of a token by checking it against the salted
	 * version of the clear-text token we have already stored in the session.
	 * On failure, it resets the edit token both in the session and in the form,
	 * so they will match on the user's next load.
	 *
	 * @param string $val
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
			// and reset the token for next time.
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
	 * @return bool
	 */
	protected function token_checkTokens() {
		static $match = null; // because we only want to do this once per load.

		if ( $match === null ) {
			// establish the edit token to prevent csrf
			$token = $this->token_getSaltedSessionToken();

			$this->logger->debug( 'editToken: ' . $token );

			// match token
			if ( !$this->dataObj->isSomething( 'wmf_token' ) ) {
				$this->addRequestData( [ 'wmf_token' => $token ] );
			}
			$token_check = $this->getData_Unstaged_Escaped( 'wmf_token' );

			$match = $this->token_matchEditToken( $token_check );
			if ( $this->dataObj->wasPosted() ) {
				$this->logger->debug( 'Submitted edit token: ' . $token_check );
				$this->logger->debug( 'Token match: ' . ( $match ? 'true' : 'false' ) );
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
	 * @todo Data Item Class. There should be a class that keeps track of
	 * the metadata for every field we use (everything that currently comes
	 * back from DonationData), that can be overridden per gateway. Revisit
	 * this in a more universal way when that time comes.
	 */
	public function buildOrderIDSources() {
		static $built = false;

		if ( $built && isset( $this->order_id_candidates ) ) { // once per request is plenty
			return;
		}

		// pull all order ids and variants from all their usual locations
		$locations = [
			'request' => 'order_id',
			'session' => [ 'Donor' => 'order_id' ],
		];

		$alt_locations = $this->getOrderIDMeta( 'alt_locations' );
		if ( $alt_locations && is_array( $alt_locations ) ) {
			foreach ( $alt_locations as $var => $key ) {
				$locations[$var] = $key;
			}
		}

		// Now pull all the locations and populate the candidate array.
		$oid_candidates = [];

		foreach ( $locations as $var => $key ) {
			switch ( $var ) {
				case "request":
					$value = WmfFramework::getRequestValue( $key, '' );
					if ( $value !== '' ) {
						$oid_candidates[$var] = $value;
					}
					break;
				case "session":
					if ( is_array( $key ) ) {
						foreach ( $key as $subkey => $subvalue ) {
							$parentVal = WmfFramework::getSessionValue( $subkey );
							if ( is_array( $parentVal ) && array_key_exists( $subvalue, $parentVal ) ) {
								$oid_candidates['session' . $subkey . $subvalue] = $parentVal[$subvalue];
							}
						}
					} else {
						$val = WmfFramework::getSessionValue( $key );
						if ( $val !== null ) {
							$oid_candidates[$var] = $val;
						}
					}
					break;
				default:
					if ( !is_array( $key ) && array_key_exists( $key, $$var ) ) {
						// simple case first. This is a direct key in $var.
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

		// unset every invalid candidate
		foreach ( $oid_candidates as $source => $value ) {
			if ( !$value || !$this->validateDataConstraintsMet( 'order_id', $value ) ) {
				unset( $oid_candidates[$source] );
			}
		}

		$this->order_id_candidates = $oid_candidates;
		$built = true;
	}

	/** @inheritDoc */
	public function getDataConstraints( $field ) {
		if ( array_key_exists( $field, $this->dataConstraints ) ) {
			return $this->dataConstraints[$field];
		}
		return [];
	}

	/**
	 * Validates that the gateway-specific data constraints for this field
	 * have been met.
	 * @param string $field The field name we're checking
	 * @param mixed $value The candidate value of the field we want to check
	 * @return bool True if it's a valid value for that field, false if it isn't.
	 */
	protected function validateDataConstraintsMet( $field, $value ) {
		$met = true;

		if ( is_array( $this->dataConstraints ) && array_key_exists( $field, $this->dataConstraints ) ) {
			$type = $this->dataConstraints[$field]['type'];
			$length = $this->dataConstraints[$field]['length'];
			switch ( $type ) {
				case 'numeric':
					// @TODO: Determine why the DataValidator's type validation functions are protected.
					// There is no good answer, use those.
					// In fact, we should probably just port the whole thing over there. Derp.
					if ( !is_numeric( $value ) ) {
						$met = false;
					} elseif ( $field === 'order_id' && $this->getOrderIDMeta( 'disallow_decimals' ) ) { // haaaaaack...
						// it's a numeric string, so all the number functions (like is_float) always return false. Because, string.
						if ( strpos( $value, '.' ) !== false ) {
							// we don't want decimals. Something is wrong. Regen.
							$met = false;
						}
					}
					break;
				case 'alphanumeric':
					// TODO: Something better here.
					break;
				default:
					// fail closed.
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
	 * @param string|null $override The pre-determined value of order_id.
	 * When you want to normalize an order_id to something you have already
	 * sorted out, short-circuit the hunting process and just take
	 * the override's word for order_id's final value.
	 * Also used when receiving the order_id from external sources
	 * (example: An API response)
	 *
	 * @param DonationData|null $dataObj Reference to the donation data object when
	 * we're creating the order ID in the constructor of the object (and thus
	 * do not yet have a reference to it.)
	 * @return string|null The normalized value of order_id
	 */
	public function normalizeOrderID( $override = null, $dataObj = null ) {
		$selected = false;
		$source = null;
		$value = null;
		if ( $override !== null && $this->validateDataConstraintsMet( 'order_id', $override ) ) {
			// just do it.
			$selected = true;
			$source = 'override';
			$value = $override;
		} else {
			// we are not overriding. Exit if we've been here before and decided something.
			if ( $this->getOrderIDMeta( 'final' ) ) {
				return $this->getOrderIDMeta( 'final' );
			}
		}

		$this->buildOrderIDSources(); // make sure all possible preexisting data is ready to go

		// If there's anything in the candidate array, take it. It's already in default order of preference.
		if ( !$selected && is_array( $this->order_id_candidates ) && $this->order_id_candidates ) {
			$selected = true;
			reset( $this->order_id_candidates );
			$source = key( $this->order_id_candidates );
			$value = $this->order_id_candidates[$source];
		}

		if ( !$selected && !array_key_exists( 'generated', $this->order_id_candidates ) && $this->getOrderIDMeta( 'generate' ) ) {
			$selected = true;
			$source = 'generated';
			$value = $this->generateOrderID( $dataObj );
			$this->order_id_candidates[$source] = $value; // so we don't regen accidentally
		}

		if ( $selected ) {
			$this->setOrderIDMeta( 'final', $value );
			$this->setOrderIDMeta( 'final_source', $source );
			return $value;
		} elseif ( $this->getOrderIDMeta( 'generate' ) ) {
			// I'd dump the whole oid meta array here, but it's pretty much guaranteed to be empty if we're here at all.
			$this->logger->error( __FUNCTION__ . ": Unable to determine what oid to use, in generate mode." );
		}

		return null;
	}

	/**
	 * Default orderID generation
	 * This used to be done in DonationData, but gateways should control
	 * the format here. Override this in child classes.
	 *
	 * @param DonationData|null $dataObj Reference to the donation data object
	 * when we are forced to create the order ID during construction of it
	 * and thus do not already have a reference. THIS IS A HACK! /me vomits
	 *
	 * @return string A freshly generated order ID
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
			$sequence = $this->session_getData( 'sequence' );
			if ( !$sequence ) {
				$sequence = 1;
				WmfFramework::setSessionValue( 'sequence', $sequence );
			}

			return "{$ctid}.{$sequence}";
		}
		$order_id = (string)mt_rand( 1000, 9999999999 );
		return $order_id;
	}

	public function regenerateOrderID(): ?string {
		$id = null;
		if ( $this->getOrderIDMeta( 'generate' ) ) {
			$id = $this->generateOrderID(); // should we pass $this->dataObj?
			$source = 'regenerated';  // This implies the try number is > 1.
			$this->order_id_candidates[$source] = $id;
			// alter the meta with the new data
			$this->setOrderIDMeta( 'final', $id );
			$this->setOrderIDMeta( 'final_source', 'regenerated' );
		} else {
			// we are not regenerating ourselves, but we need a new one...
			// so, blank it and wait.
			$this->order_id_candidates = [];
			unset( $this->order_id_meta['final'] );
			unset( $this->order_id_meta['final_source'] );
		}

		// tell DonationData about it
		$this->addRequestData( [ 'order_id' => $id ] );
		// Add new Order ID to the session.
		$this->session_addDonorData();
		return $id;
	}

	protected function ensureUniqueOrderID() {
		// If this is not our first call, get a fresh order ID
		// FIXME: This is still too complicated. We want to maintain
		// a consistent order ID from the start of do_transaction till
		// the start of at least the next transaction (if not longer).
		// For that reason, we're not regenerating the order ID at the
		// same time as we increment the sequence number. We should
		// be able to achieve that more simply.
		$sequenceNum = $this->session_getData( 'sequence' );
		if ( $sequenceNum && $sequenceNum > 1 ) {
			$this->regenerateOrderID();
		}
	}

	/**
	 * @param string|false $key
	 * @return mixed
	 */
	public function getOrderIDMeta( $key = false ) {
		$data = $this->order_id_meta;
		if ( !is_array( $data ) ) {
			return false;
		}

		if ( $key ) {
			// just return the key if it exists
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

	/** @inheritDoc */
	public function getPaymentMethodMeta( $payment_method = null ) {
		if ( $payment_method === null ) {
			$payment_method = $this->getPaymentMethod();
		}

		if ( isset( $this->payment_methods[ $payment_method ] ) ) {
			return $this->payment_methods[ $payment_method ];
		} else {
			$message = "The payment method [{$payment_method}] was not found.";
			throw new OutOfBoundsException( $message );
		}
	}

	/**
	 * @param string $payment_method
	 * @return bool
	 */
	public function isThirdPartyFormPaymentMethod( $payment_method ) {
		// we might have more thrid party payment methods here in the array, probably manage them in other place
		return in_array( $payment_method, [ 'google', 'apple', 'venmo', 'paypal', 'amazon' ] );
	}

	/** @inheritDoc */
	public function getPaymentSubmethodMeta( $payment_submethod = null, $payment_method = null ) {
		if ( $payment_submethod === null ) {
			$payment_submethod = $this->getPaymentSubmethod();
		}

		if ( $payment_method === null ) {
			$payment_method = $this->getPaymentMethod();
		}

		// no need to validate submethod for payment methods that use their own form
		if ( $this->isThirdPartyFormPaymentMethod( $payment_method ) ) {
			$this->logger->debug( 'Add validation metadata for payment ' . $payment_method . '[' . $payment_submethod . ']' );
			return [ 'validation' => [] ];
		}

		if ( isset( $this->payment_submethods[ $payment_submethod ] ) ) {
			$this->logger->debug( 'Getting metadata for payment submethod: ' . (string)$payment_submethod );

			// Ensure that the validation index is set.
			if ( !isset( $this->payment_submethods[ $payment_submethod ]['validation'] ) ) {
				$this->payment_submethods[ $payment_submethod ]['validation'] = [];
			}

			return $this->payment_submethods[ $payment_submethod ];
		}

		$msg = "The payment submethod [{$payment_submethod}] was not found.";
		$this->logger->error( $msg );
		throw new OutOfBoundsException( $msg );
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
	 * @return array with available submethods 'visa' => [ 'label' => 'Visa' ]
	 */
	public function getAvailableSubmethods() {
		$method = $this->getPaymentMethod();
		$isRecurring = $this->getData_Unstaged_Escaped( 'recurring' );
		$country = $this->getData_Unstaged_Escaped( 'country' );
		$methodSupportsRecurring = $method ? $this->getPaymentMethodMeta( $method )['recurring'] : false;

		$submethods = [];
		foreach ( $this->payment_submethods as $key => $available_submethod ) {
			$group = $available_submethod['group'];
			if ( $method !== $group ) {
				continue; // skip anything not part of the selected method
			}

			$submethodHasCountryFilter = isset( $available_submethod['countries'] );
			$removeForUnsupportedCountry =
				$submethodHasCountryFilter && !in_array( $country, $available_submethod['countries'] );

			// If the submethod does not specify whether it supports recurring, fall back to
			// the setting for its parent method.
			$submethodSupportsRecurring = $available_submethod['recurring'] ?? $methodSupportsRecurring;
			$removeForUnsupportedRecurring = $isRecurring && !$submethodSupportsRecurring;

			if ( $removeForUnsupportedCountry || $removeForUnsupportedRecurring ) {
				continue; // skip 'em if they're not allowed round here
			}
			$submethods[$key] = $available_submethod;
		}
		return $submethods;
	}

	protected function logPaymentDetails( string $preface = self::REDIRECT_PREFACE ) {
		$details = $this->getQueueDonationMessage();
		$json = json_encode( $details );
		$this->logger->info( $preface . $json );
	}

	protected function logCompletedPayment() {
		if ( $this->getGlobal( 'LogCompleted' ) ) {
			$this->logPaymentDetails( self::COMPLETED_PREFACE );
		}
	}

	protected function runSessionVelocityFilter(): bool {
		$result = Gateway_Extras_SessionVelocityFilter::onProcessorApiCall( $this );

		if ( $result == false ) {
			$this->logger->info( 'Processor API call aborted on Session Velocity filter' );
			$this->setValidationAction( ValidationAction::REJECT );
		}
		return $result;
	}

	/**
	 * Returns an array of rules used to validate data before submission.
	 * Each entry's key should correspond to the id of the target field, and
	 * the value should be a list of rules with keys as described in
	 * @see ClientSideValidationHelper::getClientSideValidation
	 * @return array
	 */
	public function getClientSideValidationRules() {
		// Start with the server required field validations.
		$requiredRules = [];
		foreach ( $this->getRequiredFields() as $field ) {
			$key = 'donate_interface-error-msg-' . $field;
			$requiredRules[$field] = [
				[
					'required' => true,
					'messageKey' => $key,
				]
			];
		}

		$transformerRules = [];
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
	 * Takes normalized data and creates adapter specific params for processDonorReturn
	 * @return array
	 */
	public function createDonorReturnParams() {
		return [];
	}

	/**
	 * Allows adapters to specify logic as to whether an orphan can be rectified
	 * @return bool
	 */
	public function shouldRectifyOrphan() {
		return false;
	}

	/**
	 * Cancel payment based on adapter and set status to cancelled
	 * @return PaymentResult
	 */
	public function cancel() {
		return PaymentResult::newFailure();
	}

	/**
	 * Check if country is in the list for $wgDonationInterfaceMonthlyConvertCountries
	 * @return bool
	 */
	protected function isMonthlyConvertCountry() {
		$country = $this->getData_Unstaged_Escaped( 'country' );
		$monthlyConvertCountries = $this->getGlobal( 'MonthlyConvertCountries' );
		return in_array( $country, $monthlyConvertCountries );
	}

	/**
	 * Add the suggested monthly donation amounts for each donation level
	 * according to the currency saved in session for this donation attempt.
	 * For currencies that are neither in the config nor these fallback rules,
	 * we leave the variable unset here and the JavaScript just redirects the
	 * donor to the Thank You page. Defaults include rules for USD, GBP, and JPY
	 * @return array|null
	 */
	public function getMonthlyConvertAmounts(): ?array {
		$convertAmounts = $this->getGlobal( 'MonthlyConvertAmounts' );
		$currency = $this->getData_Unstaged_Escaped( 'currency' );
		if ( isset( $convertAmounts[$currency] ) ) {
			return $convertAmounts[$currency];
		} elseif ( $currency === 'EUR' ) {
			// If EUR not specifically configured, fall back to GBP rules
			return $convertAmounts['GBP'];
		} elseif ( $currency === 'NOK' ) {
			// If NOK not specifically configured, fall back to SEK rules
			return $convertAmounts['SEK'];
		} elseif ( in_array( $currency, [ 'PLN', 'RON' ], true ) ) {
			// If these currencies aren't configured, fall back to MYR rules
			return $convertAmounts['MYR'];
		} elseif ( in_array( $currency, [ 'AUD', 'CAD', 'NZD' ], true ) ) {
			// If these currencies aren't configured, fall back to USD rules
			return $convertAmounts['USD'];
		}
		return null;
	}

	/**
	 * @return bool true when we want to ask a one-time donor for a recurring
	 *  donation after their one-time donation is complete.
	 *
	 * @see $wgDonationInterfaceMonthlyConvertCountries
	 */
	public function showMonthlyConvert() {
		$monthlyConvertAmounts = $this->getMonthlyConvertAmounts();
		if ( $monthlyConvertAmounts !== null ) {
			$mcMinimumAmount = $monthlyConvertAmounts[0][0];
			// check if amount is up to monthly convert minimum amount for specified currency
			if ( (float)$this->getData_Unstaged_Escaped( 'amount' ) < $mcMinimumAmount ) {
				return false;
			}
		}

		if ( !$this instanceof RecurringConversion ) {
			return false;
		}
		if ( !in_array(
			$this->getPaymentMethod(),
			$this->getPaymentMethodsSupportingRecurringConversion()
		) ) {
			return false;
		}
		// FIXME:: make a hook, move this check to EndowmentHooks
		$medium = $this->getData_Unstaged_Escaped( 'utm_medium' );
		// never show for endowment
		if ( $medium == "endowment" ) {
			return false;
		}
		$variant = $this->getData_Unstaged_Escaped( 'variant' );
		if ( $variant === 'noMonthlyConvert' ) {
			return false;
		}
		$isMonthlyConvert = is_string( $variant ) && strstr( $variant, 'monthlyConvert' ) !== false;
		$isRecurring = $this->getData_Unstaged_Escaped( 'recurring' );

		if ( !$isMonthlyConvert && $this->isMonthlyConvertCountry() ) {
			$isMonthlyConvert = true;
		}
		return !$isRecurring && $isMonthlyConvert;
	}

	/**
	 * Logs the referrer and the current request URL for the donor visit.
	 *
	 * @return void
	 */
	protected function logBasicInfoAboutDonorWebRequest(): void {
		$context = RequestContext::getMain();
		$request = $context->getRequest();

		// we only need to log during production web requests.
		if ( get_class( $request ) === WebRequest::class ) {
			$referrer = $request->getHeader( 'referer' );
			$requestURL = $request->getFullRequestURL();

			$this->logger->info( "Donor Referrer: {$referrer}" );
			$this->logger->info( "Donor Request URL: {$requestURL}" );
		}
	}

	/**
	 * Handles cases where an invalid amount value is found
	 * during currency conversion. Logs the invalid amount and performs further
	 * action based on the received request data.
	 *
	 * @param mixed $oldAmount The invalid amount value detected during the
	 * currency conversion process.
	 *
	 * @return void
	 */
	private function handleInvalidAmount( $oldAmount ): void {
		$this->logger->warning( "Invalid amount value for currency conversion: " . print_r( $oldAmount, true ) );

		$urlAmountParam = WmfFramework::getRequestValue( 'amount', null );
		if ( $urlAmountParam === 'null' ) {
			$this->redirectWithValidAmount();
		}
	}

	/**
	 * Checks for a 'null' or invalid amount parameter
	 * in the incoming request. If found, assigns a default numeric value
	 * of '1' to the amount parameter and redirects the user back to the same page
	 * with the updated parameter in the URL.
	 *
	 * @return void
	 */
	private function redirectWithValidAmount(): void {
		$this->logger->warning( "'null' amount value passed in URL. Adding in numeric value and redirecting back to same page" );
		$request = RequestContext::getMain()->getRequest();
		$params = $request->getValues();
		$params['amount'] = '1';
		$title = RequestContext::getMain()->getTitle();
		$newUrl = $title->getFullURL( $params );
		RequestContext::getMain()->getOutput()->redirect( $newUrl );
	}

}
