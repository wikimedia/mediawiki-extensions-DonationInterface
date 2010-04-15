<?php
/**
 * Internationalization file for the Donation Interface - PayflowPro - extension
 *
 * @file
 * @ingroup Extensions
 */

$messages = array();

/** English */
$messages['en'] = array(
	'payflowprogateway' => 'Support Wikimedia',
	'payflowpro_gateway-desc' => 'PayPal Payflow Pro credit card processing',
	'payflowpro_gateway-accessible' => 'This page is only accessible from the donation page.',
	'payflowpro_gateway-form-message' => 'Contribute with your credit card.
There are <a href="http://wikimediafoundation.org/wiki/Ways_to_Give/en">other ways to give, including PayPal, check, or mail</a>.',
	'payflowpro_gateway-form-message-2' => 'To change amount or currency, return to <a href="/index.php?title=Donate">the donation page</a>',
	'payflowpro_gateway-donor-legend' => 'Donor information',
	'payflowpro_gateway-card-legend' => 'Credit card information',
	'payflowpro_gateway-amount-legend' => 'Donation amount:',
	'payflowpro_gateway-cvv-link' => 'Example',
	'payflowpro_gateway-donor-legend' => 'Donor information',
	'payflowpro_gateway-donor-amount' => 'Amount:',
	'payflowpro_gateway-donor-currency-label' => 'Currency:',
	'payflowpro_gateway-donor-email' => 'E-mail address:',
	'payflowpro_gateway-donor-fname' => 'First name:',
	'payflowpro_gateway-donor-mname' => 'Middle name:',
	'payflowpro_gateway-donor-lname' => 'Last name:',
	'payflowpro_gateway-donor-name' => 'Name:',
	'payflowpro_gateway-donor-street' => 'Street:',
	'payflowpro_gateway-donor-city' => 'City:',
	'payflowpro_gateway-donor-state' => 'State:',
	'payflowpro_gateway-donor-postal' => 'Postal code:',
	'payflowpro_gateway-donor-country' => 'Country/Region:',
	'payflowpro_gateway-donor-address'=> 'Address:',
	'payflowpro_gateway-donor-card' => 'Credit card:',
	'payflowpro_gateway-donor-card-num' => 'Card number:',
	'payflowpro_gateway-donor-expiration' => 'Expiration date:',
	'payflowpro_gateway-donor-security' => 'Security code:',
	'payflowpro_gateway-donor-submit' => 'Donate',
	'payflowpro_gateway-donor-currency-msg' => 'This donation is being made in $1',
	'payflow_gateway-card-name-amex' => 'American Express',
	'payflow_gateway-card-name-visa' => 'Visa',
	'payflow_gateway-card-name-mc' => 'Mastercard',
	'payflow_gateway-card-name-discover' => 'Discover',
	'payflowpro_gateway-error-msg' => 'Please enter your $1',
	'payflowpro_gateway-error-msg-js' => 'Please enter your',
	'payflowpro_gateway-error-msg-invalid-amount' => '**Please enter a valid amount**',
	'payflowpro_gateway-error-msg-email' => '**Please enter a valid e-mail address**',
	'payflowpro_gateway-error-msg-amex' => '**Please enter a correct card number for American Express.**',
	'payflowpro_gateway-error-msg-mc' => '**Please enter a correct card number for MasterCard.**',
	'payflowpro_gateway-error-msg-visa' => '**Please enter a correct card number for Visa.**',
	'payflowpro_gateway-error-msg-discover' => '**Please enter a correct card number for Discover.**',
	'payflowpro_gateway-error-msg-amount' => 'donation amount',
	'payflowpro_gateway-error-msg-emailAdd' => 'e-mail address',
	'payflowpro_gateway-error-msg-fname' => 'first name',
	'payflowpro_gateway-error-msg-lname' => 'last name',
	'payflowpro_gateway-error-msg-street' => 'street address',
	'payflowpro_gateway-error-msg-city' => 'city',
	'payflowpro_gateway-error-msg-state' => 'state',
	'payflowpro_gateway-error-msg-zip' => 'postal code',
	'payflowpro_gateway-error-msg-card_num' => 'credit card number',
	'payflowpro_gateway-error-msg-expiration' => "card's expiration date",
	'payflowpro_gateway-error-msg-cvv' => 'CVV from the back of your card',
	'payflowpro_gateway-response-0' => 'Your transaction has been approved.
Thank you for your donation!',
	'payflowpro_gateway-response-126' => 'Your transaction is pending approval.',
	'payflowpro_gateway-response-126-2' => 'Some of the information you provided did not match your credit card profile, or you made a very large gift. For your own security, your donation is currently under review, and we will notify you through the provided e-mail address if we cannot finalize your donation. Please e-mail <a href="mailto:donate@wikimedia.org">donate@wikimedia.org</a> if you have any questions. Thank you!',
	'payflowpro_gateway-response-12' => 'Please contact your credit card company for further information.',
	'payflowpro_gateway-response-13' => 'Your transaction requires voice authorization.
Please contact us to continue your transaction.', // This will not apply to Wikimedia accounts
	'payflowpro_gateway-response-114' => 'Please contact your credit card company for further information.',
	'payflowpro_gateway-response-23' => 'Your credit card number or expiration date is incorrect.',
	'payflowpro_gateway-response-4' => 'Invalid amount.',
	'payflowpro_gateway-response-24' => 'Your credit card number or expiration date is incorrect.',
	'payflowpro_gateway-response-112' => 'Your address or CVV number (security code) is incorrect.',
	'payflowpro_gateway-response-125' => 'Your transaction has been declined by Fraud Prevention Services.',
	'payflowpro_gateway-response-125-2' => 'Your credit card could not be validated. Please verify that all provided information matches your credit card profile, or try a different card. You can also use one of our <a href="http://wikimediafoundation.org/wiki/Ways_to_Give/en">other ways to give</a> or contact us at <a href="mailto:donate@wikimedia.org">donate@wikimedia.org</a>. Thank you for your support.',
	'payflowpro_gateway-response-default' => 'There was an error processing your transaction.
Please try again later.',
	'php-response-declined' => 'Your transaction has been declined.',
	'payflowpro_gateway-thankyou' => 'Thank you for your donation!',
	'payflowpro_gateway-post-transaction' => 'Transaction details',
	'payflowpro_gateway-submit-button' => 'Donate',
	'payflowpro_gateway-cvv-explain' => '<h4>What is CVV?</h4>
<p>Cardholder Verification Value (CVV): These three or four digit numbers help ensure that the physical card is in the cardholder’s possession. This helps to prevent unauthorized or fraudulent use.</p>
<h4>Visa, Mastercard</h4>
<p>The 3-digit code is located on the back of your card, inside the signature area.
Typically the signature panel will have a series of numbers, but only the last three digits make up the CVV code.</p>
<h4>American Express</h4>
<p>The code is <i>always</i> located <i>above</i> the embossed (raised) account number on the face of the card.
In some instances, the code is located on the left side of the card, but is always above the account number.</p><br />',
	'payflowpro_gateway-question-comment' => 'Wikipedia is a project of the Wikimedia Foundation. Questions or comments? Contact the Wikimedia Foundation: <a href="mailto:donate@wikimedia.org">donate@wikimedia.org</a>',
	'payflowpro_gateway-donate-click' => 'After clicking "{{int:payflowpro_gateway-donor-submit}}", your credit card information will be processed.',
	'payflowpro_gateway-credit-storage-processing' => 'We do not store your credit card information, and your personal data is subject to our <a href="http://wikimediafoundation.org/wiki/Donor_Privacy_Policy">privacy policy</a>.',
	'donate_interface-GBP' => 'GBP: British Pound',
	'donate_interface-EUR' => 'EUR: Euro',
	'donate_interface-USD' => 'USD: U.S. Dollar',
	'donate_interface-AUD' => 'AUD: Australian Dollar',
	'donate_interface-CAD' => 'CAD: Canadian Dollar',
	'donate_interface-JPY' => 'JPY: Japanese Yen',
);

