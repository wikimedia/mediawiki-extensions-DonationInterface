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
 * Gateway_Extras_CustomFilters_MinFraud
 *
 * Implements minFraud from Maxmind with CustomFilters.
 *
 * This allows us to capture the riskScore from minfraud and adjust it with our
 * own custom filters and risk score modifications.
 *
 * Enabling the minFraud filter requires three variables to be set in
 * LocalSettings.php:
 *
 * @code
 * $wgDonationInterfaceEnableMinfraud = true;
 * $wgMinFraudLicenseKey = 'YOUR LICENSE KEY';
 * @endcode
 */
class Gateway_Extras_CustomFilters_MinFraud extends Gateway_Extras {

	/**
	 * Instance of minFraud CreditCardFraudDetection
	 * @var CreditCardFraudDetection $ccfd
	 */
	protected $ccfd;

	/**
	 * Instance of Custom filter object
	 * @var Gateway_Extras_CustomFilters $cfo
	 */
	protected $cfo;

	/**
	 * The query to send to minFraud
	 * @var array $minfraudQuery
	 */
	protected $minfraudQuery = array();

	/**
	 * Full response from minFraud
	 * @var array $minfraudResponse
	 */
	protected $minfraudResponse = array();

	/**
	 * An array of minFraud API servers
	 * @var array $minFraudServers
	 */
	protected $minFraudServers = array();

	/**
	 * License key for minfraud
	 * @var string $minfraudLicenseKey
	 */
	protected $minfraudLicenseKey = '';
	
	/**
	 * Instance of Gateway_Extras_CustomFilters_MinFraud
	 * @var Gateway_Extras_CustomFilters_MinFraud $instance
	 */
	protected static $instance;

	/**
	 * Sends messages to the blah_gateway_fraud log
	 * @var \Psr\Log\LoggerInterface
	 */
	protected $fraud_logger;

	/**
	 * Constructor
	 *
	 * @param GatewayType    $gateway_adapter    Gateway adapter instance
	 * @param Gateway_Extras_CustomFilters    $custom_filter_object    Instance of Custom filter object
	 * @param string            $license_key        The license key. May also be set in $wgMinFraudLicenseKey
	 * @throws RuntimeException
	 */
	protected function __construct(
		GatewayType $gateway_adapter,
		Gateway_Extras_CustomFilters $custom_filter_object,
		$license_key = NULL
	) {

		parent::__construct( $gateway_adapter );
		$this->fraud_logger = DonationLoggerFactory::getLogger( $gateway_adapter, '_fraud' );

		$this->cfo = $custom_filter_object;

		global $wgMinFraudLicenseKey;

		// set the minfraud license key, go no further if we don't have it
		if ( !$license_key && !$wgMinFraudLicenseKey ) {
			throw new RuntimeException( "minFraud license key required but not present." );
		}
		$this->minfraudLicenseKey = ( $license_key ) ? $license_key : $wgMinFraudLicenseKey;
		
		// Set the minFraud API servers
		$minFraudServers = $gateway_adapter->getGlobal( 'MinFraudServers' );
		if ( !empty( $minFraudServers ) && is_array( $minFraudServers ) ) {
			$this->minFraudServers = $minFraudServers;
		}
	}

	/**
	 * Builds minfraud query from user input
	 *
	 * Required:
	 * - city
	 * - country
	 * - i: Client IPA
	 * - license_key
	 * - postal
	 * - region
	 *
	 * Optional that we are sending:
	 * - bin: First 6 digits of the card
	 * - domain: send the domain of the email address
	 * - emailMD5: send an MD5 of the email address
	 * - txnID: The internal transaction id of the contribution.
	 *
	 * @param array $data
	 * @return array containing hash for minfraud query
	 */
	protected function build_query( array $data ) {
		// mapping of data keys -> minfraud array keys
		$map = array(
			"city" => "city",
			"region" => "state",
			"postal" => "postal_code",
			"country" => "country",
			"domain" => "email",
			"emailMD5" => "email",
			"bin" => "card_num",
			"txnID" => "contribution_tracking_id"
		);

		$this->minfraudQuery = array();

		// minfraud license key
		$this->minfraudQuery["license_key"] = $this->minfraudLicenseKey;

		// user's IP address
		$this->minfraudQuery["i"] = ( $this->gateway_adapter->getData_Unstaged_Escaped( 'user_ip' ) );

		// We only have access to these fields when the user's request is still
		// present, but not when in batch mode.
		if ( !$this->gateway_adapter->isBatchProcessor() ) {
			// user's user agent
			$this->minfraudQuery['user_agent'] = WmfFramework::getRequestHeader( 'user-agent' );

			// user's language
			$this->minfraudQuery['accept_language'] = WmfFramework::getRequestHeader( 'accept-language' );
		}

		// fetch the array of country codes
		$country_codes = CountryCodes::getCountryCodes();

		// loop through the map and add pertinent values from $data to the hash
		foreach ( $map as $key => $value ) {

			// do some data processing to clean up values for minfraud
			switch ( $key ) {
				case "domain": // get just the domain from the email address
					$newdata[$value] = substr( strstr( $data[$value], '@' ), 1 );
					break;
				case "bin": // get just the first 6 digits from CC#... if we have one. 
					$bin = '';
					if ( isset( $data[$value] ) ) {
						$bin = substr( $data[$value], 0, 6 );
					}
					$newdata[$value] = $bin;
					break;
				case "country":
					$newdata[$value] = $country_codes[$data[$value]];
					break;
				case "emailMD5":
					$newdata[$value] = $this->get_ccfd()->filter_field( $key, $data[$value] );
					break;
				default:
					$newdata[$value] = $data[$value];
			}

			$this->minfraudQuery[$key] = $newdata[$value];
		}

		return $this->minfraudQuery;
	}

	/**
	 * Check to see if we can bypass minFraud check
	 *
	 * The first time a user hits the submission form, a hash of the full data array plus a
	 * hashed action name are injected to the data.  This allows us to track the transaction's
	 * status.  If a valid hash of the data is present and a valid action is present, we can
	 * assume the transaction has already gone through the minFraud check and can be passed
	 * on to the appropriate action.
	 *
	 * @return boolean
	 */
	protected function can_bypass_minfraud() {
		// if the data bits data_hash and action are not set, we need to hit minFraud
		$localdata = $this->gateway_adapter->getData_Unstaged_Escaped();
		if ( !isset($localdata['data_hash']) || !strlen( $localdata['data_hash'] ) || !isset($localdata['action']) || !strlen( $localdata['action'] ) ) {
			return FALSE;
		}

		$data_hash = $localdata['data_hash']; // the data hash passed in by the form submission		
		// unset these values since they are not part of the overall data hash
		$this->gateway_adapter->unsetHash();
		unset( $localdata['data_hash'] );
		// compare the data hash to make sure it's legit
		if ( $this->compare_hash( $data_hash, serialize( $localdata ) ) ) {

			$this->gateway_adapter->setHash( $this->generate_hash( $this->gateway_adapter->getData_Unstaged_Escaped() ) ); // hash the data array
			// check to see if we have a valid action set for us to bypass minfraud
			$actions = array( 'process', 'challenge', 'review', 'reject' );
			$action_hash = $localdata['action']; // a hash of the action to take passed in by the form submission
			foreach ( $actions as $action ) {
				if ( $this->compare_hash( $action_hash, $action ) ) {
					// set the action that should be taken
					$this->gateway_adapter->setValidationAction( $action );
					return TRUE;
				}
			}
		} else {
			// log potential tampering
			$this->log( $localdata['contribution_tracking_id'], 'Data hash/action mismatch', LogLevel::ERROR );
		}

		return FALSE;
	}

	/**
	 * Execute the minFraud filter
	 *
	 * @return bool true
	 */
	protected function filter() {
		// see if we can bypass minfraud
		if ( $this->can_bypass_minfraud() ){
			return TRUE;
		}

		$minfraud_query = $this->build_query( $this->gateway_adapter->getData_Unstaged_Escaped() );
		$this->query_minfraud( $minfraud_query );
		
		// Write the query/response to the log before we go mad.
		$this->log_query();
		$this->health_check();
		
		try {
			if ( !isset( $this->minfraudResponse['riskScore'] ) ) {
				throw new RuntimeException( "No response at all from minfraud." );
			}
			$weight = $this->gateway_adapter->getGlobal( 'MinfraudWeight' );
			$multiplier = $weight / 100;

			$this->cfo->addRiskScore(
				$this->minfraudResponse['riskScore'] * $multiplier,
				'minfraud_filter'
			);
		} 
		catch( Exception $ex){
			//log out the whole response to the error log so we can tell what the heck happened... and fail closed.
			$log_message = 'Minfraud filter came back with some garbage. Assigning all the points.';
			$this->fraud_logger->error( '"addRiskScore" ' . $log_message );
			$this->cfo->addRiskScore( 100, 'minfraud_filter' );
		}

		return TRUE;
	}

	/**
	 * Get instance of CreditCardFraudDetection
	 * @return CreditCardFraudDetection
	 */
	protected function get_ccfd() {
		if ( !$this->ccfd ) {
			$this->ccfd = new CreditCardFraudDetection();
			
			// Override the minFraud API servers
			if ( !empty( $this->minFraudServers ) && is_array( $this->minFraudServers )  ) {
				$this->ccfd->server = $this->minFraudServers;
			}
		}
		return $this->ccfd;
	}

	/**
	 * Logs a minFraud query and its response
	 *
	 * WARNING: It is critical that the order of these fields is not altered.
	 *
	 * The minfraud_log_mailer depends on the order of these fields.
	 *
	 * @see http://svn.wikimedia.org/viewvc/wikimedia/trunk/fundraising-misc/minfraud_log_mailer/
	 */
	protected function log_query() {

		$encoded_response = array();
		foreach ($this->minfraudResponse as $key => $value) {
			$encoded_response[ $key ] = utf8_encode( $value );
		}

		$log_message = '';

		$log_message .= "\t" . '"' . date( 'c' ) . '"';
		$log_message .= "\t" . '"' . addslashes( $this->gateway_adapter->getData_Unstaged_Escaped( 'amount' ) . ' ' . $this->gateway_adapter->getData_Unstaged_Escaped( 'currency_code' ) ) . '"';
		$log_message .= "\t" . '"' . addslashes( json_encode( $this->minfraudQuery ) ) . '"';
		$log_message .= "\t" . '"' . addslashes( json_encode( $encoded_response ) ) . '"';
		$log_message .= "\t" . '"' . addslashes( $this->gateway_adapter->getData_Unstaged_Escaped( 'referrer' ) ) . '"';
		$this->fraud_logger->info( '"minFraud query" ' . $log_message );
	}

	/**
	 * Run the Minfraud filter if it is enabled
	 *
	 * @param GatewayType $gateway_adapter
	 * @param Gateway_Extras_CustomFilters $custom_filter_object
	 *
	 * @return true
	 */
	public static function onFilter(
		GatewayType $gateway_adapter,
		Gateway_Extras_CustomFilters $custom_filter_object
	) {

		if ( !$gateway_adapter->getGlobal( 'EnableMinfraud' ) ){
			return true;
		}
		$gateway_adapter->debugarray[] = 'minfraud onFilter!';
		return self::singleton( $gateway_adapter, $custom_filter_object )->filter();
	}

	/**
	 * Perform the min fraud query and capture the response
	 *
	 * @param array $minfraud_query The array you would pass to minfraud in a query
	 */
	protected function query_minfraud( array $minfraud_query ) {
		global $wgMinFraudTimeout;
		$ccfd = $this->get_ccfd();
		$ccfd->timeout = $wgMinFraudTimeout;
		$ccfd->input( $minfraud_query );
		if ( $this->gateway_adapter->getGlobal( 'Test' ) ) {
			$this->minfraudResponse = 0;
		} else {
			$ccfd->query();
			$this->minfraudResponse = $ccfd->output();
		}
		if ( !$this->minfraudResponse ) {
			$this->minfraudResponse = array();
		}
	}

	/**
	 * Get an instance of Gateway_Extras_CustomFilters_MinFraud
	 *
	 * @param GatewayType $gateway_adapter
	 * @param Gateway_Extras_CustomFilters $custom_filter_object
	 *
	 * @return Gateway_Extras_CustomFilters_MinFraud
	 */
	protected static function singleton(
		GatewayType $gateway_adapter,
		Gateway_Extras_CustomFilters $custom_filter_object
	) {

		if ( !self::$instance || $gateway_adapter->isBatchProcessor() ) {
			self::$instance = new self( $gateway_adapter, $custom_filter_object );
		}
		return self::$instance;
	}

	/**
	 * Perform a health check on minfraud data; send an email alarm on violation.
	 *
	 * Right now this only checks the number of queries remaining.
	 */
	protected function health_check() {
		global $wgEmergencyContact, $wgMemc;

		if ( array_key_exists( 'queriesRemaining', $this->minfraudResponse ) ) {
			$queries = intval( $this->minfraudResponse['queriesRemaining'] );

			if ( $queries < $this->gateway_adapter->getGlobal( 'MinFraudAlarmLimit' ) ) {
				$this->gateway_logger->warning( "MinFraud alarm limit reached! Queries remaining: $queries" );

				$key = wfMemcKey( 'DonationInterface', 'MinFraud', 'QueryAlarmLast' );
				$lastAlarmAt = $wgMemc->get( $key ) | 0;
				if ( $lastAlarmAt < time() - ( 60 * 60 * 24 ) ) {
					$wgMemc->set( $key, time(), ( 60 * 60 * 48 ) );
					$this->gateway_logger->info( "MinFraud alarm on query limit -- sending email" );

					$result = UserMailer::send(
						new MailAddress( $wgEmergencyContact ),
						new MailAddress( 'donationinterface@' . gethostname() ),
						"Minfraud Queries Remaining Low ({$queries})",
						'Queries remaining: ' . $queries
					);
					if ( !$result->isGood() ) {
						$this->gateway_logger->error(
							"Could not send MinFraud query limit email: " . $result->errors[0]->message
						);
					}
				}
			}
		}
	}
}
