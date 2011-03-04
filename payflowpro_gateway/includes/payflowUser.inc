<?php

/**
 * Payflow Pro account information
 * Payflow individual account information must be defined in the LocalSettings.php file
 */
function payflowUser() {
	// User account information from LocalSettings.php
	global $wgPayflowProPartnerID,
			$wgPayflowProVendorID,
			$wgPayflowProUserID,
			$wgPayflowProPassword,
			$wgPayflowProURL,
			$wgPayflowProTestingURL,
			$wgPayflowGatewayTest;

	$payflow_data = array(
		'partner' => $wgPayflowProPartnerID, // PayPal or original authorized reseller
		'vendor' => $wgPayflowProVendorID, // paypal merchant login ID
		'user' => $wgPayflowProUserID, // if one or more users are set up, authorized user ID, else same as VENDOR
		'password' => $wgPayflowProPassword, // merchant login password
		'paypalurl' => $wgPayflowProURL,
		'testingurl' => $wgPayflowProTestingURL, // Payflow testing URL
		'trxtype' => 'S', // transaction type - all donations are a sale
		'tender' => 'C', // credit card - all transactions in this case are credit cards
		'verbosity' => 'MEDIUM', // level of detail in Payflow response
		'user_ip' => ( $wgPayflowGatewayTest ) ? '12.12.12.12' : wfGetIP(), // current user's IP address
		'order_id' => payflowGetOrderId(),
		'i_order_id' => payflowGetInternalOrderId(),
	);

	return $payflow_data;
}

/**
 * Fetch and return the 'order_id' for a transaction
 * 
 * Since transactions to PayPal are initially matched internally on their end
 * with the 'order_id' field, but we don't actually care what the order id is,
 * we generate a sufficiently random number to avoid duplication. 
 * 
 * We go ahead and always generate a random order id becuse if PayPal detects
 * the same order_id more than once, it considers the request a duplicate, even
 * if the data is completely different.
 * 
 * @return int
 */
function payflowGetOrderId() {
	return generateOrderId();
}

/**
 * Generate an internal order id
 * 
 * This is only used internally for tracking a user's 'session' with the credit
 * card form.  I mean 'session' in the sense of the moment a credit card page
 * is loaded for the first time (nothing posted to it - a discrete donation 
 * session) as opposed to the $_SESSION - as the $_SESSION id could potentially
 * not change between contribution attempts.
 */
function payflowGetInternalOrderId() {
	global $wgRequest;
	
	// is an order_id already set?
	$i_order_id = $wgRequest->getText( 'i_order_id', 0 );
	
	// if the form was not just posted OR there's no order_id set, generate one.
	if ( !$wgRequest->wasPosted() || !$i_order_id ) {
		$i_order_id = generateOrderId();
	}

	return $i_order_id;
}

/**
 * Generate an order id
 */
function generateOrderId() {
	return (double) microtime() * 1000000 . mt_rand();
}