<?php
/**
 * Internationalization file for the Donation Interface - GlobalCollect - extension
 *
 * @file
 * @ingroup Extensions
 */

$messages = array();

/** English */
$messages['en'] = array(
	'globalcollectgateway' => 'Make your donation now',
	'globalcollect_gateway-desc' => 'GlobalCollect payment processing',
	'globalcollect_gateway-response-9130' => 'Invalid country.',
	'globalcollect_gateway-response-9140' => 'Invalid currency.',
	'globalcollect_gateway-response-9150' => 'Invalid language.',
	'globalcollect_gateway-response-400530' => 'Invalid payment method.',
	'globalcollect_gateway-response-default' => 'There was an error processing your transaction.
Please try again later.',
);

/** Message documentation (Message documentation)
 * @author Kaldari
 */
$messages['qqq'] = array(
	'globalcollectgateway' => '{{Identical|Support Wikimedia}}',
	'globalcollect_gateway-desc' => '{{desc}}',
	'globalcollect_gateway-response-9130' => 'Error message for invalid country.',
	'globalcollect_gateway-response-9140' => 'Error message for invalid currency.',
	'globalcollect_gateway-response-9150' => 'Error message for invalid language.',
	'globalcollect_gateway-response-400530' => 'Error message for invalid payment method, for example, not a valid credit card type.',
	'globalcollect_gateway-response-default' => 'Error message if something went wrong on our side.',
);
