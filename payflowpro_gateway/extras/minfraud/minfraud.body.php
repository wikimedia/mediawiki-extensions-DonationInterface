<?php
class PayflowProGateway_Extras_MinFraud extends PayflowProGateway_Extras {

	/**
	 * Full response from minFraud
	 * @var public array
	 */
	public $minfraud_response = NULL;

	/**
	 * License key for minfraud
	 * @var public string
	 */
	public $minfraud_license_key = NULL;

	/**
	 * User-definable riskScore ranges for actions to take
	 * 
 	 * Overload with $wgMinFraudActionRanges
	 * @var public array
	 */
	public $action_ranges = array(
		'process'	=> array( 0, 100 ),
		'review'	=> array( -1, -1 ),
		'challenge'	=> array( -1, -1 ),
		'reject'	=> array( -1, -1 ),
	);

	/**
	 * For holding an instance of self
	 */
	static $instance;

	function __construct( $license_key = NULL ) {
		parent::__construct();
		$dir = dirname( __FILE__ ) .'/';
		require_once( $dir . "ccfd/CreditCardFraudDetection.php" );
		require_once( $dir . "../../includes/countryCodes.inc" );
		global $wgMinFraudLicenseKey, $wgMinFraudActionRanges;

		// set the minfraud license key, go no further if we don't have it
		if ( !$license_key && !$wgMinFraudLicenseKey ) {
			throw new MWException( "minFraud license key required but not present." );
		}
		$this->minfraud_license_key = ( $license_key ) ? $license_key : $wgMinFraudLicenseKey; 

		if ( isset( $wgMinFraudActionRanges )) $this->action_ranges = $wgMinFraudActionRanges;
	}

	/**
	 * Query minFraud with the transaction, set actions to take and make a log entry
	 *
	 * Accessible via $wgHooks[ 'PayflowGatewayValidate' ]
	 * @param object PayflowPro Gateway object
	 * @param array The array of data generated from an attempted transaction
	 */
	public function validate( &$pfp_gateway_object, &$data ) {
		// see if we can bypass minfraud
		if ( $this->can_bypass_minfraud( $pfp_gateway_object, $data )) return TRUE;

		$minfraud_query = $this->build_query( $data );
		$this->query_minfraud( $minfraud_query );
		$pfp_gateway_object->action = $this->determine_action( $this->minfraud_response[ 'riskScore' ] );

		// reset the data hash
		if ( isset( $data[ 'data_hash' ] )) unset( $data[ 'data_hash' ] );
		$data[ 'action' ] = $this->generate_hash( $pfp_gateway_object->action );
		$data[ 'data_hash' ] = $this->generate_hash( serialize( $data ));
	
		// Write the query/response to the log
		$this->log_query( $minfraud_query, $pfp_gateway_object, $data );
		return TRUE;
	}

	/**
	 * Logs a minFraud query and its response
	 */
	public function log_query( $minfraud_query, $pfp_gateway_object, $data ) {
		if ( $this->log_fh ) {
			$log_message = '"' . addslashes( $data[ 'comment' ] ) . '"';
			$log_message .= "\t" . '"' . addslashes( $data[ 'amount' ] . ' ' . $data[ 'currency' ] ) . '"';
			$log_message .= "\t" . '"' . addslashes( json_encode( $minfraud_query )) . '"';
			$log_message .= "\t" . '"' . addslashes( json_encode( $this->minfraud_response )) . '"';
			$log_message .= "\t" . '"' . addslashes( $pfp_gateway_object->action ) . '"';
			$log_message .= "\t" . '"' . addslashes( $data[ 'referrer' ] ) . '"';
			$this->log( $data[ 'contribution_tracking_id' ], 'minFraud query', $log_message );
		}
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
	 * @param object $pfp_gateway_object The PayflowPro gateway object
	 * @param array $data The array of data from the form submission
	 * @return bool
	 */
	public function can_bypass_minfraud( &$pfp_gateway_object, &$data ) {
		// if the data bits data_hash and action are not set, we need to hit minFraud
		if ( !strlen( $data[ 'data_hash' ] ) || !strlen( $data[ 'action' ] ) ) {
			return FALSE;
		}

		$data_hash = $data[ 'data_hash' ]; // the data hash passed in by the form submission
		$num_attempt = $data[ 'numAttempt' ]; // the num_attempt has been increased by one, so we have to adjust slightly
		$data[ 'numAttempt' ] = $num_attempt - 1;

		// unset these values from the data aray since they are not part of the overall data hash
		unset( $data[ 'data_hash' ] );
		// compare the data hash to make sure it's legit
		if ( $this->compare_hash( $data_hash, serialize( $data ))) {
			$data[ 'numAttempt' ] = $num_attempt; // reset the current num attempt
			$data[ 'data_hash' ] = $this->generate_hash( serialize( $data )); // hash the data array

			// check to see if we have a valid action set for us to bypass minfraud
			$actions = array( 'process', 'challenge', 'review', 'reject' );
			$action_hash = $data[ 'action' ]; // a hash of the action to take passed in by the form submission
			foreach ( $actions as $action ) {
				if ( $this->compare_hash( $action_hash, $action )) {
					// set the action that should be taken
					$pfp_gateway_object->action = $action;
					return TRUE;
				}
			}
		} else {
			// log potential tampering
			if ( $this->log_fh ) $this->log( $data[ 'contribution_tracking_id'], 'Data hash/action mismatch' );
		}

		return FALSE;
	}

	/**
	 * Get instance of CreditCardFraudDetection
	 * @return object
	 */
	public function get_ccfd() {
		if ( !$this->ccfd ) {
			$this->ccfd = new CreditCardFraudDetection;
		}
		return $this->ccfd;
	}

	/**
	 * Builds minfraud query from user input
	 * @return array containing hash for minfraud query
	 */
	public function build_query( array $data ) {
		global $wgPayflowGatewayTest;

		// mapping of data keys -> minfraud array keys
		$map = array(
			"city" => "city",
			"region" => "state",
			"postal" => "zip",
			"country" => "country",
			"domain" => "email",
			"emailMD5" => "email",
			"bin" => "card_num",
			"txnID" => "contribution_tracking_id"
		);

		// minfraud license key
		$minfraud_array[ "license_key" ] = $this->minfraud_license_key;

		// user's IP address
		$minfraud_array[ "i" ] = ( $wgPayflowGatewayTest ) ? '12.12.12.12' : wfGetIP();

		// user's user agent
		global $wgRequest;
		$minfraud_array[ "user_agent" ] = $wgRequest->getHeader('user-agent');

		// user's language
		$minfraud_array[ 'accept_language' ] = $wgRequest->getHeader('accept-language');

		// fetch the array of country codes
		$country_codes = countryCodes();

		// loop through the map and add pertinent values from $data to the hash
		foreach ( $map as $key => $value ) {

			// do some data processing to clean up values for minfraud
			switch ( $key ) {
				case "domain": // get just the domain from the email address
					$newdata[ $value ] = substr( strstr( $data[ $value ], '@' ), 1 );
					break;
				case "bin": // get just the first 6 digits from CC#
					$newdata[ $value ] = substr( $data[ $value ], 0, 6 );
					break;
				case "country":
					$newdata[ $value ] = $country_codes[ $data[ $value ]];
					break;
				default:
		          $newdata[ $value ] = $data[ $value ];
			}

			$minfraud_array[ $key ] = $newdata[ $value ];
		}

		return $minfraud_array;
	}

	/**
	 * Perform the min fraud query and capture the response
	 */
	public function query_minfraud( array $minfraud_query ) {
		$this->get_ccfd()->input( $minfraud_query );
		$this->get_ccfd()->query();
		$this->minfraud_response = $this->get_ccfd()->output();
	}

	/**
	 * Validates the minfraud_query for minimum required fields
	 *
	 * This is a pretty dumb validator.  It just checks to see if
	 * there is a value for a required field and if its length is > 0
	 * 
	 * @param array $minfraud_query which is the array you would pass to 
	 *	minfraud in a query
	 * @result bool
	 */
	public function validate_minfraud_query( array $minfraud_query ) {
		// array of minfraud required fields
		$reqd_fields = array(
			'license_key',
			'i',
			'city',
			'region',
			'postal',
			'country'
		);

		foreach ( $reqd_fields as $reqd_field ) {
			if ( !isset( $minfraud_query[ $reqd_field ] ) || 
					strlen( $minfraud_query[ $reqd_field ] ) < 1 ) {
				return FALSE;
			}
		}

		return TRUE;
	}

	/**
	 * Determine the action for the processor to take
	 *
	 * Determined based on predefined riskScore ranges for 
	 * a given action.
	 * @param float risk score (returned from minFraud)
	 * @return array of actions to be taken
	 */
	 public function determine_action( $risk_score ) {
		foreach ( $this->action_ranges as $action => $range ) {
			if ( $risk_score >= $range[0] && $risk_score <= $range[1] ) {
				return $action;
			}
		}
	}

	static function onValidate( &$pfp_gateway_object, &$data ) {
		return self::singleton()->validate( &$pfp_gateway_object, &$data );
	}

	static function singleton() {
		if ( !self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}
}
