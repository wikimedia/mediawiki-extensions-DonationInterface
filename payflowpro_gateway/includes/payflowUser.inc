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
	);

	return $payflow_data;
}

/**
 * Return a mostly random number for an 'order id'
 * @return int
 */
function payflowGetOrderId() {
	return (double) microtime() * 1000000 . mt_rand(); // Generate random order number
}