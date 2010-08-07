<?php

class MinFraud {

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
	 * File handle for log file
	 * @var public 
	 */
	public $log_fh = NULL;

	/**
	 * User-definable riskScore ranges for actions to take
	 * @var public array
	 */
	public $action_ranges = array(
		'process'	=> array( 0, 100 ),
		'review'	=> array( -1, -1 ),
		'challenge'	=> array( -1, -1 ),
		'reject'	=> array( -1, -1 ),
	);

	function __construct( $license_key = NULL ) {
		require_once( __FILE__ . "/../ccfd/CreditCardFraudDetection.php" );

		global $wgMinFraudLicenseKey, $wgMinFraudActionRanges, $wgMinFraudLog;

		// set the minfraud license key, go no further if we don't have it
		if ( !$license_key && !$wgMinFraudLicenseKey ) {
			throw new Exception( "minFraud license key required but not present." );
		}
		$this->minfraud_license_key = ( $license_key ) ? $license_key : $wgMinFraudLicenseKey; 

		if ( isset( $wgMinFraudActionRanges )) $this->action_ranges = $wgMinFraudActionRanges;

		$log_file = ( $wgMinFraudLog ) ? $wgMinFraudLog : "/var/log/mw/minfraud";
		$this->prepare_log_file( $log_file );
	}

	/**
	 * Query minFraud with the transaction, set actions to take and make a log entry
	 *
	 * Accessible via $wgHooks[ 'PayflowGatewayValidate' ]
	 * @param object PayflowPro Gateway object
	 * @param array The array of data generated from an attempted transaction
	 */
	public function validate( &$pfp_gateway_object, $data ) {
		$minfraud_hash = $this->build_query( $data );
		$this->query_minfraud( $minfraud_hash );
		$pfp_gateway_object->actions = $this->determine_actions( $this->minfraud_response[ 'riskScore' ] );
		$log_message = '"'. date('c') . '"';
		$log_message .= "\t" . '"' . $data[ 'contribution_tracking_id' ] . '"';
		$log_message .= "\t" . '"' . $data[ 'comment' ] . '"';
		$log_message .= "\t" . '"' . $data[ 'amount' ] . ' ' . $data[ 'currency' ] . '"';
		$log_message .= "\t" . '"' . serialize( $minfraud_hash ) . '"';
		$log_message .= "\t" . '"' . serialize( $this->minfraud_response ) . '"';
		$log_message .= "\t" . '"' . serialize( $pfp_gateway_object->actions ) . '"';
		$this->log( $log_message );
    return TRUE;
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
	 * Builds minfraud query hash from user input
	 * @return array containing hash for minfraud query
	 */
	public function build_query( array $data ) {
		// mapping of data keys -> minfraud hash keys
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
		$minfraud_hash[ "license_key" ] = $this->minfraud_license_key;

		// user's IP address
		$minfraud_hash[ "i" ] = $_SERVER[ 'REMOTE_ADDR' ];

		// user's user agent
		$minfraud_hash[ "user_agent" ] = $_SERVER[ 'HTTP_USER_AGENT' ];

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
        default:
          $newdata[ $value ] = $data[ $value ];
			}

			$minfraud_hash[ $key ] = $newdata[ $value ];
		}

		return $minfraud_hash;
	}

	/**
	 * Perform the min fraud query and capture the response
	 */
	public function query_minfraud( array $minfraud_hash ) {
		$this->get_ccfd()->input( $minfraud_hash );
		$this->get_ccfd()->query();
		$this->minfraud_response = $this->get_ccfd()->output();
	}

	/**
	 * Validates the minfraud_hash for minimum required fields
	 *
	 * This is a pretty dumb validator.  It just checks to see if
	 * there is a value for a required field and if its length is > 0
	 * 
	 * @param array $minfraud_hash which is the hash you would pass to 
	 *	minfraud in a query
	 * @result bool
	 */
	public function validate_minfraud_hash( array $minfraud_hash ) {
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
			if ( !isset( $minfraud_hash[ $reqd_field ] ) || 
					strlen( $minfraud_hash[ $reqd_field ] ) < 1 ) {
				return FALSE;
			}
		}

		return TRUE;
	}

	/**
	 * Determine the actions for the processor to take
	 *
	 * Determined based on predefined riskScore ranges for 
	 * a given action.  It is possible to return multiple
	 * ranges.
	 * @param float risk score (returned from minFraud)
	 * @return array of actions to be taken
	 */
	 public function determine_actions( $risk_score ) {
		$actions = array();
		foreach ( $this->action_ranges as $action => $range ) {
			if ( $risk_score >= $range[0] && $risk_score <= $range[1] ) {
				$actions[] = $action;
			}
		}
		return $actions;
	}

	/**
	 * Prepares a log file
	 *
	 * @param string path to log file
	 * @return resource Pointer for the log file
	 */
	protected function prepare_log_file( $log_file ) {
		$this->log_fh = fopen( $log_file, 'a+' );
	}

	/**
	 * Writes message to a log file
	 *
	 * If a log file does not exist and could not be created,
	 * do nothing.
	 * @fixme Perhaps lack of log file can be handled better,
	 *	or maybe it doesn't matter?
	 * @param string The message to log
	 */
	public function log( $msg ) {
		if ( !$this->log_fh ) {
			return;
		}
		$msg .= "\n";
		fwrite( $this->log_fh, $msg );
	}

	/**
	 * Close the open log file handler if it's open
	 */
	public function __destruct() {
		if ( $this->log_fh ) fclose( $this->log_fh );
	}
}
