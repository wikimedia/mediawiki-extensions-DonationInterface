<?php

/*
 * Create <donate /> tag to include landing page donation form
 */

function efStompSetup( &$parser ) {
	global $wgDonationInterfaceEnableStomp;

	if ( $wgDonationInterfaceEnableStomp !== true ) {
		return true;
	}

	// redundant and causes Fatal Error
	// $parser->disableCache();

	$parser->setHook( 'stomp', 'efStompTest' );

	return true;
}

function efStompTest( $input, $args, &$parser ) {
	$parser->disableCache();

	$output = "STOMP Test page";

	WmfFramework::runHooks( 'gwStomp', array( &$transaction ) );

	return $output;
}

/**
 * Hook to send complete transaction information to ActiveMQ server
 *
 * @global string $wgStompServer ActiveMQ server name.
 * @global string $wgStompQueueNames Array containing names of queues. Will use entry either on key
 * of the strcat(payment_method '-$queue'), or '$queue'
 *
 * @param array $transaction Key-value array of staged and ready donation data.
 * @param string $queue Name of the queue to use, ie: 'limbo' or 'pending'
 *
 * @return bool Just returns true all the time. Presumably an indication that
 * nothing exploded big enough to kill the whole thing.
 * @throws RuntimeException
 */
function sendSTOMP( $transaction, $queue = 'default' ) {
	global $IP, $wgStompServer, $wgStompQueueNames,
		$wgDonationInterfaceEnableStomp;

	if ( $wgDonationInterfaceEnableStomp !== true ) {
		return true;
	}

	// Find the queue name
	if ( array_key_exists( $transaction['payment_method'] . "-$queue", $wgStompQueueNames ) ) {
		$queueName = $wgStompQueueNames[$transaction['payment_method'] . "-$queue"];
	} elseif ( array_key_exists( $queue, $wgStompQueueNames ) ) {
		$queueName = $wgStompQueueNames[$queue];
	} else {
		// Sane default...
		$queueName = "test-$queue";
		WmfFramework::debugLog( 'stomp', "We should consider adding a queue name entry for $queue" );
	}

	// If it turns out the queue name is false or empty, we don't actually want to use this queue
	if ( $queueName == false ) {
		return true;
	}

	static $sourceRevision = null;
	if ( !$sourceRevision ) {
		$versionStampPath = "$IP/.version-stamp";
		if ( file_exists( $versionStampPath ) ) {
			$sourceRevision = trim( file_get_contents( $versionStampPath ) );
		} else {
			$sourceRevision = 'unknown';
		}
	}

	// Create the message and associated properties
	$properties = array(
		'persistent' => 'true',
		'payment_method' => $transaction['payment_method'],
		'php-message-class' => $transaction['php-message-class'],
		'gateway' => $transaction['gateway'],
		'source_name' => 'DonationInterface',
		'source_type' => 'payments',
		'source_host' => WmfFramework::getHostname(),
		'source_run_id' => getmypid(),
		'source_version' => $sourceRevision,
		'source_enqueued_time' => time(),
	);

	if ( array_key_exists( 'antimessage', $transaction ) ) {
		$message = '';
		$properties['antimessage'] = 'true';
	} else {
		if ( array_key_exists( 'freeform', $transaction ) ) {
			$message = $transaction;
			unset( $message['freeform'] );
		} else {
			$message = createQueueMessage( $transaction );
		}
		$message = json_encode( $message );
	}

	if ( array_key_exists( 'correlation-id', $transaction ) ) {
		$properties['correlation-id'] = $transaction['correlation-id'];
	}

	// make a connection
	$con = new Stomp( $wgStompServer );
	$con->connect();

	// send a message to the queue
	$result = $con->send( "/queue/$queueName", $message, $properties );

	if ( !$result ) {
		throw new RuntimeException( "Send to $queueName failed for this message: $message" );
	}

	$con->disconnect();

	return true;
}

/**
 * Hook to send transaction information to ActiveMQ server
 * @deprecated Use sendSTOMP with $queue = 'pending' instead
 *
 * @param array $transaction Key-value array of staged and ready donation data.
 * @return bool Just returns true all the time. Presumably an indication that
 * nothing exploded big enough to kill the whole thing.
 */
function sendPendingSTOMP( $transaction ) {
	global $wgDonationInterfaceEnableStomp;

	if ( $wgDonationInterfaceEnableStomp !== true ) {
		return true;
	}

	return sendSTOMP( $transaction, 'pending' );
}

/**
 * Hook to send transaction information to ActiveMQ server
 * @deprecated Use sendSTOMP with $queue = 'limbo' instead
 *
 * @param array $transaction Key-value array of staged and ready donation data.
 * @return bool Just returns true all the time. Presumably an indication that
 * nothing exploded big enough to kill the whole thing.
 */
function sendLimboSTOMP( $transaction ) {
	return sendSTOMP( $transaction, 'limbo' );
}

/**
 * Hook to send transaction information to ActiveMQ server
 * @deprecated Use sendSTOMP with $queue = 'limbo' instead
 *
 * @param array $transaction Key-value array of staged and ready donation data.
 * @return bool Just returns true all the time. Presumably an indication that
 * nothing exploded big enough to kill the whole thing.
 */
function sendFreeformSTOMP( $transaction, $queue ) {
	global $wgDonationInterfaceEnableStomp;

	if ( $wgDonationInterfaceEnableStomp !== true ) {
		return true;
	}

	$transaction['freeform'] = true;
	return sendSTOMP( $transaction, $queue );
}

/**
 * Assign correct values to the array of data to be sent to the ActiveMQ server
 * TODO: Probably something else. I don't like the way this works and neither do you.
 *
 * Older notes follow:
 * Currency in receiving module has currency set to USD, should take passed variable for these
 * PAssed both ISO and country code, no need to look up
 * 'gateway' = globalcollect, e.g.
 * 'date' is sent as $date("r") so it can be translated with strtotime like Paypal transactions (correct?)
 * Processor txn ID sent in the transaction response is assigned to 'gateway_txn_id' (PNREF)
 * Order ID (generated with transaction) is assigned to 'contribution_tracking_id'?
 * Response from processor is assigned to 'response'
 */
function createQueueMessage( $transaction ) {
	// specifically designed to match the CiviCRM API that will handle it
	// edit this array to include/ignore transaction data sent to the server
	$message = array(
		'contribution_tracking_id' => $transaction['contribution_tracking_id'],
		'utm_source' => $transaction['utm_source'],
		'language' => $transaction['language'],
		'referrer' => $transaction['referrer'],
		'email' => $transaction['email'],
		'first_name' => $transaction['fname'],
		'last_name' => $transaction['lname'],
		'street_address' => $transaction['street'],
		'city' => $transaction['city'],
		'state_province' => $transaction['state'],
		'country' => $transaction['country'],
		'postal_code' => $transaction['zip'],
		'gateway' => $transaction['gateway'],
		'gateway_account' => $transaction['gateway_account'],
		'gateway_txn_id' => $transaction['gateway_txn_id'],
		'payment_method' => $transaction['payment_method'],
		'payment_submethod' => $transaction['payment_submethod'],
		'response' => $transaction['response'],
		'currency' => $transaction['currency_code'],
		'fee' => '0',
		'gross' => $transaction['amount'],
		'user_ip' => $transaction['user_ip'],
		//the following int casting fixes an issue that is more in Drupal/CiviCRM than here.
		//The code there should also be fixed.
		'date' => ( int ) $transaction['date'],
	);

	//optional keys
	$optional_keys = array(
		'recurring',
		'optout',
		'anonymous',
		'street_supplemental',
		'utm_campaign',
		'utm_medium',
	);
	foreach ( $optional_keys as $key ) {
		if ( isset( $transaction[ $key ] ) ) {
			$message[ $key ] = $transaction[ $key ];
		}
	}

	//as this is just the one thing, I can't think of a way to do this that isn't actually more annoying. :/
	if ( isset( $message['street_supplemental'] ) ) {
		$message['supplemental_address_1'] = $message['street_supplemental'];
		unset( $message['street_supplemental'] );
	}

	return $message;
}

/**
 * Called by the orphan rectifier to change a queue message back into a gateway
 * transaction array, basically undoing the mappings from createQueueMessage
 *
 * @param array $transaction STOMP message
 *
 * @return array message with queue keys remapped to gateway keys
 */
function unCreateQueueMessage( $transaction ) {
	// For now, this function assumes that we have a complete queue message.
	// TODO: Something more robust and programmatic, as time allows. This whole file is just terrible.

	$rekey = array(
		'first_name' => 'fname',
		'last_name' => 'lname',
		'street_address' => 'street',
		'state_province' => 'state',
		'postal_code' => 'zip',
		'currency' => 'currency_code',
		'gross' => 'amount',
	);

	foreach ( $rekey as $stomp => $di ){
		if ( isset( $transaction[$stomp] ) ){
			$transaction[$di] = $transaction[$stomp];
			unset($transaction[$stomp]);
		};
	}

	return $transaction;
}


/**
 * Fetches all the messages in a queue that match the supplies selector.
 * Limiting to a completely arbitrary 50, just in case something goes amiss somewhere.
 * @param string $queue The target queue from which we would like to fetch things.
 *	To simplify things, specify either 'verified', 'pending', or 'limbo'.
 * @param string $selector Could be anything that STOMP will regard as a valid selector. For our purposes, we will probably do things like:
 *	$selector = "JMSCorrelationID = 'globalcollect-6214814668'", or
 *	$selector = "payment_method = 'cc'";
 * @param int $limit The maximum number of messages we would like to pull off of the queue at one time.
 * @return array an array of stomp messages, with a count of up to $limit.
 */
function stompFetchMessages( $queue, $selector = null, $limit = 50 ){
	global $wgStompQueueNames;

	static $selector_last = null;
	if ( !is_null( $selector_last ) && $selector_last != $selector ){
		$renew = true;
	} else {
		$renew = false;
	}
	$selector_last = $selector;

	// Get the actual name of the queue
	if ( array_key_exists( $queue, $wgStompQueueNames ) ) {
		$queue = $wgStompQueueNames[$queue];
	} else {
		$queue = $wgStompQueueNames['default'];
	}

	//This needs to be renewed every time we change the selectors.
	$stomp = getDIStompConnection( $renew );

	$properties = array( 'ack' => 'client' );
	if ( !is_null( $selector ) ){
		$properties['selector'] = $selector;
	}

	$stomp->subscribe( '/queue/' . $queue, $properties );
	$message = $stomp->readFrame();

	$return = array();

	while ( !empty( $message ) && count( $return ) < $limit ) {
		$return[] = $message;
		$stomp->subscribe( '/queue/' . $queue, $properties );
		$message = $stomp->readFrame();
	}

	return $return;
}


/**
 * Ack all of the messages in the array, thereby removing them from the queue.
 * @param array $messages
 */
function stompAckMessages( $messages = array() ){
	$stomp = getDIStompConnection();
	foreach ($messages as $message){
		if (!array_key_exists('redelivered', $message->headers)) {
			$message->headers['redelivered'] = 'true';
		}
		$stomp->ack($message);
	}
}

function getDIStompConnection( $renew = false ){
	global $wgStompServer;
	static $conn = null;
	if ( $conn === null || !$conn->isConnected() || $renew ) {
		if ( $conn !== null && $conn->isConnected() ){
			$conn->disconnect(); //just to be safe.
		}
		// make a connection
		$conn = new Stomp( $wgStompServer );
		$conn->connect();
	}
	return $conn;
}

function closeDIStompConnection(){
	$conn = getDIStompConnection();
	$conn->disconnect();
}
