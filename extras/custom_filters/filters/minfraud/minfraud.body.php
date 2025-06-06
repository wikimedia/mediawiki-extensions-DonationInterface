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
use MaxMind\MinFraud;
use MaxMind\MinFraud\Model\Score;
use MediaWiki\MediaWikiServices;
use Psr\Log\LogLevel;
use SmashPig\PaymentData\ValidationAction;

/**
 * Gateway_Extras_CustomFilters_MinFraud
 *
 * Implements minFraud Score from MaxMind with CustomFilters.
 *
 * This allows us to capture the riskScore from minFraud and adjust it with our
 * own custom filters and risk score modifications.
 *
 * See developer documentation at https://dev.maxmind.com/minfraud/ and
 * http://maxmind.github.io/minfraud-api-php/
 *
 * Enabling the minFraud filter requires three variables to be set in
 * LocalSettings.php:
 *
 * @code
 * $wgDonationInterfaceEnableMinFraud = true;
 * $wgDonationInterfaceMinFraudAccountId = 12345;
 * $wgDonationInterfaceMinFraudLicenseKey = 'YOUR LICENSE KEY';
 * @endcode
 */
class Gateway_Extras_CustomFilters_MinFraud extends Gateway_Extras {

	/**
	 * MaxMind\MinFraud client
	 *
	 * @var MinFraud
	 */
	protected $minFraud;

	/**
	 * Instance of Custom filter object
	 * @var Gateway_Extras_CustomFilters
	 */
	protected $cfo;
	/**
	 * User ID for minFraud Score web service
	 * @var int
	 */
	protected $minFraudAccountId = 0;

	/**
	 * An array of options to pass to the MinFraud client
	 * @see MaxMind\MinFraud
	 * @var array
	 */
	protected $minFraudClientOptions = [];

	/**
	 * License key for minFraud
	 * @var string
	 */
	protected $minFraudLicenseKey = '';

	/**
	 * Instance of Gateway_Extras_CustomFilters_MinFraud
	 * @var Gateway_Extras_CustomFilters_MinFraud
	 */
	protected static $instance;

	/**
	 * Sends messages to the blah_gateway_fraud log
	 * @var \Psr\Log\LoggerInterface
	 */
	protected $fraud_logger;

	/**
	 * Extra fields to send to minFraud. These should be our normalized field
	 * names, not the minFraud field names.
	 * When 'email' is specified as an extra, it means we will send the real
	 * address instead of the md5 hash.
	 *
	 * See http://dev.maxmind.com/minfraud/#Input
	 * @var string[]
	 */
	protected $enabledExtraFields = [];

	/**
	 * Top level keys indicate the grouping under the minFraud scheme.
	 * Second level keys are our field names, values are minFraud field names.
	 * @var array
	 */
	protected static $extraFieldsMap = [
		'email' => [ 'email' => 'address' ],
		'billing' => [
			'first_name' => 'first_name',
			'last_name' => 'last_name',
			'street_address' => 'address',
		],
		'order' => [
			'amount' => 'amount',
			'currency' => 'currency'
		]
	];

	/**
	 * Constructor
	 *
	 * @param GatewayType $gateway_adapter Gateway adapter instance
	 * @param Gateway_Extras_CustomFilters $custom_filter_object Instance of Custom filter object
	 * @throws RuntimeException
	 */
	protected function __construct(
		GatewayType $gateway_adapter,
		Gateway_Extras_CustomFilters $custom_filter_object
	) {
		parent::__construct( $gateway_adapter );
		$this->fraud_logger = DonationLoggerFactory::getLogger( $gateway_adapter, '_fraud' );

		$this->cfo = $custom_filter_object;

		$accountId = $gateway_adapter->getGlobal( 'MinFraudAccountId' );
		$licenseKey = $gateway_adapter->getGlobal( 'MinFraudLicenseKey' );

		// Set the minFraud User ID and license key.
		// Go no further if we don't have them
		if ( !$accountId || !$licenseKey ) {
			throw new RuntimeException(
				'When $wgDonationInterfaceEnableMinFraud is true, both ' .
				'$wgDonationInterfaceMinFraudAccountId and ' .
				'$wgDonationInterfaceMinFraudLicenseKey must be set.'
			);
		}
		$this->minFraudLicenseKey = $licenseKey;
		$this->minFraudAccountId = $accountId;

		// Set the minFraud API options
		$minFraudOptions = $gateway_adapter->getGlobal( 'MinFraudClientOptions' );
		if ( is_array( $minFraudOptions ) ) {
			$this->minFraudClientOptions = $minFraudOptions;
		}
		$extraFields = $gateway_adapter->getGlobal( 'MinFraudExtraFields' );
		if ( is_array( $extraFields ) ) {
			$this->enabledExtraFields = $extraFields;
		}
	}

	/**
	 * Builds minFraud query from user input
	 *
	 * Required:
	 * - city
	 * - country
	 * - donor IP address
	 * - postal code
	 * - region
	 *
	 * Optional that we are sending:
	 * - domain: the domain of the email address
	 * - email: an MD5 of the email address
	 * - transaction_id: the internal transaction id of the contribution
	 *
	 * @param array $data unstaged data from the gateway
	 * @return array all parameters for the query
	 */
	protected function buildQuery( array $data ) {
		$standardQuery = [
			'device' => $this->getDeviceParams( $data ),
			'email' => $this->getEmailParams( $data ),
			'billing' => $this->getBillingParams( $data ),
			'event' => $this->getEventParams( $data ),
		];
		$query = $this->withExtraFields( $data, $standardQuery );
		return $query;
	}

	protected function getDeviceParams( array $data ): array {
		$deviceParams = [
			'ip_address' => $data['user_ip']
		];
		$deviceParams += [
			'user_agent' => WmfFramework::getRequestHeader( 'user-agent' ),
			'accept_language' => WmfFramework::getRequestHeader( 'accept-language' )
		];
		return $deviceParams;
	}

	/**
	 * @param array $data
	 * @return array
	 */
	protected function getEmailParams( $data ) {
		return [
			'address' => md5( $data['email'] ),
			'domain' => substr( strstr( $data['email'], '@' ), 1 )
		];
	}

	protected function getBillingParams( array $data ): array {
		$map = [
			'city' => 'city',
			'postal_code' => 'postal',
			'country' => 'country'
		];
		$billingParams = [];
		foreach ( $map as $ourName => $theirName ) {
			if ( !empty( $data[$ourName] ) && $data[$ourName] !== '0' ) {
				$billingParams[$theirName] = $data[$ourName];
			}
		}
		// Only send any state / province data if we're sure it's a valid ISO code
		if ( !empty( $data['state_province'] ) ) {
			$countrySubdivisions = Subdivisions::getByCountry( $data['country'] );
			if ( $countrySubdivisions && array_key_exists( $data['state_province'], $countrySubdivisions ) ) {
				$billingParams['region'] = $data['state_province'];
			}
		}
		return $billingParams;
	}

	protected function getEventParams( array $data ): array {
		return [
			'transaction_id' => $data['contribution_tracking_id'],
		];
	}

	protected function withExtraFields( array $data, array $query ): array {
		foreach ( self::$extraFieldsMap as $section => $fields ) {
			foreach ( $fields as $ourName => $theirName ) {
				if ( in_array( $ourName, $this->enabledExtraFields ) ) {
					if ( !empty( $data[$ourName] ) ) {
						$query[$section][$theirName] = $data[$ourName];
					}
				}
			}
		}
		return $query;
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
	 * @return bool
	 */
	protected function canBypassMinFraud() {
		// if the data bits data_hash and action are not set, we need to hit minFraud
		$localdata = $this->gateway_adapter->getData_Unstaged_Escaped();
		if ( !isset( $localdata['data_hash'] ) || !strlen(
				$localdata['data_hash']
			) || !isset( $localdata['action'] ) || !strlen( $localdata['action'] ) ) {
			return false;
		}

		$data_hash = $localdata['data_hash']; // the data hash passed in by the form submission
		// unset these values since they are not part of the overall data hash
		$this->gateway_adapter->unsetHash();
		unset( $localdata['data_hash'] );
		// compare the data hash to make sure it's legit
		if ( $this->compare_hash( $data_hash, serialize( $localdata ) ) ) {
			$this->gateway_adapter->setHash(
				$this->generate_hash( $this->gateway_adapter->getData_Unstaged_Escaped() )
			); // hash the data array
			// check to see if we have a valid action set for us to bypass minFraud
			$actions = [
				ValidationAction::PROCESS,
				ValidationAction::CHALLENGE,
				ValidationAction::REVIEW,
				ValidationAction::REJECT
			];
			$action_hash = $localdata['action']; // a hash of the action to take passed in by the form submission
			foreach ( $actions as $action ) {
				if ( $this->compare_hash( $action_hash, $action ) ) {
					// set the action that should be taken
					$this->gateway_adapter->setValidationAction( $action );
					return true;
				}
			}
		} else {
			// log potential tampering
			$this->log( $localdata['contribution_tracking_id'], 'Data hash/action mismatch', LogLevel::ERROR );
		}

		return false;
	}

	/**
	 * Execute the minFraud filter
	 *
	 * @return bool true
	 */
	protected function filter() {
		// see if we can bypass minFraud
		if ( $this->canBypassMinFraud() ) {
			return true;
		}
		// get globals
		$score = $this->gateway_adapter->getGlobal( 'MinFraudErrorScore' );
		$weight = $this->gateway_adapter->getGlobal( 'MinFraudWeight' );
		$multiplier = $weight / 100;
		try {
			$data = $this->getTransactionDataWithSplitNames();
			$query = $this->buildQuery( $data );
			$response = $this->queryMinFraud( $query );
			// Write the query/response to the log before we go mad.
			$this->logQuery( $query, $response );
			$this->healthCheck( $response );
			if ( !isset( $response->riskScore ) ) {
				$this->fraud_logger->critical(
					"'addRiskScore' No response at all from minFraud."
				);
				$this->cfo->addRiskScore( $score * $multiplier, 'minfraud_filter' );
			} else {
				$this->cfo->addRiskScore(
					$response->riskScore * $multiplier,
					'minfraud_filter'
				);
			}

		} catch ( Exception $ex ) {
			// log out the whole response to the error log so we can tell what the heck happened... and fail closed.
			$log_message = 'An error occurred during minFraud query. Assigning MinFraudErrorScore.';
			$this->fraud_logger->error( '"addRiskScore" ' . $log_message );
			$this->fraud_logger->error( $ex->getMessage() );
			$this->cfo->addRiskScore( $score * $multiplier, 'minfraud_filter' );
		}

		return true;
	}

	/**
	 * Get a copy of the transaction data, with any full_name field
	 * split up to send to minfraud. For now we do a simple split on
	 * the first space since we are likely to get any full_name from
	 * the cardholder name field, which is unlikely to have prefixes.
	 * If we end up with a lot of bad data we can look into using
	 * a name parser library as in the Civi message import functions.
	 * @return array
	 */
	protected function getTransactionDataWithSplitNames(): array {
		$data = $this->gateway_adapter->getData_Unstaged_Escaped();
		if (
			empty( $data['first_name'] ) &&
			empty( $data['last_name'] ) &&
			!empty( $data['full_name'] )
		) {
			$nameParts = explode( ' ', $data['full_name'], 2 );
			$data['first_name'] = $nameParts[0];
			if ( count( $nameParts ) > 1 ) {
				$data['last_name'] = $nameParts[1];
			}
		}
		return $data;
	}

	/**
	 * Logs a minFraud query and its response
	 *
	 * @param array $query parameters sent to minFraud
	 * @param Score $response result from minFraud client score() call
	 */
	protected function logQuery( array $query, Score $response ) {
		$encodedResponse = json_encode( $response->jsonSerialize() );

		$log_message = '';

		$log_message .= "\t" . '"' . date( 'c' ) . '"';
		$log_message .= "\t" . '"' . addslashes(
			$this->gateway_adapter->getData_Unstaged_Escaped( 'amount' ) .
			' ' .
			$this->gateway_adapter->getData_Unstaged_Escaped( 'currency' )
		) . '"';
		$log_message .= "\t" . '"' . addslashes( json_encode( $query ) ) . '"';
		$log_message .= "\t" . '"' . addslashes( $encodedResponse ) . '"';
		$log_message .= "\t" . '"' . addslashes(
			$this->gateway_adapter->getData_Unstaged_Escaped( 'referrer' )
		) . '"';
		$this->fraud_logger->info( '"minFraud query" ' . $log_message );
	}

	/**
	 * Run the minFraud filter if it is enabled
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
		if ( !$gateway_adapter->getGlobal( 'EnableMinFraud' ) ) {
			return true;
		}
		$gateway_adapter->debugarray[] = 'minFraud onFilter!';
		return self::singleton( $gateway_adapter, $custom_filter_object )->filter();
	}

	/**
	 * Perform the minFraud query and capture the response
	 *
	 * @param array $query The array you would pass to minFraud in a query
	 * @return Score result from minFraud client score() call
	 */
	protected function queryMinFraud( array $query ) {
		$minFraud = new MinFraud(
			$this->minFraudAccountId,
			$this->minFraudLicenseKey,
			$this->minFraudClientOptions
		);
		$minFraud = $minFraud->withBilling(
			$query['billing']
		)->withDevice(
			$query['device']
		)->withEmail(
			$query['email']
		)->withEvent(
			$query['event']
		);
		if ( !empty( $query['order'] ) ) {
			$minFraud = $minFraud->withOrder( $query['order'] );
		}
		return $minFraud->score();
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
		if ( !self::$instance ) {
			self::$instance = new self( $gateway_adapter, $custom_filter_object );
		}
		return self::$instance;
	}

	/**
	 * Perform a health check on minFraud data; send an email alarm on violation.
	 * Right now this only checks the number of queries remaining.
	 *
	 * @param Score $response result from minFraud client score() call
	 */
	protected function healthCheck( Score $response ) {
		global $wgEmergencyContact;

		if ( isset( $response->queriesRemaining ) ) {
			$queries = intval( $response->queriesRemaining );

			if ( $queries < $this->gateway_adapter->getGlobal( 'MinFraudAlarmLimit' ) ) {
				$this->gateway_logger->warning( "minFraud alarm limit reached! Queries remaining: $queries" );

				$cache = MediaWikiServices::getInstance()->getObjectCacheFactory()->getLocalClusterInstance();
				$key = $cache->makeKey( 'DonationInterface', 'MinFraud', 'QueryAlarmLast' );
				$lastAlarmAt = $cache->get( $key ) | 0;
				if ( $lastAlarmAt < time() - ( 60 * 60 * 24 ) ) {
					$cache->set( $key, time(), ( 60 * 60 * 48 ) );
					$this->gateway_logger->info( "minFraud alarm on query limit -- sending email" );

					$result = UserMailer::send(
						new MailAddress( $wgEmergencyContact ),
						new MailAddress( 'donationinterface@' . gethostname() ),
						"minFraud Queries Remaining Low ({$queries})",
						'Queries remaining: ' . $queries
					);
					if ( !$result->isGood() ) {
						$this->gateway_logger->error(
							// @phan-suppress-next-line PhanTypeExpectedObjectPropAccess
							"Could not send minFraud query limit email: " . $result->getErrors()[0]->message
						);
					}
				}
			}
		}
	}
}
