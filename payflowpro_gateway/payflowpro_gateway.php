<?php

# Alert the user that this is not a valid entry point to MediaWiki if they try to access the special pages file directly.
if (!defined('MEDIAWIKI')) {
        echo <<<EOT
To install my extension, put the following line in LocalSettings.php:
require_once( "\$IP/extensions/payflowpro_gateway/payflowpro_gateway.php" );
EOT;
        exit( 1 );
}
 
$wgExtensionCredits['specialpage'][] = array(
	'name' => 'PayflowPro Gateway',
	'author' => 'Four Kitchens',
	'url' => 'http://www.mediawiki.org/wiki/Extension:PayflowProGateway',
	'description' => 'Integrates Paypal Payflow Pro credit card processing',
	'descriptionmsg' => 'payflowpro_gateway-desc',
	'version' => '1.0.0',
);
 
$dir = dirname(__FILE__) . '/';


$wgAutoloadClasses['PayflowProGateway'] = $dir . 'payflowpro_gateway.body.php'; # Tell MediaWiki to load the extension body.
$wgExtensionMessagesFiles['PayflowProGateway'] = $dir . 'payflowpro_gateway.i18n.php';
$wgExtensionAliasesFiles['PayflowProGateway'] = $dir . 'payflowpro_gateway.alias.php';
$wgSpecialPages['PayflowProGateway'] = 'PayflowProGateway'; # Let MediaWiki know about your new special page.


/** 
* Hooks required to interface with the donation extension (include <donate> on page)
*
* gwValue supplies the value of the form option, the name that appears on the form
* and the currencies supported by the gateway in the $values array
*/
$wgHooks['gwValue'][] = 'pfpGatewayValue';
$wgHooks['gwPage'][] = 'pfpGatewayPage';

/*
* Hook to register form value and display name of this gateway
* also supplies currencies supported by this gateway
*/
function pfpGatewayValue(&$values) {
  
  $values['payflow'] = array(
    'gateway' => "payflow",
    'display_name' => "Credit Card",
    'form_value' => "payflow",
    'currencies' => array(
      'GBP' => "GBP: British Pound",
      'EUR' => "EUR: Euro",
      'USD' => "USD: U.S. Dollar",
      'AUD' => "AUD: Australian Dollar",
      'CAD' => "CAD: Canadian Dollar",
      'CHF' => "CHF: Swiss Franc",
      'CZK' => "CZK: Czech Koruna",
      'DKK' => "DKK: Danish Krone",
      'HKD' => "HKD: Hong Kong Dollar",
      'HUF' => "HUF: Hungarian Forint",
      'JPY' => "JPY: Japanese Yen",
      'NZD' => "NZD: New Zealand Dollar",
      'NOK' => "NOK: Norwegian Krone",
      'PLN' => "PLN: Polish Zloty",
      'SGD' => "SGD: Singapore Dollar",
      'SEK' => "SEK: Swedish Krona",
      'ILS' => "ILS: Isreali Shekel",
      ),
    );

  return true;
}

/*
*  Hook to supply the page address of the payment gateway
*
* The user will redirected here with supplied data with input data appended (GET).
* For example, if $url[$key] = index.php?title=Special:PayflowPro
* the result might look like this: http://www.yourdomain.com/index.php?title=Special:PayflowPro&amount=75.00&currency_code=USD&payment_method=payflow
*/
function pfpGatewayPage(&$url) {
  global $wgScript;
  
  $url['payflow'] = $wgScript. "?title=Special:PayflowProGateway";
  
  return true;
}