<?php

/**
 * Donation Interface
 *
 *  To install the DontaionInterface extension, put the following line in LocalSettings.php:
 *	require_once( "\$IP/extensions/DonationInterface/donationinterface.php" );
 * 
 */


# Alert the user that this is not a valid entry point to MediaWiki if they try to access the special pages file directly.
if ( !defined( 'MEDIAWIKI' ) ) {
	echo <<<EOT
To install the DontaionInterface extension, put the following line in LocalSettings.php:
require_once( "\$IP/extensions/DonationInterface/donationinterface.php" );
EOT;
	exit( 1 );
}

// Extension credits that will show up on Special:Version
$wgExtensionCredits['specialpage'][] = array(
	'name' => 'Donation Interface',
	'author' => 'Katie Horn',
	'version' => '1.0.0',
	'descriptionmsg' => 'donationinterface-desc',
	'url' => 'http://www.mediawiki.org/wiki/Extension:DonationInterface',
);

$donationinterface_dir = dirname( __FILE__ ) . '/';

/**
 * Figure out what we've got enabled. 
 */

$optionalParts = array( //define as fail closed. This variable will be unset before we leave this file. 
	'Extras' => false, //this one gets set in the next loop, so don't bother. 
	'Stomp' => false,
	'CustomFilters' => false, //this is definitely an Extra
	'ConversionLog' => false, //this is definitely an Extra
	'Minfraud' => false, //this is definitely an Extra
	'Minfraud_as_filter' => false, //extra
	'Recaptcha' => false, //extra
	'PayflowPro' => false,
	'GlobalCollect' => false,
	
);

foreach ($optionalParts as $subextension => $enabled){
	$globalname = 'wgDonationInterfaceEnable' . $subextension;
	global $$globalname;
	if ( isset( $$globalname ) && $$globalname === true ) {
		$optionalParts[$subextension] = true;
		if ( $subextension === 'CustomFilters' ||
			$subextension === 'ConversionLog' ||
			$subextension === 'Minfraud' ||
			$subextension === 'Recaptcha' ) {
			
			$optionalParts['Extras'] = true;
		}
	}
}


/**
 * CLASSES
 */
$wgAutoloadClasses['DonationData'] = $donationinterface_dir . 'gateway_common/DonationData.php';
$wgAutoloadClasses['GatewayAdapter'] = $donationinterface_dir . 'gateway_common/gateway.adapter.php';
$wgAutoloadClasses['GatewayForm'] = $donationinterface_dir . 'gateway_common/GatewayForm.php';

//load all possible form classes
$wgAutoloadClasses['Gateway_Form'] = $donationinterface_dir . 'gateway_forms/Form.php';
$wgAutoloadClasses['Gateway_Form_OneStepTwoColumn'] = $donationinterface_dir . 'gateway_forms/OneStepTwoColumn.php';
$wgAutoloadClasses['Gateway_Form_TwoStepAmount'] = $donationinterface_dir . 'gateway_forms/TwoStepAmount.php';
$wgAutoloadClasses['Gateway_Form_TwoStepTwoColumn'] = $donationinterface_dir . 'gateway_forms/TwoStepTwoColumn.php';
$wgAutoloadClasses['Gateway_Form_TwoColumnPayPal'] = $donationinterface_dir . 'gateway_forms/TwoColumnPayPal.php';
$wgAutoloadClasses['Gateway_Form_TwoColumnLetter'] = $donationinterface_dir . 'gateway_forms/TwoColumnLetter.php';
$wgAutoloadClasses['Gateway_Form_TwoColumnLetter2'] = $donationinterface_dir . 'gateway_forms/TwoColumnLetter2.php';
$wgAutoloadClasses['Gateway_Form_TwoColumnLetter3'] = $donationinterface_dir . 'gateway_forms/TwoColumnLetter3.php';
$wgAutoloadClasses['Gateway_Form_TwoColumnLetter4'] = $donationinterface_dir . 'gateway_forms/TwoColumnLetter4.php';
$wgAutoloadClasses['Gateway_Form_TwoColumnLetter5'] = $donationinterface_dir . 'gateway_forms/TwoColumnLetter5.php';
$wgAutoloadClasses['Gateway_Form_TwoColumnLetter6'] = $donationinterface_dir . 'gateway_forms/TwoColumnLetter6.php';
$wgAutoloadClasses['Gateway_Form_TwoColumnLetter7'] = $donationinterface_dir . 'gateway_forms/TwoColumnLetter7.php';
$wgAutoloadClasses['Gateway_Form_TwoStepTwoColumnLetter'] = $donationinterface_dir . 'gateway_forms/TwoStepTwoColumnLetter.php';
$wgAutoloadClasses['Gateway_Form_TwoStepTwoColumnLetterCA'] = $donationinterface_dir . 'gateway_forms/TwoStepTwoColumnLetterCA.php';
$wgAutoloadClasses['Gateway_Form_TwoStepTwoColumnLetter2'] = $donationinterface_dir . 'gateway_forms/TwoStepTwoColumnLetter2.php';
$wgAutoloadClasses['Gateway_Form_TwoStepTwoColumnLetter3'] = $donationinterface_dir . 'gateway_forms/TwoStepTwoColumnLetter3.php';
$wgAutoloadClasses['Gateway_Form_TwoStepTwoColumnPremium'] = $donationinterface_dir . 'gateway_forms/TwoStepTwoColumnPremium.php';
$wgAutoloadClasses['Gateway_Form_TwoStepTwoColumnPremiumUS'] = $donationinterface_dir . 'gateway_forms/TwoStepTwoColumnPremiumUS.php';
$wgAutoloadClasses['Gateway_Form_RapidHtml'] = $donationinterface_dir . 'gateway_forms/RapidHtml.php';
$wgAutoloadClasses['Gateway_Form_SingleColumn'] = $donationinterface_dir . 'gateway_forms/SingleColumn.php';


//GlobalCollect gateway classes
if ( $optionalParts['GlobalCollect'] === true ){
	$wgAutoloadClasses['GlobalCollectGateway'] = $donationinterface_dir . 'globalcollect_gateway/globalcollect_gateway.body.php';
	$wgAutoloadClasses['GlobalCollectGatewayResult'] = $donationinterface_dir . 'globalcollect_gateway/globalcollect_resultswitcher.body.php';
	$wgAutoloadClasses['GlobalCollectAdapter'] = $donationinterface_dir . 'globalcollect_gateway/globalcollect.adapter.php';
}
//PayflowPro gateway classes
if ( $optionalParts['PayflowPro'] === true ){
	$wgAutoloadClasses['PayflowProGateway'] = $donationinterface_dir . 'payflowpro_gateway/payflowpro_gateway.body.php';
	$wgAutoloadClasses['PayflowProAdapter'] = $donationinterface_dir . 'payflowpro_gateway/payflowpro.adapter.php';
}

//Stomp classes
if ($optionalParts['Stomp'] === true){
	$wgAutoloadClasses['activemq_stomp'] = $donationinterface_dir . 'activemq_stomp/activemq_stomp.php'; # Tell MediaWiki to load the extension body.
}

//Extras classes - required for ANY optional class that is considered an "extra". 
if ($optionalParts['Extras'] === true){
	$wgAutoloadClasses['Gateway_Extras'] = $donationinterface_dir . "extras/extras.body.php";
}

//Custom Filters classes
if ($optionalParts['CustomFilters'] === true){
	$wgAutoloadClasses['Gateway_Extras_CustomFilters'] = $donationinterface_dir . "extras/custom_filters/custom_filters.body.php";
}

//Conversion Log classes
if ($optionalParts['ConversionLog'] === true){
	$wgAutoloadClasses['Gateway_Extras_ConversionLog'] = $donationinterface_dir . "extras/conversion_log/conversion_log.body.php";
}

//Minfraud classes
if ( $optionalParts['Minfraud'] === true || $optionalParts['Minfraud_as_filter'] === true ){
	$wgAutoloadClasses['Gateway_Extras_MinFraud'] = $donationinterface_dir . "extras/minfraud/minfraud.body.php";
}

//Minfraud as Filter classes
if ( $optionalParts['Minfraud_as_filter'] === true ){
	$wgAutoloadClasses['Gateway_Extras_CustomFilters_MinFraud'] = $donationinterface_dir . "extras/custom_filters/filters/minfraud/minfraud.body.php";
}

//Recaptcha classes
if ( $optionalParts['Recaptcha'] === true ){
	$wgAutoloadClasses['Gateway_Extras_ReCaptcha'] = $donationinterface_dir . "extras/recaptcha/recaptcha.body.php";
}


/**
 * GLOBALS
 */

/**
 * Global form dir and RapidHTML whitelist
 */
$wgDonationInterfaceHtmlFormDir = dirname( __FILE__ ) . "/gateway_forms/rapidhtml/html";
//ffname is the $key from now on. 
$wgDonationInterfaceAllowedHtmlForms = array(
	'demo' => $wgDonationInterfaceHtmlFormDir . "/demo.html",
	'globalcollect_test' => $wgDonationInterfaceHtmlFormDir . "/globalcollect_test.html",
);

$wgDonationInterfaceTest = false;

/**
 * The URL to redirect a transaction to PayPal
 * This should probably point to ContributionTracking. 
 */
$wgDonationInterfacePaypalURL = '';
$wgDonationInterfaceRetrySeconds = 5;

//all of the following variables make sense to override directly, 
//or change "DonationInterface" to the gateway's id to override just for that gateway. 
//for instance: To override $wgDonationInterfaceUseSyslog just for GlobalCollect, add
// $wgGolbalCollectGatewayUseSyslog = true
// to LocalSettings. 
//   

$wgDonationInterfaceDisplayDebug = false;
$wgDonationInterfaceUseSyslog = false;
$wgDonationInterfaceSaveCommStats = false;

$wgDonationInterfaceCSSVersion = 1;
$wgDonationInterfaceTimeout = 5;
$wgDonationInterfaceDefaultForm = 'TwoStepTwoColumn';

/**
 * A string or array of strings for making tokens more secure
 *
 * Please set this!  If you do not, tokens are easy to get around, which can
 * potentially leave you and your users vulnerable to CSRF or other forms of
 * attack.
 */
$wgDonationInterfaceSalt = $wgSecretKey;

/**
 * A string that can contain wikitext to display at the head of the credit card form
 *
 * This string gets run like so: $wg->addHtml( $wg->Parse( $wgpayflowGatewayHeader ))
 * You can use '@language' as a placeholder token to extract the user's language.
 *
 */
$wgDonationInterfaceHeader = NULL;

/**
 * A string containing full URL for Javascript-disabled credit card form redirect
 */
$wgDonationInterfaceNoScriptRedirect = null;

/**
 * Proxy settings
 *
 * If you need to use an HTTP proxy for outgoing traffic,
 * set wgPayflowGatweayUseHTTPProxy=TRUE and set $wgPayflowProGatewayHTTPProxy
 * to the proxy desination.
 *  eg:
 *  $wgPayflowProGatewayUseHTTPProxy=TRUE;
 *  $wgPayflowProGatewayHTTPProxy='192.168.1.1:3128'
 */
$wgDonationInterfaceUseHTTPProxy = FALSE;
$wgDonationInterfaceHTTPProxy = '';

/**
 * Set the max-age value for Squid
 *
 * If you have Squid enabled for caching, use this variable to configure
 * the s-max-age for cached requests.
 * @var int Time in seconds
 */
$wgDonationInterfaceSMaxAge = 6000;

/**
 * Configure price cieling and floor for valid contribution amount.  Values 
 * should be in USD.
 */
$wgDonationInterfacePriceFloor = '1.00';
$wgDonationInterfacePriceCeiling = '10000.00';

/**
 * Default Thank You and Fail pages for all of donationinterface - language will be calc'd and appended at runtime. 
 */
//$wgDonationInterfaceThankYouPage = 'https://wikimediafoundation.org/wiki/Thank_You';
$wgDonationInterfaceThankYouPage = 'Donate-thanks';
$wgDonationInterfaceFailPage = 'Donate-error';


//GlobalCollect gateway globals
if ( $optionalParts['GlobalCollect'] === true ){
	$wgGlobalCollectGatewayURL = 'https://ps.gcsip.nl/wdl/wdl';
	$wgGlobalCollectGatewayTestingURL = 'https://'; // GlobalCollect testing URL
	
	$wgGlobalCollectGatewayMerchantID = ''; // GlobalCollect ID
	
	$wgGlobalCollectGatewayHtmlFormDir = $donationinterface_dir . 'globalcollect_gateway/forms/html';
	//this really should be redefined in LocalSettings. 
	$wgGlobalCollectGatewayAllowedHtmlForms = $wgDonationInterfaceAllowedHtmlForms;
}

//PayflowPro gateway globals
if ( $optionalParts['PayflowPro'] === true ){
	$wgPayflowProGatewayURL = 'https://payflowpro.paypal.com';
	$wgPayflowProGatewayTestingURL = 'https://pilot-payflowpro.paypal.com'; // Payflow testing URL
	
	$wgPayflowProGatewayPartnerID = ''; // PayPal or original authorized reseller
	$wgPayflowProGatewayVendorID = ''; // paypal merchant login ID
	$wgPayflowProGatewayUserID = ''; // if one or more users are set up, authorized user ID, else same as VENDOR
	$wgPayflowProGatewayPassword = ''; // merchant login password
	
	$wgPayflowProGatewayHtmlFormDir = $donationinterface_dir . 'payflowpro_gateway/forms/html';
	//this really should be redefined in LocalSettings. 
	$wgPayflowProGatewayAllowedHtmlForms = $wgDonationInterfaceAllowedHtmlForms;
}

//Stomp globals
if ($optionalParts['Stomp'] === true){
	$wgStompServer = "";
	//$wgStompQueueName = ""; //only set this with an actual value. Default is unset. 
	//$wgPendingStompQueueName = ""; //only set this with an actual value. Default is unset. 
}

//Extras globals - required for ANY optional class that is considered an "extra". 
if ($optionalParts['Extras'] === true){
	$wgDonationInterfaceExtrasLog = '';
}

//Custom Filters globals
if ( $optionalParts['CustomFilters'] === true ){
	//Define the action to take for a given $risk_score
	$wgDonationInterfaceCustomFiltersActionRanges = array(
		'process' => array( 0, 100 ),
		'review' => array( -1, -1 ),
		'challenge' => array( -1, -1 ),
		'reject' => array( -1, -1 ),
	);
	
	/**
	 * A value for tracking the 'riskiness' of a transaction
	 *
	 * The action to take based on a transaction's riskScore is determined by
	 * $action_ranges.  This is built assuming a range of possible risk scores
	 * as 0-100, although you can probably bend this as needed.
	 */
	$wgDonationInterfaceCustomFiltersRiskScore = 0;
}

//Minfraud globals
if ( $optionalParts['Minfraud'] === true || $optionalParts['Minfraud_as_filter'] === true ){
	/**
	 * Your minFraud license key.
	 */
	$wgMinFraudLicenseKey = '';

	/**
	 * Set the risk score ranges that will cause a particular 'action'
	 *
	 * The keys to the array are the 'actions' to be taken (eg 'process').
	 * The value for one of these keys is an array representing the lower
	 * and upper bounds for that action.  For instance,
	 *   $wgMinFraudActionRagnes = array(
	 * 		'process' => array( 0, 100)
	 * 		...
	 * 	);
	 * means that any transaction with a risk score greather than or equal
	 * to 0 and less than or equal to 100 will be given the 'process' action.
	 *
	 * These are evauluated on a >= or <= basis.  Please refer to minFraud
	 * documentation for a thorough explanation of the 'riskScore'.
	 */
	$wgMinFraudActionRanges = array(
		'process' => array( 0, 100 ),
		'review' => array( -1, -1 ),
		'challenge' => array( -1, -1 ),
		'reject' => array( -1, -1 )
	);

	// Timeout in seconds for communicating with MaxMind
	$wgMinFraudTimeout = 2;

	/**
	 * Define whether or not to run minFraud in stand alone mode
	 *
	 * If this is set to run in standalone, these scripts will be
	 * accessed directly via the "GatewayValidate" hook.
	 * You may not want to run this in standalone mode if you prefer
	 * to use this in conjunction with Custom Filters.  This has the
	 * advantage of sharing minFraud info with other filters.
	 */
	$wgMinFraudStandalone = TRUE;
	
}

//Minfraud as Filter globals
if ( $optionalParts['Minfraud_as_filter'] === true ){
	$wgMinFraudStandalone = FALSE;
}

//Recaptcha globals
if ( $optionalParts['Recaptcha'] === true ){
	/**
	 * Public and Private reCaptcha keys
	 *
	 * These can be obtained at:
	 *   http://www.google.com/recaptcha/whyrecaptcha
	 */
	$wgDonationInterfaceRecaptchaPublicKey = '';
	$wgDonationInterfaceRecaptchaPrivateKey = '';

	// Timeout (in seconds) for communicating with reCatpcha
	$wgDonationInterfaceRecaptchaTimeout = 2;

	/**
	 * HTTP Proxy settings
	 */
	$wgDonationInterfaceRecaptchaUseHTTPProxy = false;
	$wgDonationInterfaceRecaptchaHTTPProxy = false;

	/**
	 * Use SSL to communicate with reCaptcha
	 */
	$wgDonationInterfaceRecaptchaUseSSL = 1;

	/**
	 * The # of times to retry communicating with reCaptcha if communication fails
	 * @var int
	 */
	$wgDonationInterfaceRecaptchaComsRetryLimit = 3;
}

/**
 * SPECIAL PAGES
 */

//GlobalCollect gateway special pages
if ( $optionalParts['GlobalCollect'] === true ){
	$wgSpecialPages['GlobalCollectGateway'] = 'GlobalCollectGateway';
	$wgSpecialPages['GlobalCollectGatewayResult'] = 'GlobalCollectGatewayResult';
}
//PayflowPro gateway special pages
if ( $optionalParts['PayflowPro'] === true ){
	$wgSpecialPages['PayflowProGateway'] = 'PayflowProGateway';
}


/**
 * HOOKS
 */

//Unit tests
$wgHooks['UnitTestsList'][] = 'efDonationInterfaceUnitTests';

//Stomp hooks
if ($optionalParts['Stomp'] === true){
	$wgHooks['ParserFirstCallInit'][] = 'efStompSetup';
	$wgHooks['gwStomp'][] = 'sendSTOMP';
	$wgHooks['gwPendingStomp'][] = 'sendPendingSTOMP';
}

//Custom Filters hooks
if ($optionalParts['CustomFilters'] === true){
	$wgHooks["GatewayValidate"][] = array( 'Gateway_Extras_CustomFilters::onValidate' );
}

//Conversion Log hooks
if ($optionalParts['ConversionLog'] === true){
	// Sets the 'conversion log' as logger for post-processing
	$wgHooks["GatewayPostProcess"][] = array( "Gateway_Extras_ConversionLog::onPostProcess" );
}

//Recaptcha hooks
if ($optionalParts['Recaptcha'] === true){
	// Set reCpatcha as plugin for 'challenge' action
	$wgHooks["GatewayChallenge"][] = array( "Gateway_Extras_ReCaptcha::onChallenge" );
}

/**
 * APIS 
 */
// enable the API
$wgAPIModules['donate'] = 'DonationApi';
$wgAutoloadClasses['DonationApi'] = $donationinterface_dir . 'gateway_common/donation.api.php';

//Payflowpro API
if ( $optionalParts['PayflowPro'] === true ){
	$wgAPIModules['pfp'] = 'ApiPayflowProGateway';
	$wgAutoloadClasses['ApiPayflowProGateway'] = $donationinterface_dir . 'payflowpro_gateway/api_payflowpro_gateway.php';
}


/**
 * ADDITIONAL MAGICAL GLOBALS 
 */

// Resource modules
$wgResourceTemplate = array(
	'localBasePath' => $donationinterface_dir . 'modules',
	'remoteExtPath' => 'DonationInterface/modules',
);
$wgResourceModules['iframe.liberator'] = array(
	'scripts' => 'iframe.liberator.js',
	'position' => 'top'
	) + $wgResourceTemplate;
$wgResourceModules['donationInterface.skinOverride'] = array(
	'styles' => 'skinOverride.css',
	'position' => 'top'
	) + $wgResourceTemplate;

// Resources for ResourceLoader - 98582
$wgResourceTemplate = array(
	'localBasePath' => $donationinterface_dir . 'gateway_forms',
	'remoteExtPath' => 'DonationInterface/gateway_forms',
);
$wgResourceModules[ 'pfp.form.rapidhtml.webitects' ] = array(
	'styles' => array(
		'rapidhtml/css/lp1.css',
		'rapidhtml/css/Webitects.css',
	),
	'scripts' => array(
		'rapidhtml/js/jquery.ezpz_hint.js',
	),
	'dependencies' => array(
		'jquery.ui.accordion'
	)
) + $wgResourceTemplate;


//99077
// RapidHtml globalcollect_test form resources
$wgResourceModules[ 'pfp.form.rapidhtml.globalcollect_test' ] = array(
	'styles' => array(
		'css/TwoStepTwoColumnLetter3.css',
		'css/gateway.css',
	),
	'scripts' => array(),
	'dependencies' => array(),
) + $wgResourceTemplate;

// form validation resource
//TODO: Move this somewhere gateway-agnostic. 
$wgResourceModules[ 'pfp.form.core.validate' ] = array(
	'styles' => array(),
	'scripts' => 'validate_input.js',
	'dependencies' => 'pfp.form.core.pfp_css',
	'localBasePath' => $donationinterface_dir . 'payflowpro_gateway',
	'remoteExtPath' => 'DonationInterface/payflowpro_gateway'
);

// general PFP css
$wgResourceModules[ 'pfp.form.core.pfp_css' ] = array(
	'styles' => 'css/gateway.css',
	'scripts' => array(),
	'dependencies' => array(),
) + $wgResourceTemplate;


//98589 & 98600
// RapidHtml lightbox form resources
$wgResourceModules[ 'pfp.form.rapidhtml.lightbox.js' ] = array(
	'scripts' => array(
		'rapidhtml/js/lightbox1.js',
	),
	'dependencies' => array(
		'jquery.ui.core',
		'jquery.ui.widget',
		'jquery.ui.mouse',
		'jquery.ui.position',
		'jquery.ui.draggable',
		'jquery.ui.resizable',
		'jquery.ui.button',
		'jquery.ui.dialog',
	),
	'position' => 'top',
) + $wgResourceTemplate;

// RapidHtml lightbox form css resources (these are separate from the js
// resources for a good reason but I forget what - I believe to facilitate
// ensuring proper load order?
$wgResourceModules[ 'pfp.form.rapidhtml.lightbox.css' ] = array(
	'styles' => array(
		'rapidhtml/css/lightbox1.css',	
	),
	'position' => 'top',
) + $wgResourceTemplate;



$wgExtensionMessagesFiles['DonateInterface'] = $donationinterface_dir . 'donate_interface/donate_interface.i18n.php';


//GlobalCollect gateway magical globals

//TODO: all the bits where we make the i18n make sense for multiple gateways. This is clearly less than ideal.
if ( $optionalParts['GlobalCollect'] === true ){
	$wgExtensionMessagesFiles['GlobalCollectGateway'] = $donationinterface_dir . 'payflowpro_gateway/payflowpro_gateway.i18n.php';
	$wgExtensionMessagesFiles['GlobalCollectGatewayCountries'] = $donationinterface_dir . 'payflowpro_gateway/payflowpro_gateway.countries.i18n.php';
	$wgExtensionMessagesFiles['GlobalCollectGatewayUSStates'] = $donationinterface_dir . 'payflowpro_gateway/payflowpro_gateway.us-states.i18n.php';
	$wgExtensionAliasesFiles['GlobalCollectGateway'] = $donationinterface_dir . 'payflowpro_gateway/payflowpro_gateway.alias.php';
}

//PayflowPro gateway magical globals
if ( $optionalParts['PayflowPro'] === true ){
	$wgExtensionMessagesFiles['PayflowProGateway'] = $donationinterface_dir . 'payflowpro_gateway/payflowpro_gateway.i18n.php';
	$wgExtensionMessagesFiles['PayflowProGatewayCountries'] = $donationinterface_dir . 'payflowpro_gateway/payflowpro_gateway.countries.i18n.php';
	$wgExtensionMessagesFiles['PayflowProGatewayUSStates'] = $donationinterface_dir . 'payflowpro_gateway/payflowpro_gateway.us-states.i18n.php';
	$wgExtensionAliasesFiles['PayflowProGateway'] = $donationinterface_dir . 'payflowpro_gateway/payflowpro_gateway.alias.php';
	$wgAjaxExportList[] = "fnPayflowProofofWork";
}

//Minfraud magical globals
if ( $optionalParts['Minfraud'] === true ){ //We do not want this in filter mode. 
	$wgExtensionFunctions[] = 'efMinFraudSetup';
}

//Minfraud as Filter globals
if ( $optionalParts['Minfraud_as_filter'] === true ){
	$wgExtensionFunctions[] = 'efCustomFiltersMinFraudSetup';
}


/**
 * FUNCTIONS
 */

//---Stomp functions---
if ($optionalParts['Stomp'] === true){
	require_once( $donationinterface_dir . 'activemq_stomp/activemq_stomp.php'  );
}

//---Minfraud functions---
if ($optionalParts['Minfraud'] === true){
	require_once( $donationinterface_dir . 'extras/minfraud/minfraud.php'  );
}

//---Minfraud as filter functions---
if ($optionalParts['Minfraud_as_filter'] === true){
	require_once( $donationinterface_dir . 'extras/custom_filters/filters/minfraud/minfraud.php'  );
}

function efDonationInterfaceUnitTests( &$files ) {
	$files[] = dirname( __FILE__ ) . '/tests/AllTests.php';
	return true;
}

unset( $optionalParts );
