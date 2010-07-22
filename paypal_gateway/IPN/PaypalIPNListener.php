<?php
/**
 * PayPal IPN listener and handler.  Also pushes messages into the ActiveMQ queueing system.
 *
 * This is currently designed to act as a mechanism for pushing transactions received from PayPal's
 * IPN system into a 'pending' queue from ActiveMQ.  Once a transaction is verified, it is removed
 * from the pending queue and pushed into a 'verified' queue.  If it is not verified, a copy is left
 * in the pending queue.  This particular logic takes place in execute().
 *
 * Generally speaking, this should likely be abstracted to allow for more flexible use cases, as what
 * is outlined above is pretty specific, but most of the other methods should allow for some flexibility -
 * particularly if you were to subclass this.
 * 
 * Also, this is close to being useable with other queueing systems that can talk Stomp.  However, this 
 * currently employs some things that are likely unique to ActiveMQ, namely setting some custom header
 * information for items going into a pending queue and then using ActiveMQ 'selectors' to pull out
 * a specific message.
 *
 * Does not actually require Mediawiki to run, can be run as stand alone or can be integrated 
 * with a Mediawiki extension.  See StandaloneListener.php.example for a guide on how to do this.
 *
 * Configurable variables:
 *	log_level => $int // 0, 1, or 2 (see constant definitions below for options)
 *  stomp_path => path to Stomp.php
 *  pending_queue => the queue to push pending items to
 *  verified_queue => the queue to push verfiied items to
 *  activemq_stomp_uri => the URI for the activemq broker
 *  contrib_db_host => the hostname where the contribution_tracking table lives
 *  contrib_db_username => the username for the db where contribution_tracking lives
 *  contrib_db_password => the pw for accessing the db where contribution_tracking lives
 *  conrtib_db_name => the db name where contribution_tracking lives
 *
 * Note that the contrib_db* variables are likely of no use to you, unless you're using CiviCRM with Drupal and
 * are using a special contribution tracking module... So if you're not doing that, you can likely 
 * leave those out of your config.
 *
 * PayPal IPN docs: https://cms.paypal.com/us/cgi-bin/?&cmd=_render-content&content_ID=developer/e_howto_admin_IPNIntro
 *
 * @author Arthur Richards <arichards@wikimedia.org>
 * @TODO: 
 *		add output for DB connection/query
 *		abstract out the contribution_tracking stuff so this is more flexible?
 */

/** Set available log levels **/
DEFINE( 'LOG_LEVEL_QUIET', 0 ); // output nothing
DEFINE( 'LOG_LEVEL_INFO', 1 ); // output minimal info
DEFINE( 'LOG_LEVEL_DEBUG', 2 ); // output lots of info

class PaypalIPNProcessor {

	// set the apropriate logging level
	$log_level = LOG_LEVEL_INFO;
	
	// path to Stomp
	$stomp_path = "../../activemq_stomp/Stomp.php";

	// path to pending queue
	$pending_queue = '/queue/pending_paypal';

	// path to the verified queue
	$verified_queue = '/queue/donations';

  // URI to activeMQ
	$activemq_stomp_uri = 'tcp://localhost:61613';

	/**
	 * Class constructor, sets configurable parameters
	 *
	 * @param $opts array of key, value pairs where the 'key' is the parameter name and the
	 *	value is the value you wish to set
	 */
	function __construct( $opts = array() ) {
		// set the log level
		if ( array_key_exists( 'log_level', $opts )) {
			$this->log_level = $opts[ 'log_level' ];
			unset( $opts[ 'log_level'] );
		}

		$this->out( "Loading Paypal IPN processor" ); 

		// set parameters
		foreach ( $opts as $key => $value ) {
			$this->{$key} = $value;

			// star out passwords in the log!!!!
			if ( $key == 'contrib_db_password' ) $value = '******';
			
			$this->out( "Setting parameter $key as $value.", LOG_LEVEL_DEBUG );
		}

		//prepare our stomp connection
		$this->set_stomp_connection();
	}

	/**
	 * Execute IPN procesing.
	 *
	 * Take the data sent from a PayPal IPN request, verify it against the IPN, then push the
	 * transaction to the queue.  Before verifying the transaction against the IPN, this will
	 * place the transaction originally received in the pending queue.  If the transaction is
	 * verified, it will be removed from the pending queue and placed in an accepted queue.  If
	 * it is not verified, it will be left in the pending queue for dealing with in some other
	 * fashion.
	 *
	 * @param $data Array containing the message received from the IPN, likely the contents of 
	 *	$_POST
	 */
	function execute( $data ) {

		//make sure we're actually getting something posted to the page.
		if ( empty( $data )) {
			$this->out( "Received an empty object, nothing to verify." );
			return;
		}

		// connect to stomp
		$this->set_stomp_connection();

		//push message to pending queue
		$contribution = $this->ipn_parse( $data );

		// generate a unique id for the message 2 ensure we're manipulating the correct message later on
		$tx_id = time() . '_' . mt_rand(); //should be sufficiently unique...
		$headers = array( 'persistent' => TRUE, 'JMSCorrelationID' => $tx_id );
		$this->out( "Setting JMSCorrelationID: $tx_id", LOG_LEVEL_DEBUG );

		// do the queueing - perhaps move out the tracking checking to its own func?
		if ( !$this->queue_message( $this->pending_queue, json_encode( $contribution ), $headers )) {
			$this->out( "There was a problem queueing the message to the queue: " . $this->pending_queue );
			$this->out( "Message: " . print_r( $contribution, TRUE ), LOG_LEVEL_DEBUG );
		}


		//verify the message with PayPal
		if ( !$this->ipn_verify( $data )) {
			$this->out( "Message did not pass PayPal verification." );
			$this->out( "\$_POST contents: " . print_r( $data, TRUE ), LOG_LEVEL_DEBUG );
			return;
		}

		// pull the message off of the pending queue using a 'selector' to make sure we're getting the right msg
		$properties['selector'] = "JMSCorrelationID = '$tx_id'";
		$this->out( "Attempting to pull mssage from pending queue with JMSCorrelationID = $tx_id", LOG_LEVEL_DEBUG );
		$msg = $this->fetch_message( $this->pending_queue, $properties );
		if ( $msg ) {
			$this->out( "Pulled message from pending queue: " . print_r( json_decode( $msg ), TRUE ), LOG_LEVEL_DEBUG);
		} else {
			$this->out( "FAILED retrieving message from pending queue.", LOG_LEVEL_DEBUG );
			return;
		}

		// push to verified queue
		if ( !$this->queue_message( $this->verified_queue, $msg->body )) {
			$this->out( "There was a problem queueing the message to the quque: " . $this->verified_queue );
			$this->out( "Message: " . print_r( $contribution, TRUE ), LOG_LEVEL_DEBUG );
			return;
		}

		// remove from pending
		$this->out( "Attempting to remove message from pending.", LOG_LEVEL_DEBUG );
		if ( !$this->stomp->ack( $msg )) {
			$this->out( "There was a problem remoivng the verified message from the pending queue: " . print_r( json_decode( $msg, TRUE )));
		}
	}

	/**
	 * Verify IPN's message validitiy
	 * 
	 * Yoinked from fundcore_paypal_verify() in fundcore/gateways/fundcore_paypal.module Drupal module
	 * @param $post_data array of post data - the message received from PayPal
	 * @return bool
	 */
	public function ipn_verify( $post_data ) {
		if ( $post_data[ 'payment_status' ] != 'Completed' ) {
			// order not completed
			$this->out( "Message not marked as complete." );
			return FALSE;
		}

		if ( $post_data[ 'mc_gross' ] <= 0 ) {
			$this->out( "Message has 0 or less in the mc_gross field." );
			return FALSE;
		}			

		// url to respond to paypal with verification response
		$postback_url = 'https://www.paypal.com/cgi-bin/webscr'; // should this be configurable?
		if (isset($post_data['test_ipn'])) {
			$postback_url = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
		}

		// respond with exact same data/structure + cmd=_notify-validate
		$attr = $post_data;
		$attr['cmd'] = '_notify-validate';
							    
		// send the message back to PayPal for verification
		$status = $this->curl_download( $postback_url, $attr );
		if ($status != 'VERIFIED') {
			$this->out( "The message could not be verified." );
			$this->out( "Returned with status: $status", LOG_LEVEL_DEBUG );
			return FALSE;
		}

		return TRUE;
	}

	/**
	 * Parse the PayPal message/post data into the format we need for ActiveMQ
	 *
	 * @param $post_data array containing the $_POST data from PayPal
	 * @return array containing the parsed/formatted message for stuffing into ActiveMQ
	 */
	public function ipn_parse( $post_data ) {
		$this->out( "Attempting to parse: " . print_r( $post_data, TRUE ), LOG_LEVEL_DEBUG );
		$contribution = array();

		$timestamp = strtotime($post_data['payment_date']);

		// Detect if we're using the new-style (likely unique to Wikimedia) - this should be handled elsewhere
		if (is_numeric($post_data['option_selection1'])) {
			// get the database connection to the tracking table
			$this->contribution_tracking_connection();
			$tracking_data = $this->get_tracking_data( $post_data['option_selection1'] );
			if ( !$tracking_data ) { //we have a problem!
				$this->out( "There is no contribution ID associated with this transaction." );
				exit();
			}
			$contribution['contribution_tracking_id'] = $post_data['option_selection1'];
			$contribution['optout'] = $tracking_data['optout'];
			$contribution['anonymous'] = $tracking_data['anonymous'];
			$contribution['comment'] = $tracking_data['note'];
		} else {
			$split = explode(';', $post_data['option_selection1']);
			$contribution['anonymous'] = ($split[0] != 'public' && $split[0] != 'Mention my name');
			$contribution['comment'] = $post_data['option_selection2'];
		}

		$contribution['email'] = $post_data['payer_email'];
		$contribution['first_name'] = $post_data['first_name'];
		$contribution['last_name'] = $post_data['last_name'];

		$split = split("\n", str_replace("\r", '', $post_data['address_street']));

		$contribution['street_address'] = $split[0];
		$contribution['supplemental_address_1'] = $split[1];
		$contribution['city'] = $post_data['address_city'];
		$contribution['original_currency'] = $post_data['mc_currency'];
		$contribution['original_gross'] = $post_data['mc_gross'];
		$contribution['fee'] = $post_data['mc_fee'];  
		$contribution['gross'] = $post_data['mc_gross']; 
		$contribution['net'] = $contribution['gross'] - $contribution['fee'];
		$contribution['date'] = $timestamp;

		return $contribution;
	}

	/**
	 * Connect to a URL, send optional post variables, return data
	 *
	 * Yoinked from _fundcore_paypal_download in fundcore/gateways/fundcore_paypal.module Drupal module
	 * @param $url String of the URL to connect to
	 * @param $vars Array of POST variables
	 * @return String containing the output returned from Server
	 */
	public function curl_download( $url, $vars = NULL ) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 0); 
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); 
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
		    
		if ($vars !== NULL) {
			curl_setopt($ch, CURLOPT_POST, 1); 
			curl_setopt($ch, CURLOPT_POSTFIELDS, $vars);
		}
		$data = curl_exec($ch);
		if (!$data) {
			$data = curl_error($ch);
			$this->out( "Curl error: " . $data );
		}
		curl_close($ch);
		return $data;
	}

	/** 
	 * Establishes a connection to the stomp listener
	 *
	 * Stomp listner URI set in config options (via command line or localSettings.php).
	 * If a connection cannot be established, will exit with non-0 status.
	 */
	protected function set_stomp_connection() {
		require_once( $this->stomp_path );
		//attempt to connect, otherwise throw exception and exit
		$this->out( "Attempting to connect to Stomp listener: {$this->activemq_stomp_uri}", LOG_LEVEL_DEBUG );
		try {
			//establish stomp connection
			$this->stomp = new Stomp( $this->activemq_stomp_uri );
			$this->stomp->connect();
			$this->out( "Successfully connected to Stomp listener", LOG_LEVEL_DEBUG );
		} catch (Stomp_Exception $e) {
			$this->out( "Stomp connection failed: " . $e->getMessage() );
			exit(1);
		}   
	}

    /** 
     * Send a message to the queue
     *
     * @param $destination string of the destination path for where to send a message
     * @param $message string the (formatted) message to send to the queue
     * @param $options array of additional Stomp options
     * @return bool result from send, FALSE on failure
     */
    public function queue_message( $destination, $message, $options = array( 'persistent' => TRUE )) {
        $this->out( "Attempting to queue message to $destination", LOG_LEVEL_DEBUG );
        $sent = $this->stomp->send( $destination, $message, $options );
        $this->out( "Result of queuing message: $sent", LOG_LEVEL_DEBUG );
        return $sent;
    }   

    /**
     * Fetch latest raw message from a queue
     *
     * @param $destination string of the destination path from where to fetch a message
     * @return mixed raw message (Stomp_Frame object) from Stomp client or False if no msg present
	 */
	public function fetch_message( $destination, $properties = NULL ) {
		$this->out( "Attempting to connect to queue at: $destination", LOG_LEVEL_DEBUG );
		if ( $properties ) $this->out( "With the following properties: " . print_r( $properties, TRUE ));
		$this->stomp->subscribe( $destination, $properties );
		$this->out( "Attempting to pull queued item", LOG_LEVEL_DEBUG );
		$message = $this->stomp->readFrame();
		return $message;
	}

	/**
	 * Establish a connection with the contribution database.
	 *
	 * The properties contrib_db_host, contrib_db_username, contrib_db_password and 
	 * contrib_db_name should be set prior to the execution of this method.
	 */
	protected function contribution_tracking_connection() {
		$this->contrib_db = mysql_connect(
			$this->contrib_db_host,
			$this->contrib_db_username,
			$this->contrib_db_password );
		mysql_select_db( $this->contrib_db_name, $this->contrib_db );
	}

	/**
	 * Fetches tracking data we need to for this transaction from the contribution_tracking table
	 * 
	 * @param int the ID of the transaction we care about
	 * @return array containing the key=>value pairs of data from the contribution_tracking table
	 */
	protected function get_tracking_data( $id ) {
		//sanitize the $id
		$id = mysql_real_escape_string( $id );
		$query = "SELECT * FROM contribution_tracking WHERE id=$id";
		$this->out( "Preparing to run query on contribution_tracking: $query", LOG_LEVEL_DEBUG );
		$result = mysql_query( $query );
		$row = mysql_fetch_assoc( $result );
		$this->out( "Query result: " . print_r( $row, TRUE ), LOG_LEVEL_DEBUG );
		return $row;
	}

	/**
	 * Formats text for output.
	 *
	 * @param $msg String a message to output.
	 * @param $level the Level at which the message should be output.
	 */
	protected function out( $msg, $level=LOG_LEVEL_INFO ) {
		if ( $this->log_level >= $level ) echo date( 'c' ) . ": " . $msg . "\n";
	}

	public function __destruct() {
		$this->out( "Exiting gracefully." );
	}
}
