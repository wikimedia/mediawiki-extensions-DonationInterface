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
	'globalcollect_gateway-response-430306' => 'Your credit card has expired. Please try a different card or one of our other payment methods.',
	'globalcollect_gateway-response-430330' => 'Invalid card number.',
	'globalcollect_gateway-response-430421' => 'Your credit card could not be validated. Please verify that all information matches your credit card profile, or try a different card.', // suspected fraud
	'globalcollect_gateway-response-430360' => 'The transaction could not be authorized. Please try a different card or one of our other payment methods.', // low funds
	'globalcollect_gateway-response-430285' => 'The transaction could not be authorized. Please try a different card or one of our other payment methods.', // do not honor
	'globalcollect_gateway-response-21000150' => 'Invalid bank account number.',
	'globalcollect_gateway-response-21000155' => 'Invalid bank code.',
	'globalcollect_gateway-response-21000160' => 'Invalid giro account number.',
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
	'globalcollect_gateway-response-430306' => 'Error message for expired credit card.',
	'globalcollect_gateway-response-430330' => 'Error message for invalid card number.',
	'globalcollect_gateway-response-430421' => 'Error message for declined credit card transaction. This error may be due to incorrect information being entered into the form.',
	'globalcollect_gateway-response-430360' => 'Error message for declined credit card transaction due to insuffient funds.',
	'globalcollect_gateway-response-430285' => 'Error message for declined credit card transaction due to "do not honor" message from payment provider.',
	'globalcollect_gateway-response-21000150' => 'Error message for invalid bank account number.',
	'globalcollect_gateway-response-21000155' => 'Error message for invalid bank code.',
	'globalcollect_gateway-response-21000160' => 'Error message for invalid giro account number.',
	'globalcollect_gateway-response-default' => 'Error message if something went wrong on our side.',
);

/** German (Deutsch)
 * @author Kghbln
 */
$messages['de'] = array(
	'globalcollectgateway' => 'Jetzt spenden',
	'globalcollect_gateway-desc' => 'Ermöglicht die Zahlungsabwicklung durch GlobalCollect',
	'globalcollect_gateway-response-9130' => 'Ungültiger Staat.',
	'globalcollect_gateway-response-9140' => 'Ungültige Währung.',
	'globalcollect_gateway-response-9150' => 'Ungültige Sprache.',
	'globalcollect_gateway-response-400530' => 'Ungültige Zahlungsmethode.',
	'globalcollect_gateway-response-default' => 'Während des Ausführens der Transaktion ist ein Verarbeitungsfehler aufgetreten.
Bitte versuche es später noch einmal.',
);

/** German (formal address) (‪Deutsch (Sie-Form)‬)
 * @author Kghbln
 */
$messages['de-formal'] = array(
	'globalcollect_gateway-response-default' => 'Während des Ausführens der Transaktion ist ein Verarbeitungsfehler aufgetreten.
Bitte versuchen Sie es später noch einmal.',
);

