<?php

# Alert the user that this is not a valid entry point to MediaWiki if they try to access the special pages file directly.
if ( !defined( 'MEDIAWIKI' ) ) {
        echo <<<EOT
To install PayflowPro Gateway extension, put the following line in LocalSettings.php:
require_once( "\$IP/extensions/payflowpro_gateway/payflowpro_gateway.php" );
EOT;
        exit( 1 );
}

// Extension credits that will show up on Special:Version
$wgExtensionCredits['specialpage'][] = array(
        'name' => 'PayflowPro Gateway',
        'author' => 'Four Kitchens',
        'version' => '1.0.0',
        'descriptionmsg' => 'payflowpro_gateway-desc',
        'url' => 'http://www.mediawiki.org/wiki/Extension:PayflowProGateway',
);

// Set up the new special page
$dir = dirname( __FILE__ ) . '/';
$wgAutoloadClasses['PayflowProGateway'] = $dir . 'payflowpro_gateway.body.php';
$wgAutoloadClasses[ 'PayflowProGateway_Form' ] = $dir . 'forms/Form.php';
$wgAutoloadClasses[ 'PayflowProGateway_Form_OneStepTwoColumn' ] = $dir . 'forms/OneStepTwoColumn.php';
$wgAutoloadClasses[ 'PayflowProGateway_Form_TwoStepTwoColumn' ] = $dir . 'forms/TwoStepTwoColumn.php';
$wgAutoloadClasses[ 'PayflowProGateway_Form_TwoColumnPayPal' ] = $dir . 'forms/TwoColumnPayPal.php';
$wgAutoloadClasses[ 'PayflowProGateway_Form_TwoColumnLetter' ] = $dir . 'forms/TwoColumnLetter.php';
$wgAutoloadClasses[ 'PayflowProGateway_Form_TwoColumnLetter2' ] = $dir . 'forms/TwoColumnLetter2.php';
$wgAutoloadClasses[ 'PayflowProGateway_Form_TwoColumnLetter3' ] = $dir . 'forms/TwoColumnLetter3.php';
$wgAutoloadClasses[ 'PayflowProGateway_Form_TwoColumnLetter4' ] = $dir . 'forms/TwoColumnLetter4.php';
$wgAutoloadClasses[ 'PayflowProGateway_Form_TwoColumnLetter5' ] = $dir . 'forms/TwoColumnLetter5.php';
$wgAutoloadClasses[ 'PayflowProGateway_Form_TwoColumnLetter6' ] = $dir . 'forms/TwoColumnLetter6.php';
$wgAutoloadClasses[ 'PayflowProGateway_Form_TwoColumnLetter7' ] = $dir . 'forms/TwoColumnLetter7.php';
$wgAutoloadClasses[ 'PayflowProGateway_Form_TwoStepTwoColumnLetter' ] = $dir . 'forms/TwoStepTwoColumnLetter.php';
$wgAutoloadClasses[ 'PayflowProGateway_Form_TwoStepTwoColumnLetterCA' ] = $dir . 'forms/TwoStepTwoColumnLetterCA.php';
$wgAutoloadClasses[ 'PayflowProGateway_Form_TwoStepTwoColumnLetter2' ] = $dir . 'forms/TwoStepTwoColumnLetter2.php';
$wgAutoloadClasses[ 'PayflowProGateway_Form_TwoStepTwoColumnLetter3' ] = $dir . 'forms/TwoStepTwoColumnLetter3.php';
$wgAutoloadClasses[ 'PayflowProGateway_Form_TwoStepTwoColumnPremium' ] = $dir . 'forms/TwoStepTwoColumnPremium.php';
$wgAutoloadClasses[ 'PayflowProGateway_Form_TwoStepTwoColumnPremiumUS' ] = $dir . 'forms/TwoStepTwoColumnPremiumUS.php';
$wgAutoloadClasses[ 'PayflowProGateway_Form_RapidHtml' ] = $dir . 'forms/RapidHtml.php';
$wgAutoloadClasses[ 'PayflowProGateway_Form_SingleColumn' ] = $dir . 'forms/SingleColumn.php';
$wgExtensionMessagesFiles['PayflowProGateway'] = $dir . 'payflowpro_gateway.i18n.php';
$wgExtensionMessagesFiles['PayflowProGatewayCountries'] = $dir . 'payflowpro_gateway.countries.i18n.php';
$wgExtensionMessagesFiles['PayflowProGatewayUSStates'] = $dir . 'payflowpro_gateway.us-states.i18n.php';
$wgExtensionAliasesFiles['PayflowProGateway'] = $dir . 'payflowpro_gateway.alias.php';
$wgSpecialPages['PayflowProGateway'] = 'PayflowProGateway';
$wgSpecialPages['HideBanners'] = 'SpecialHideBanners';
$wgAutoloadClasses['SpecialHideBanners'] = $dir . 'SpecialHideBanners.php';
$wgAjaxExportList[] = "fnPayflowProofofWork";


// set defaults, these should be assigned in LocalSettings.php
$wgPayflowProURL = 'https://payflowpro.paypal.com';
$wgPayflowProTestingURL = 'https://pilot-payflowpro.paypal.com'; // Payflow testing URL

$wgPayFlowProGatewayCSSVersion = 1;

$wgPayflowProPartnerID = ''; // PayPal or original authorized reseller
$wgPayflowProVendorID = ''; // paypal merchant login ID
$wgPayflowProUserID = ''; // if one or more users are set up, authorized user ID, else same as VENDOR
$wgPayflowProPassword = ''; // merchant login password

// a boolean to determine if we're in testing mode
$wgPayflowGatewayTest = FALSE;

// timeout in seconds for communicating with paypal
$wgPayflowProTimeout = 5;

/**
 * The default form to use
 */
$wgPayflowGatewayDefaultForm = 'TwoStepTwoColumn';

/**
 * A string or array of strings for making tokens more secure
 *
 * Please set this!  If you do not, tokens are easy to get around, which can
 * potentially leave you and your users vulnerable to CSRF or other forms of
 * attack.
 */
$wgPayflowGatewaySalt = $wgSecretKey;

$wgPayflowGatewayDBserver = $wgDBserver;
$wgPayflowGatewayDBname = $wgDBname;
$wgPayflowGatewayDBuser = $wgDBuser;
$wgPayflowGatewayDBpassword = $wgDBpassword;

/**
 * A string that can contain wikitext to display at the head of the credit card form
 *
 * This string gets run like so: $wg->addHtml( $wg->Parse( $wgpayflowGatewayHeader ))
 * You can use '@language' as a placeholder token to extract the user's language.
 *
 */
$wgPayflowGatewayHeader = NULL;

/**
 * A string containing full URL for Javascript-disabled credit card form redirect
 */
$wgPayflowGatewayNoScriptRedirect = null;

/**
 * Proxy settings
 *
 * If you need to use an HTTP proxy for outgoing traffic,
 * set wgPayflowGatweayUseHTTPProxy=TRUE and set $wgPayflowGatewayHTTPProxy
 * to the proxy desination.
 *  eg:
 *  $wgPayflowGatewayUseHTTPProxy=TRUE;
 *  $wgPayflowGatewayHTTPProxy='192.168.1.1:3128'
 */
$wgPayflowGatewayUseHTTPProxy = FALSE;
$wgPayflowGatewayHTTPProxy = '';

/**
 * The URL to redirect a transaction to PayPal
 */
$wgPayflowGatewayPaypalURL = '';

/**
 * Set the max-age value for Squid
 *
 * If you have Squid enabled for caching, use this variable to configure
 * the s-max-age for cached requests.
 * @var int Time in seconds
 */
$wgPayflowSMaxAge = 6000;

/**
 * Directory for HTML forms (used by RapidHtml form class)
 * @var string
 */
$wgPayflowHtmlFormDir = dirname( __FILE__ ) . "/forms/html";

/**
 * An array of allowed HTML forms.
 * 
 * Be sure to use full paths.  If your HTML form is not listed here, it will
 * /never/ be loaded by the rapid html form loader!
 * @var string
 */
$wgPayflowAllowedHtmlForms = array(	$wgPayflowHtmlFormDir . "/demo.html" );

/**
 * Configure PayflowproGateway to use syslog for log messages rather than wfDebugLog
 * 
 * @var bool
 */
$wgPayflowGatewayUseSyslog = false;

/**
 * Configure price cieling and floor for valid contribution amount.  Values 
 * should be in USD.
 */
$wgPayflowPriceFloor = '1.00';
$wgPayflowPriceCieling = '10000.00';

/**
 * Hooks required to interface with the donation extension (include <donate> on page)
 *
 * gwValue supplies the value of the form option, the name that appears on the form
 * and the currencies supported by the gateway in the $values array
 */
$wgHooks['DonationInterface_Value'][] = 'pfpGatewayValue';
$wgHooks['DonationInterface_Page'][] = 'pfpGatewayPage';

// enable the API
$wgAPIModules[ 'pfp' ] = 'ApiPayflowProGateway';
$wgAutoloadClasses[ 'ApiPayflowProGateway' ] = $dir . 'api_payflowpro_gateway.php';

function payflowGatewayConnection() {
	global $wgPayflowGatewayDBserver, $wgPayflowGatewayDBname;
	global $wgPayflowGatewayDBuser, $wgPayflowGatewayDBpassword;

	static $db;

	if ( !$db ) {
			$db = new DatabaseMysql(
					$wgPayflowGatewayDBserver,
					$wgPayflowGatewayDBuser,
					$wgPayflowGatewayDBpassword,
					$wgPayflowGatewayDBname );
					$db->query( "SET names utf8" );
	}

	return $db;
}

/**
 * Hook to register form value and display name of this gateway
 * also supplies currencies supported by this gateway
 */
function pfpGatewayValue( &$values ) {
	$values['payflow'] = array(
			'gateway' => 'payflow',
			'display_name' => 'Credit Card',
			'form_value' => 'payflow',
			'currencies' => array(
					'GBP' => 'GBP: British Pound',
					'EUR' => 'EUR: Euro',
					'USD' => 'USD: U.S. Dollar',
					'AUD' => 'AUD: Australian Dollar',
					'CAD' => 'CAD: Canadian Dollar',
					'JPY' => 'JPY: Japanese Yen',
			),
	);

	return true;
}

/**
 *  Hook to supply the page address of the payment gateway
 *
 * The user will redirected here with supplied data with input data appended (GET).
 * For example, if $url[$key] = index.php?title=Special:PayflowPro
 * the result might look like this: http://www.yourdomain.com/index.php?title=Special:PayflowPro&amount=75.00&currency_code=USD&payment_method=payflow
 */
function pfpGatewayPage( &$url ) {
	global $wgScript;

	$url['payflow'] = $wgScript . "?title=Special:PayflowProGateway";
	return true;
}
