Donation Interface

To install the DontaionInterface extension, put the following line in LocalSettings.php:
  wfLoadExtension( 'DonationInterface' );

All of this extension's globals can be overridden on a per-gateway basis by
adding a setting with the same name, but with 'DonationInterface' replaced
with the gatway's name. To override $wgDonationInterfaceUseSyslog just for
Adyen, add
  $wgAdyenGatewayUseSyslog = true;
to LocalSettings.php.


Some configuration options and default values follow. To change the defaults,
add a line to LocalSettings.php with the new value.

Set these to true to enable each payment processor integration:

$wgGlobalCollectGatewayEnabled = false
$wgAmazonGatewayEnabled = false
$wgAdyenGatewayEnabled = false
$wgAstroPayGatewayEnabled = false
$wgPaypalExpressGatewayEnabled = false
$wgPaypalGatewayEnabled = false

You must also configure account information for each processor as
described in 'Processors and accounts' below.

==== Misc ====

Retry Loop Count - If there's a place where the API can choose to loop on some retry behavior, do it this number of times.
$wgDonationInterfaceRetryLoopCount = 3

Number of seconds to wait for a response from processor API endpoints
$wgDonationInterfaceTimeout = 5

Test mode flag, alters various behavior
$wgDonationInterfaceTest = false

$wgDonationInterfaceEnableFormChooser = false
/**
 * A string or array of strings for making tokens more secure
 *
 * Please set this!  If you do not, tokens are easy to get around, which can
 * potentially leave you and your users vulnerable to CSRF or other forms of
 * attack.
 */
$wgDonationInterfaceSalt = $wgSecretKey

/**
 * 3D Secure enabled currencies (and countries) for Credit Card.
 * An array in the form of currency => array of countries
 * (all-caps ISO 3166-1 alpha-2), or an empty array for all transactions in that
 * currency regardless of country of origin.
 * As this is a mandatory check for all INR transactions, that rule made it into
 * the default.
 */
$wgDonationInterface3DSRules = array(
	'INR' => array(), //all countries
)

Caching:
To let SmashPig objects use Mediawiki's local cluster BagOStuff cache, add this
to your SmashPig configuration under key 'cache':
 class: LocalClusterPsr6Cache
(no constructor-parameters need to be specified)

==== Form appearance and content ====

Besides these settings, please see DonationInterfaceFormSettings.php

Title to transclude in form template as {{{ appeal_text }}}.
$appeal and $language will be substituted before transclusion

$wgDonationInterfaceAppealWikiTemplate = 'LanguageSwitch|2011FR/$appeal/text|$language'

Used as the value for $appeal when nothing is given in query string
$wgDonationInterfaceDefaultAppeal = 'JimmyQuote'

$language and $country will be substituted in the next four URLs

URL of a page for donors who encounter problems
$wgDonationInterfaceProblemsURL = 'https://wikimediafoundation.org/wiki/Special:LandingCheck?landing_page=Problems_donating&basic=true&language=$language&country=$country'

URL of a page listing alternate ways to give.
$wgDonationInterfaceOtherWaysURL = 'https://wikimediafoundation.org/wiki/Special:LandingCheck?basic=true&landing_page=Ways_to_Give&language=$language&country=$country'

URL of your organizations FAQ page for donors
$wgDonationInterfaceFaqURL = 'https://wikimediafoundation.org/wiki/Special:LandingCheck?basic=true&landing_page=FAQ&language=$language&country=$country'

URL of a page detailing tax detectability of donations to your organization
$wgDonationInterfaceTaxURL = 'https://wikimediafoundation.org/wiki/Special:LandingCheck?basic=true&landing_page=Tax_Deductibility&language=$language&country=$country'

Email address donors should contact with any donation-related problems
$wgDonationInterfaceProblemsEmail = 'problemsdonating@wikimedia.org'

Email address donors should contact with donations too big to process online
$wgDonationInterfaceMajorGiftsEmail = 'benefactors@wikimedia.org';

The full URL for Javascript-disabled credit card form redirect
$wgDonationInterfaceNoScriptRedirect = null

Dummy email address associated with donation if donor does not provide one
$wgDonationInterfaceDefaultEmail = 'nobody@wikimedia.org'
/**
 * When true, error forms will be preferred over FailPage specified below
 * @var bool
 */
$wgDonationInterfaceRapidFail = false

/**
 * Default Thank You and Fail pages for all of donationinterface - language will be calc'd and appended at runtime.
 */
$wgDonationInterfaceThankYouPage = 'Donate-thanks'
$wgDonationInterfaceFailPage = 'Donate-error'

/**
 * Where to send donors who click a 'cancel' button on a payment processor's web site.
 * Currently only used with PayPal.
 */
$wgDonationInterfaceCancelPage = 'Donate-error'

/**
 * If this is set to a valid directory path, yaml files under this path can
 * potentially replace built-in config files. For example, if this is set to
 * /etc/di_alt/, and query string variable 'variant' has value 'nostate',
 * then /etc/di_alt/nostate/globalcollect/country_fields.yaml will be used
 * instead of the built-in globalcollect_gateway/config/country_fields.yaml.
 * Superseded files will be completely ignored, so copy and modify originals.
 * Only latin letters, numbers, and underscores are allowed in variant.
 */
$wgDonationInterfaceVariantConfigurationDirectory = false

==== Debug and logging ====

$wgDonationInterfaceDisplayDebug = false
$wgDonationInterfaceUseSyslog = false
$wgDonationInterfaceSaveCommStats = false

Set to true to allow debug level log messages.
TODO: Deprecate and show how to accomplish the same thing using Monolog
configuration.

$wgDonationInterfaceDebugLog = false

Use sparingly, preferably for a single gateway.  When true, log verbose
cURL output (including IPs resolved) at info level.

$wgDonationInterfaceCurlVerboseLog = false

As donations are sent to the "donations" queue, also log the json blob.

$wgDonationInterfaceLogCompleted = false

As donations are sent to the "completed" queue, also log the json blob.

$wgDonationInterfaceLogCompleted = false

==== Currency and amounts ====

Configure price ceiling and floor for valid contribution amount.  Values
should be in USD.

$wgDonationInterfacePriceFloor = 1.00
$wgDonationInterfacePriceCeiling = 10000.00

If set to a currency code, gateway forms will try to convert amounts
in unsupported currencies to the fallback instead of just showing
an unsupported currency error.
$wgDonationInterfaceFallbackCurrency = false

For a gateway that has exactly one valid currency per supported country,
you can instead set this variable to true to make gateway forms use an
appropriate fallback currency for the selected country.
$wgDonationInterfaceFallbackCurrencyByCountry = false

When this is true and an unsupported currency has been converted to the
fallback (see above), we show an interstitial page notifying the user
of the conversion before sending the donation to the gateway.
$wgDonationInterfaceNotifyOnConvert = true



==== Processors and accounts ====

//GlobalCollect gateway globals

$wgGlobalCollectGatewayTestingURL = 'https://ps.gcsip.nl/wdl/wdl'
// Actually it's ps.gcsip.com, but trust me it's better this way.
$wgGlobalCollectGatewayURL = 'https://ps.gcsip.nl/wdl/wdl'

#	$wgGlobalCollectGatewayAccountInfo['example'] = array(
#		'MerchantID' => '', // GlobalCollect ID
#	)

$wgGlobalCollectGatewayCvvMap = array(
	'M' => true, //CVV check performed and valid value.
	'N' => false, //CVV checked and no match.
	'P' => true, //CVV check not performed, not requested
	'S' => false, //Card holder claims no CVV-code on card, issuer states CVV-code should be on card.
	'U' => true, //? //Issuer not certified for CVV2.
	'Y' => false, //Server provider did not respond.
	'0' => true, //No service available.
	'' => false, //No code returned. All the points.
)

$wgGlobalCollectGatewayAvsMap = array(
	'A' => 50, //Address (Street) matches, Zip does not.
	'B' => 50, //Street address match for international transactions. Postal code not verified due to incompatible formats.
	'C' => 50, //Street address and postal code not verified for international transaction due to incompatible formats.
	'D' => 0, //Street address and postal codes match for international transaction.
	'E' => 100, //AVS Error.
	'F' => 0, //Address does match and five digit ZIP code does match (UK only).
	'G' => 50, //Address information is unavailable international transaction non-AVS participant.
	'I' => 50, //Address information not verified for international transaction.
	'M' => 0, //Street address and postal codes match for international transaction.
	'N' => 100, //No Match on Address (Street) or Zip.
	'P' => 50, //Postal codes match for international transaction. Street address not verified due to incompatible formats.
	'R' => 100, //Retry, System unavailable or Timed out.
	'S' => 50, //Service not supported by issuer.
	'U' => 50, //Address information is unavailable.
	'W' => 50, //9 digit Zip matches, Address (Street) does not.
	'X' => 0, //Exact AVS Match.
	'Y' => 0, //Address (Street) and 5 digit Zip match.
	'Z' => 50, //5 digit Zip matches, Address (Street) does not.
	'0' => 25, //No service available.
	'' => 100, //No code returned. All the points.
)

Amazon account info is mostly read from SmashPig configuration
FIXME: stop duplicating SellerID and ClientID
FIXME: actually use 'Region'
$wgAmazonGatewayAccountInfo['example'] = array(
    'SellerID' => '', // 13 or so uppercase letters
    'ClientID' => '', // app or site-specific, starts with amznX.application
    'Region' => '', // 'de', 'jp', 'uk', or 'us'
    'WidgetScriptURL' => 'https://static-na.payments-amazon.com/OffAmazonPayments/us/sandbox/js/Widgets.js',
    // static-eu serves widgets for uk and de, but jp uses this awful URL:
    // https://origin-na.ssl-images-amazon.com/images/G/09/EP/offAmazonPayments/sandbox/prod/lpa/js/Widgets.js
    // remove 'sandbox/' from above URLs for production use
    'ReturnURL' => ''
    // Sorry, devs, ReturnURL HAS to be https.
    // Also, it has to be whitelisted for your application at sellercentral.amazon.com
    // e.g. https://payments.wikimedia.org/index.php/Special:AmazonGateway
)

// This URL appears to be global and usable for both sandbox and non-sandbox
$wgAmazonGatewayLoginScript = 'https://api-cdn.amazon.com/sdk/login1.js'

$wgPaypalGatewayURL = 'https://www.paypal.com/cgi-bin/webscr'
$wgPaypalGatewayTestingURL = 'https://www.sandbox.paypal.com/cgi-bin/webscr'
$wgPaypalGatewayRecurringLength = '0' // 0 should mean forever

$wgPaypalGatewayXclickCountries = array()

# Example PayPal Express Checkout account:
#
# $wgPaypalExpressGatewayAccountInfo['test'] = array(
#     'User' => 'abc',
#     'Password' => '12345',
#
#     // Use either certificate (preferred) OR signature authentication:
#     // 'Signature' => 'or 123123123',
#     'CertificatePath' => '/absolute path to cert_key_pem.txt',
#
#     // TODO: Use parameter substitution.
#     'RedirectURL' => 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&useraction=commit&token=',
# )

# Example legacy PayPal
#	$wgPaypalGatewayAccountInfo['example'] = array(
#		'AccountEmail' => "",
#	)

# https://developer.paypal.com/docs/classic/api/endpoints/
# TODO: Move to configuration.
# We use different URLs depending on: authentication method and testingness.
$wgPaypalExpressGatewayCertificateURL = 'https://api.paypal.com/nvp'
$wgPaypalExpressGatewaySignatureURL = 'https://api-3t.paypal.com/nvp'
$wgPaypalExpressGatewayTestingCertificateURL = 'https://api.sandbox.paypal.com/nvp'
$wgPaypalExpressGatewayTestingSignatureURL = 'https://api-3t.sandbox.paypal.com/nvp'

$wgAdyenGatewayURL = 'https://live.adyen.com'
$wgAdyenGatewayTestingURL = 'https://test.adyen.com'

// Adyen automatically declines any payment with a fraud score over 100.
// This variable caps the score we send them so donations we've flagged in
// error can be reviewed and manually captured.
$wgAdyenGatewayMaxRiskScore = 95

#	$wgAdyenGatewayAccountInfo['example'] = array(
#		'AccountName' => '' // account identifier, not login name
#		'SharedSecret' => '' // entered in the skin editor
#		'SkinCode' => ''
#	)

// Set base URLs here.  Individual transactions have their own paths
$wgAstroPayGatewayURL = 'https://astropaycard.com/'
$wgAstroPayGatewayTestingURL = 'https://sandbox.astropaycard.com/'
#	$wgAstroPayGatewayAccountInfo['example'] = array(
#		'Create' => array( // For creating invoices
#			'Login' => '',
#			'Password' => '',
#		),
#		'Status' => array( // For checking payment status
#			'Login' => '',
#			'Password' => '',
#		),
#		'SecretKey' => '', // For signing requests and verifying responses
#	)


==== Queues ====

DonationInterface uses the SmashPig library for queue handling.

Essential queues:
* 'donations': Incoming donations that we think have been paid for.
* 'pending': Transactions still needing action before they are settled.

Non-critical queues:
'payments-antifraud': These messages will be shoved into the fraud database
    (see crm/modules/fredge).
'payments-init': These are shoved into the payments-initial database.
'banner-history': Banner history log ID-contribution tracking ID associations
    that go in Drupal in banner_history_contribution_associations.
    See crm/modules/queue2civicrm/banner_history.


==== Fraud filters and blocking ====

/**
 * Forbidden countries. No donations will be allowed to come in from countries
 * in this list.
 * All should be represented as all-caps ISO 3166-1 alpha-2
 * This one global shouldn't ever be overridden per gateway. As it's probably
 * going to only contain countries forbidden by law, there's no reason
 * to override by gateway and as such it's always referenced directly.
 */
$wgDonationInterfaceForbiddenCountries = array()

//Custom Filters globals
/**
 * Set the risk score ranges that will cause a particular 'action'
 *
 * The keys to the array are the 'actions' to be taken (eg 'process').
 * The value for one of these keys is an array representing the lower
 * and upper bounds for that action.  For instance,
 *  $wgDonationInterfaceCustomFiltersActionRanges = array(
 * 		'process' => array( 0, 100)
 * 		...
 * 	)
 * means that any transaction with a risk score greater than or equal
 * to 0 and less than or equal to 100 will be given the 'process' action.
 *
 * These are evaluated on a >= or <= basis.
 */
$wgDonationInterfaceCustomFiltersActionRanges = array(
	'process' => array( 0, 100 ),
	'review' => array( -1, -1 ),
	'challenge' => array( -1, -1 ),
	'reject' => array( -1, -1 ),
)

/**
 * A base value for tracking the 'riskiness' of a transaction
 *
 * The action to take based on a transaction's riskScore is determined by
 * CustomFiltersActionRanges.  This is built assuming a range of possible
 * risk scores as 0-100, although you can probably bend this as needed.
 */
$wgDonationInterfaceCustomFiltersRiskScore = 0

// minFraud globals
/**
 * Your minFraud User ID.
 */
$wgDonationInterfaceMinFraudUserId = ''

/**
 * Your minFraud license key.
 */
$wgDonationInterfaceMinFraudLicenseKey = ''

/**
 * Options to pass along to the minFraud API Client, including timeout,
 * specific servers, and proxies.
 * The following list is copied from the minFraud client documentation:
 * * 'host' - The host to use when connecting to the web service.
 * * 'userAgent' - The prefix for the User-Agent header to use in the
 *   request.
 * * 'caBundle' - The bundle of CA root certificates to use in the request.
 * * 'connectTimeout' - The connect timeout to use for the request.
 * * 'timeout' - The timeout to use for the request.
 * * 'proxy' - The HTTP proxy to use. May include a schema, port,
 *   username, and password, e.g., 'http://username:password@127.0.0.1:10'.
 * * 'locales' - An array of locale codes to use for the location name
 *   properties.
 * * 'validateInput' - Default is 'true'. Determines whether values passed
 *   to the 'with*()' methods are validated. It is recommended that you
 *   leave validation on while developing and only (optionally) disable it
 *   before deployment.
 */
$wgDonationInterfaceMinFraudClientOptions = array()

// Weight to give minFraud risk scores when enabled
// 100 means to use the raw minFraud score
$wgDonationInterfaceMinFraudWeight = 100

// Score for the minFraud filter to assign if there is an error querying
// the minFraud web service, including no response
$wgDonationInterfaceMinFraudErrorScore = 50

/**
 * When to send an email to $wgEmergencyContact that we're
 * running low on minFraud queries. Will continue to send
 * once per day until the limit is once again over the limit.
 */
$wgDonationInterfaceMinFraudAlarmLimit = 25000

/**
 * Additional fields to send in each Minfraud request.
 * Parameter documentation: http://dev.maxmind.com/minfraud/#Input
 * We will always send city, region, postal, country, domain, email (MD5
 * hashed), transaction_id, ip_address, user_agent, and accept_language.
 * Things you can put here: email (send the real address instead of a hash),
 * amount, currency, first_name, last_name, and street_address.
 */
$wgDonationInterfaceMinFraudExtraFields = array()

//Referrer Filter globals
$wgDonationInterfaceCustomFiltersRefRules = array()

//Source Filter globals
$wgDonationInterfaceCustomFiltersSrcRules = array()

//Functions Filter globals
//These functions fire when we trigger the antifraud filters.
//Anything that needs access to API call results goes here.
//FIXME: you need to copy all the initial functions here because
//individual function scores don't persist like filter scores.
$wgDonationInterfaceCustomFiltersFunctions = array()
//These functions fire on GatewayReady, so all they can see is the
//request and the session.
$wgDonationInterfaceCustomFiltersInitialFunctions = array()

//IP velocity filter globals
$wgDonationInterfaceIPVelocityFailScore = 100
$wgDonationInterfaceIPVelocityTimeout = 60 * 5	//5 minutes in seconds
$wgDonationInterfaceIPVelocityThreshhold = 3	//3 transactions per timeout
//$wgDonationInterfaceIPVelocityToxicDuration can be set to penalize IP addresses
//that attempt to use cards reported stolen.
//$wgDonationInterfaceIPVelocityFailDuration is also something you can set...
//If you leave it blank, it will use the VelocityTimeout as a default.

// Session velocity filter globals
$wgDonationInterfaceSessionVelocity_HitScore = 10  // How much to add to the score for an initial API hit
$wgDonationInterfaceSessionVelocity_Multiplier = 1 // Hit score increases by this factor for each subsequent API call
$wgDonationInterfaceSessionVelocity_DecayRate = 1  // Linear decay rate pts / sec
$wgDonationInterfaceSessionVelocity_Threshold = 50 // Above this score, we deny users the page

/**
 * $wgDonationInterfaceCountryMap
 *
 * A score of 0 for a country means no risk.
 * A score of 100 means this country is extremely risky for fraud.
 *
 * The score for a country has the following range:
 *
 * 0 <= $score <= 100
 *
 * To enable this filter add this to your LocalSettings.php:
 *
 * @code
 * <?php
 *
 * $wgDonationInterfaceCustomFiltersFunctions = array(
 * 	'getScoreCountryMap' => 100,
 * )
 *
 * $wgDonationInterfaceCountryMap = array(
 * 	'CA' =>  1,
 * 	'US' => 5,
 * )
 * ?>
 * @endcode
 */
$wgDonationInterfaceCountryMap = array()

/**
 * $wgDonationInterfaceEmailDomainMap
 *
 * A score of 0 for an email domain means no risk.
 * A score of 100 means this email domain is extremely risky for fraud.
 * Scores may be negative.
 *
 * To enable this filter add this to your LocalSettings.php:
 *
 * @code
 * <?php
 *
 * $wgDonationInterfaceCustomFiltersFunctions = array(
 * 	'getScoreEmailDomainMap' => 100,
 * )
 *
 * $wgDonationInterfaceEmailDomainMap = array(
 * 	'gmail.com' =>  5,
 * 	'wikimedia.org' => 0,
 * )
 * ?>
 * @endcode
 */
$wgDonationInterfaceEmailDomainMap = array()

/**
 * $wgDonationInterfaceUtmCampaignMap
 *
 * A score of 0 for utm_campaign means no risk.
 * A score of 100 means this utm_campaign is extremely risky for fraud.
 * Scores may be negative
 *
 * To enable this filter add this to your LocalSettings.php:
 *
 * @code
 * <?php
 *
 * $wgDonationInterfaceCustomFiltersFunctions = array(
 * 	'getScoreUtmCampaignMap' => 100,
 * )
 *
 * $wgDonationInterfaceUtmCampaignMap = array(
 * 	'/^$/' =>  20,
 * 	'/some-odd-string/' => 100,
 * )
 * ?>
 * @endcode
 */
$wgDonationInterfaceUtmCampaignMap = array()

/**
 * $wgDonationInterfaceUtmMediumMap
 *
 * A score of 0 for utm_medium means no risk.
 * A score of 100 means this utm_medium is extremely risky for fraud.
 * Scores may be negative
 *
 * To enable this filter add this to your LocalSettings.php:
 *
 * @code
 * <?php
 *
 * $wgDonationInterfaceCustomFiltersFunctions = array(
 * 	'getScoreUtmMediumMap' => 100,
 * )
 *
 * $wgDonationInterfaceUtmMediumMap = array(
 * 	'/^$/' =>  20,
 * 	'/some-odd-string/' => 100,
 * )
 * ?>
 * @endcode
 */
$wgDonationInterfaceUtmMediumMap = array()

/**
 * $wgDonationInterfaceUtmSourceMap
 *
 * A score of 0 for utm_source means no risk.
 * A score of 100 means this utm_source is extremely risky for fraud.
 * Scores may be negative
 *
 * To enable this filter add this to your LocalSettings.php:
 *
 * @code
 * <?php
 *
 * $wgDonationInterfaceCustomFiltersFunctions = array(
 * 	'getScoreUtmSourceMap' => 100,
 * )
 *
 * $wgDonationInterfaceUtmSourceMap = array(
 * 	'/^$/' =>  20,
 * 	'/some-odd-string/' => 100,
 * )
 * ?>
 * @endcode
 */
$wgDonationInterfaceUtmSourceMap = array()

/**
 * $wgDonationInterfaceNameFilterRules
 *
 * For each entry in the rule array,
 * Set KeyMapA and KeyMapB to mutually exclusive arrays of characters.
 * Set GibberishWeight to reflect the ratio of characters from one group that will cause a fail.
 * Set Score to the number of points to assign on fail.
 *
 * To enable this filter add this to your LocalSettings.php:
 *
 * @code
 * <?php
 *
 * $wgDonationInterfaceCustomFiltersFunctions = array(
 * 	'getScoreName' => 100,
 * )
 *
 * $wgDonationInterfaceNameFilterRules = array(
 *     array(
 *         'KeyMapA' => array('a','s','d'),
 *         'KeyMapB' => array('h','j','k','l'),
 *         'GibberishWeight' => .9,
 *         'Score' => 10,
 *     ),
 * )
 *
 */

$wgDonationInterfaceNameFilterRules = array()

$wgDonationInterfaceEnableConversionLog = false //this is definitely an Extra
$wgDonationInterfaceEnableMinFraud = false //this is definitely an Extra

/**
 * @global boolean Set to false to disable all filters, or set a gateway-
 * specific value such as $wgPaypalGatewayEnableCustomFilters = false.
 */
$wgDonationInterfaceEnableCustomFilters = true
$wgDonationInterfaceEnableReferrerFilter = false //extra
$wgDonationInterfaceEnableSourceFilter = false //extra
$wgDonationInterfaceEnableFunctionsFilter = false //extra
$wgDonationInterfaceEnableIPVelocityFilter = false //extra
$wgDonationInterfaceEnableSessionVelocityFilter = false //extra
$wgDonationInterfaceEnableSystemStatus = false //extra
