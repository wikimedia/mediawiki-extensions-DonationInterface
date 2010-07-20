<?php
/**
 * Processes pending PayflowPro transactions in a queueing service using Stomp
 *
 * Uses the MediaWiki maintenance system for command line execution.  Requires MW > 1.16
 *
 * This was built to verify pending PayflowPro transactions in an ActiveMQ queue system.  
 * It pulls a transaction out of a 'pending' queue and submits the message to PayflowPro
 * for verification.  If PayflowPro verifies the transaction, it is then passed off to a
 * 'confirmed' queue for processing elsewhere.  If PayflowPro rejects for a small variety 
 * of reasons (ones that require some user intervention), the message is reloaded to the 
 * queue.  If PayflowPro completely rejects the message, it is pruned from the queue 
 * altogether.
 *
 * This performs some logging (depending on the log_level setting), which if not set to 0 
 * just gets output to the screen.
 *
 * Config options (key = var name for localSettings.php, value = command line arg name):
 *		$options = array (
 *					'wgPayflowProURL' => 'pfp-url',
 *					'wgPayflowProPartnerID' => 'pfp-partner-id',
 *					'wgPayflowProVendorID' => 'pfp-vendor-id',
 *					'wgPayflowProUserID' => 'pfp-user-id',
 *					'wgPayflowProPassword' => 'pfp-password',
 *					'wgActiveMQStompURI' => 'activemq-stomp-uri',
 *					'wgActiveMQPendingPath' => 'activemq-pending-queue',
 *					'wgActiveMQConfirmedPath' => 'activemq-confirmed-queue',
 *					'wgActiveMQPendingProcessingBatchSize' => 'batch-size',
 *					'wgActiveMQPendingProcessLogLevel' => 'log-level' );
 * Each of these config options gets set as a class property with the name of the command line arg, 
 * with the '-' replaced with a '_' (eg 'pfp-url' becomes $this->pfp_url).
 *
 * @author: Arthur Richards <arichards@wikimedia.org>
 */
require_once( dirname(__FILE__) . "/../../../maintenance/Maintenance.php" );

// load necessary stomp files from DonationInterface/active_mq
//require_once( dirname( __FILE__ ) . "/../extensions/DonationInterface/activemq_stomp/Stomp.php" );
require_once('/var/www/sites/all/modules/queue2civicrm/Stomp.php'); // why are these Stomps different?!
//require_once( dirname( __FILE__ ) . "/../extensions/DonationInterface/activemq_stomp/Stomp/Exception.php" );
require_once('/var/www/sites/all/modules/queue2civicrm/Stomp/Exception.php');

define( 'LOG_LEVEL_QUIET', 0 ); // disables all logging
define( 'LOG_LEVEL_INFO', 1 ); // some useful logging information
define( 'LOG_LEVEL_DEBUG', 2 ); // verbose logging for debug

class StompPFPPendingProcessor extends Maintenance {

	/** If TRUE, output extra information for debug purposes **/
	protected $log_level = LOG_LEVEL_INFO;

	/** Holds our Stomp connection instance **/
	protected $stomp;
	
	/** The number of items to process **/
	protected $batch_size = 50;

	public function __construct() {
		parent::__construct();

		// register command line params with the parent class
		$this->register_params();
	}

	public function execute() {
		// load configuration options
		$this->load_config_options();
		$this->log( "Pending queue processor bootstrapped and ready to go!" );

		// estamplish a connection to the stomp listener
		$this->set_stomp_connection();

		$this->log( "Preparing to process up to {$this->batch_size} pending transactions.", LOG_LEVEL_DEBUG );

		// batch process pending transactions
		for ( $i = 0; $i < $this->batch_size; $i++ ) {
			// empty pending_transaction
			if ( isset( $message )) unset( $message );

			// fetch the latest pending transaction from the queue (Stomp_Frame object)
			$message = $this->fetch_message( $this->activemq_pending_queue );
			// if we do not get a pending transaction back...
			if ( !$message ) {
				$this->log( "There are no more pending transactions to process.", LOG_LEVEL_DEBUG );
				break;
			}
			
			// the message is in it's raw format, we need to decode just it's body
			$pending_transaction = json_decode( $message->body, TRUE );
			$this->log( "Pending transaction: " . print_r( $pending_transaction, TRUE ), LOG_LEVEL_DEBUG );

			// fetch the payflow pro status of this transaction
			$status = $this->fetch_payflow_transaction_status( $pending_transaction['gateway_txn_id'] );

			// determine the result code from the payflow pro status message
			$result_code = $this->parse_payflow_transaction_status( $status );

			// handle the pending transaction based on the payflow pro result code
			$this->handle_pending_transaction( $result_code, json_encode( $pending_transaction ));
	
			$ack_response = $this->stomp->ack( $message );
			$this->log( "Ack response: $ack_response", LOG_LEVEL_DEBUG );
		}
		$this->log( "Processed $i messages." );
	}

	/**
	 * Fetch latest raw message from a queue
	 *
	 * @param $destination string of the destination path from where to fetch a message
	 * @return mixed raw message (Stomp_Frame object) from Stomp client or False if no msg present
	 */
	protected function fetch_message( $destination ) {
		$this->log( "Attempting to connect to queue at: $destination", LOG_LEVEL_DEBUG );
		
		$this->stomp->subscribe( $destination );
		
		$this->log( "Attempting to pull queued item", LOG_LEVEL_DEBUG );
		$message = $this->stomp->readFrame();
		return $message;
	}

	/**
	 * Send a message to the queue
	 *
	 * @param $destination string of the destination path for where to send a message
	 * @param $message string the (formatted) message to send to the queue
	 * @param $options array of additional Stomp options
	 * @return bool result from send, FALSE on failure
	 */
	protected function queue_message( $destination, $message, $options = array( 'persistent' => TRUE )) {
		$this->log( "Attempting to queue message...", LOG_LEVEL_DEBUG );
		$sent = $this->stomp->send( $destination, $message, $options );
		$this->log( "Result of queuing message: $sent", LOG_LEVEL_DEBUG );
		return $sent;
	}

	/**
	 * Fetch the PayflowPro status of a transaction.
	 *
	 * @param $transaction_id string of the original ID of the transaction to status check
	 * @return string containing the raw status message returned by PayflowPro
	 */
	protected function fetch_payflow_transaction_status( $transaction_id ) {
		$this->log( "Transaction ID: $transaction_id", LOG_LEVEL_DEBUG );
		// create payflow query string, include string lengths
		$queryArray = array(
			'TRXTYPE' => 'I',
			'TENDER'  => 'C',
			'USER'  => $this->pfp_user_id, //$payflow_data['user'],
			'VENDOR' => $this->pfp_vendor_id, //$payflow_data['vendor'],
			'PARTNER' => $this->pfp_partner_id, //$payflow_data['partner'],
			'PWD' => $this->pfp_password, //$payflow_data['password'],
			'ORIGID' => $transaction_id,
		);
		$this->log( "PayflowPro query array: " . print_r( $queryArray, TRUE ), LOG_LEVEL_DEBUG );

		// format the query string for PayflowPro		
		foreach ( $queryArray as $name => $value ) {
			$query[] = $name . '[' . strlen( $value ) . ']=' . $value;
		}
		$payflow_query = implode( '&', $query );
		$this->log( "PayflowPro query array (formatted): " . print_r( $payflow_query, TRUE ), LOG_LEVEL_DEBUG );

		// assign header data necessary for the curl_setopt() function
		$order_id = date( 'ymdH' ) . rand( 1000, 9999 ); //why?
		$user_agent = $_SERVER['HTTP_USER_AGENT'];
		$headers[] = 'Content-Type: text/namevalue';
		$headers[] = 'Content-Length : ' . strlen( $payflow_query );
		$headers[] = 'X-VPS-Client-Timeout: 45';
		$headers[] = 'X-VPS-Request-ID:' . $order_id;
		$ch = curl_init();

		curl_setopt( $ch, CURLOPT_URL, $this->pfp_url );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
		curl_setopt( $ch, CURLOPT_USERAGENT, $user_agent );
		curl_setopt( $ch, CURLOPT_HEADER, 1 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 90 );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 0 );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $payflow_query );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST,  2 );
		curl_setopt( $ch, CURLOPT_FORBID_REUSE, true );
		curl_setopt( $ch, CURLOPT_POST, 1 );

		// As suggested in the PayPal developer forum sample code, try more than once to get a response
		// in case there is a general network issue 
		for ( $i=1; $i <=3; $i++ ) {
			$this->log( "Attempt #$i to connect to PayflowPro...", LOG_LEVEL_DEBUG );
			$status = curl_exec( $ch );
			$headers = curl_getinfo( $ch );

			if ( $headers['http_code'] != 200 && $headers['http_code'] != 403 ) {
				sleep( 5 );
			} elseif ( $headers['http_code'] == 200 || $headers['http_code'] == 403 ) {
				$this->log( "Succesfully connected to PayflowPro", LOG_LEVEL_DEBUG );
				break;
			}
		}

		if ( $headers['http_code'] != 200 ) {
			$this->log( "No response from PayflowPro after $i attempts." );
			curl_close( $ch );
			exit(1);
		}

		curl_close( $ch );

		$this->log( "PayflowPro reported status: $status", LOG_LEVEL_DEBUG );
		return $status;
	}

	/**
	 * Parse the result code out of PayflowPro's status message.
	 *
	 * This is modified from queue2civicrm_payflow_result() in the Drupal queue2civicrm module.
	 * That code, however, seemed to be cataloging all of the key/value pairs in the status
	 * message.  Since we only care about the result code, that's all I'm looking for.  
	 * Perhaps it is more favorable to return an aray of the key/value pairs in the status
	 * message...
	 * 
	 * @status string The full status message returned by a PayflowPro queyry
	 * @return int PayflowPro result code, FALSE on failure
	 */
	protected function parse_payflow_transaction_status( $status ) {
		// we only really care about the 'RESULT' portion of the status message
		$result = strstr( $status, 'RESULT' );

		// log the result string?
		$this->log( "PayflowPro RESULT string: $result", LOG_LEVEL_DEBUG );

		// establish our key/value positions in the string to facilitate extracting the value
		$key_position = strpos( $result, '=' );
		$value_position = strpos( $result, '&' ) ? strpos( $result, '&' ) : strlen( $result) ;

		$result_code = substr( $result, $key_position + 1, $value_position - $key_position - 1 );
		$this->log( "PayflowPro result code: $result_code", LOG_LEVEL_DEBUG );
		return $result_code;
	}

	/**
	 * Apropriately handles pending transactions based on the PayflowPro result code
	 *
	 * @param int PayflowPro result code
	 * @param string Formatted message to send to a queue
	 */
	protected function handle_pending_transaction( $result_code, $message ) {
		switch ( $result_code ) {
			case "0": // push to confirmed queue
				$this->log( "Attempting to push message to confirmed queue: " . print_r( $message, TRUE ), LOG_LEVEL_DEBUG );
				if ( $this->queue_message( $this->activemq_confirmed_queue, $message )) {
					$this->log( "Succesfully pushed message to confirmed queue.", LOG_LEVEL_DEBUG );
				}
				break;
			case "126": // push back to pending queue
			case "26": //push back to pending queue
				$this->log( "Attempting to push message back to pending queue: " . print_r( $message, TRUE ), LOG_LEVEL_DEBUG );
				if ( $this->queue_message( $this->activemq_pending_queue, $message )) {
					$this->log( "Succesfully pushed message back to pending queue", LOG_LEVEL_DEBUG );
				}
				break;
			default:
				$this->log( "Message ignored: " . print_r( $message, TRUE ), LOG_LEVEL_DEBUG );
				break;
		}
	}

	/** 
	 * Loads configuration options
	 *
	 * Config options can be set in localSettings.php or with arguments passed in via the command
	 * line.  Command line arguments will override localSettings.php defined options.
	 */
	protected function load_config_options() {
		// Associative array of mediawiki option => maintenance arg name
		$options = array (
					'wgPayflowProURL' => 'pfp-url',
					'wgPayflowProPartnerID' => 'pfp-partner-id',
					'wgPayflowProVendorID' => 'pfp-vendor-id',
					'wgPayflowProUserID' => 'pfp-user-id',
					'wgPayflowProPassword' => 'pfp-password',
					'wgActiveMQStompURI' => 'activemq-stomp-uri',
					'wgActiveMQPendingPath' => 'activemq-pending-queue',
					'wgActiveMQConfirmedPath' => 'activemq-confirmed-queue',
					'wgActiveMQPendingProcessingBatchSize' => 'batch-size',
					'wgActiveMQPendingProcessLogLevel' => 'log-level' );

		// loop through our options and set their values, 
		// overrides local settings with command line options
		foreach ( $options as $mw_option => $m_arg_name ) {
			global ${$mw_option};

			// replace - with _ from CL args to map to class properties
			$property = str_replace( "-", "_", $m_arg_name );

			// set class property with the config option
			$this->$property = parent::getOption( $m_arg_name, ${$mw_option} );

			$this->log( "$property = $mw_option, $m_arg_name, ${$mw_option}", LOG_LEVEL_DEBUG );
			$this->log( "$property = {$this->$property}", LOG_LEVEL_DEBUG );
		}
	}

	/**
	 * Registers parameters with the maintenance system
	 */
	protected function register_params() {
		//parent::addOption( $name, $description, $required=false, $withArg=false )
		parent::addOption( 'pfp-url', 'The PayflowPro URL', FALSE, TRUE );
		parent::addOption( 
			'pfp-partner-id', 
			'Authorized reseller ID for PayflowPro (eg "PayPal")', 
			FALSE, 
			TRUE );
		parent::addOption( 'pfp-vendor-id', 'The PayflowPro merchant login ID', FALSE, TRUE );
		parent::addOption( 
			'pfp-user-id', 
			"If one or more users are set up, this should be the authorized PayflowPro user ID, otherwise this should be the same as pfp-vendor-id", 
			FALSE, 
			TRUE );
		parent::addOption( 
			'pfp-password', 
			'The PayflowPro merchant login password.  ** Declaring this here could be a security risk.',
			FALSE,
			TRUE );
		parent::addOption( 
			'activemq-stomp-uri', 
			'The URI to the ActiveMQ Stomp listener', 
			FALSE, 
			TRUE );
		parent::addOption( 
			'activemq-pending-queue', 
			'The path to the ActiveMQ pending queue', 
			FALSE, 
			TRUE );
		parent::addOption(
			'activemq-confirmed-queue',
			'The path to the ActiveMQ confirmed queue',
			FALSE,
			TRUE );
		// I know there is a method to handle batch size this in the parent class, but it makes me
		// do things I don't want to do.
		parent::addOption(
			'batch-size',
			'The number of queue items to process.  Default: ' . $this->batch_size,
			FALSE,
			TRUE );
		parent::addOption( 
			'log-level', 
			"The level of logging you would like to enable.  Options:\n\t0 - No output\n\t1 - Normal, minimal output\n\t2 - Debug, verbose output", 
			FALSE, 
			TRUE );
	}

	/**
	 * Establishes a connection to the stomp listener
	 *
	 * Stomp listner URI set in config options (via command line or localSettings.php).
	 * If a connection cannot be established, will exit with non-0 status.
	 */
	protected function set_stomp_connection() {
		//attempt to connect, otherwise throw exception and exit
		$this->log( "Attempting to connect to Stomp listener: {$this->activemq_stomp_uri}", LOG_LEVEL_DEBUG );
		try {
			//establish stomp connection
			$this->stomp = new Stomp( $this->activemq_stomp_uri );
			$this->stomp->connect();
			$this->log( "Successfully connected to Stomp listener", LOG_LEVEL_DEBUG );
		} catch (Stomp_Exception $e) {
			$this->log( "Stomp connection failed: " . $e->getMessage() );
			exit(1);
		}
	}

	/**
	 * Logs messages of less than or equal value to the defined log level.
	 *
	 * Log levels available are defined by the constants LOG_LEVEL_*.  The log level for the script
	 * defaults to LOG_LEVEL_INFO but can be overridden in LocalSettings.php or via a command line
	 * argument passed in at run time.
	 * 
	 * @param $message string containing the message you wish to log
	 * @param $level int of the highest log level you wish to output messages for
	 */
	protected function log( $message, $level=LOG_LEVEL_INFO ) {
		if ( $this->log_level >= $level ) echo date('c') . ": " . $level . " : " .  $message . "\n";
	}

	public function __destruct() {
		// clean up our stomp connection
		$this->log( "Cleaning up stomp connection...", LOG_LEVEL_DEBUG );
		if ( isset( $this->stomp )) $this->stomp->disconnect();
		$this->log( "Stomp connection cleaned up", LOG_LEVEL_DEBUG );
		$this->log( "Exiting gracefully" );

	}
}

$maintClass = "StompPFPPendingProcessor";
require_once( DO_MAINTENANCE );
