<?php
/**
 * Special class to act as IPN listener and handler.  Also pushes messages into the ActiveMQ 
 * queueing system.
 *
 * NOTE: THIS IS EXPERIMENTAL AND INCOMPLETE
 *
 * Requires ContributionTracking extension.
 *
 * Configurable variables:
 *   $wgPayPalIPNProcessingLogLevel - can be one of the defined LOG_LEVEL_* constants.
 *
 * PayPal IPN docs: https://cms.paypal.com/us/cgi-bin/?&cmd=_render-content&content_ID=developer/e_howto_admin_IPNIntro
 *
 * @author Arthur Richards <arichards@wikimedia.org>
 * @TODO: add a better mechanism for changing log level
 */

/** Set available log levels **/
DEFINE( 'LOG_LEVEL_QUIET', 0 ); // output nothing
DEFINE( 'LOG_LEVEL_INFO', 1 ); // output minimal info
DEFINE( 'LOG_LEVEL_DEBUG', 2 ); // output lots of info

class PaypalIPNProcessing extends UnlistedSpecialPage {

	// set the apropriate logging level
	protected $log_level = LOG_LEVEL_INFO;
	
	// path to Stomp
	protected $stomp_path = dirname( __FILE__ ) . "/../../activemq_stomp/Stomp.php";

	// path to pending queue
	protected $pending_queue = '/queue/pending_paypal';

	function __construct() {
		parent::__construct( 'PaypalIPNProcessing' );
		wfLoadExtensionMessages( 'PaypalIPNProcessing' );
		$this->out( "Loading Paypal IPN processor" ); 

		if ( isset( $wgPayPalIPNProcessingLogLevel )) {
			$this->log_level = $wgPayPalIPNProcessingLogLevel;
		}

		if ( isset( $wgPayPalIPNProcessingStompPath )) {
			$this->stomp_path = $wgPayPalIPNProcessingStompPath;
		}

		if ( isset( $wgPayPalIPNProcessingPendingQueue )) {
			$this->pending_queue = $wgPayPalIPNProcessingPendingQueue;
		}
	}

	/**
	 * Output in plain text?
	 */
	function execute( $par ) {
		global $wgRequest, $wgOut;
		$wgOut->disable();
		header( "Content-type: text/plain; charset=utf-8" );

		//make sure we're actually getting something posted to the page.
		if ( empty( $_POST )) {
			$this->out( "Received an empty post object." );
			return;
		}

		// connect to stomp
		$this->set_stomp_connection();

		//push message to pending queue
		$contribution = $this->ipn_parse( $_POST );
		// do the queueing - perhaps move out the tracking checking to its own func?
		if ( !$this->queue_message( $this->pending_queue, $contribution )){
			$this->out( "There was a problem queueing the message to the queue: " . $this->pending_queue );
			$this->out( "Message: " . print_r( $contribution, TRUE ), LOG_LEVEL_DEBUG );
		}


		//verify the message with PayPal
		if ( !$this->ipn_verify( $_POST )) {
			$this->out( "Message did not pass PayPal verification." );
			$this->out( "\$_POST contents: " . print_r( $_POST, TRUE ), LOG_LEVEL_DEBUG );
			return;
		}

		//push to donations queue, remove from pending
	}

	/**
	 * Verify IPN's message validitiy
	 * 
	 * Yoinked from fundcore_paypal_verify() in fundcore/gateways/fundcore_paypal.module Drupal module
	 * @param $post_data array of post data - the message received from PayPal
	 * @return bool
	 */
	protected function ipn_verify( $post_data ) {
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
		$postback_url = 'https://www.paypal.com/cgi-bin/webscr';
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
	protected function ipn_parse( $post_data ) {
		$contribution = array();

		$timestamp = strtotime($post_data['payment_date']);

		// Detect if we're using the new-style
		if (is_numeric($post_data['option_selection1'])) {
			// get the database connection to the tracking table
			$tracking_db = contributionTrackingConnection();
			
			// Query from Drupal: $tracking_data = db_fetch_array(db_query('SELECT * FROM {contribution_tracking} WHERE id = %d', $post_data['option_selection1']));
			$tracking_query = $tracking_db->select( 
				'contribution_tracking', 
				array( 'optout', 'anonymous', 'note' ), 
				array( 'id' => $post_data[ 'option_selection1' ]);
			$tracking_data = $tracking_query->fetchRow();
			
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
		$contribution['fee'] = $post_data['mc_fee'],  
		$contribution['gross'] = $post_data['mc_gross'], 
		$contribution['net'] = $contribution['gross'] - $contribution['fee'];
		$contribution['date'] = $timestamp;

		//print_r the contribution?

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
	protected function curl_download( $url, $vars = NULL ) {
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
    protected function queue_message( $destination, $message, $options = array( 'persistent' => TRUE )) {
        $this->out( "Attempting to queue message...", LOG_LEVEL_DEBUG );
        $sent = $this->stomp->send( $destination, $message, $options );
        $this->out( "Result of queuing message: $sent", LOG_LEVEL_DEBUG );
        return $sent;
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
