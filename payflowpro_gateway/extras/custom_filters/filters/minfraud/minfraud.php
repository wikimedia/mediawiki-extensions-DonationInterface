<?php
/**
 * Custom filter using minFraud
 *
 * Essentially acts as a wrapper for the minFraud extra and runs minFraud
 * queries via custom filter paradigm.  This allows us to capture the 
 * riskScore from minfraud and adjust it with our own custom filters and
 * risk score modifications.
 *
 * This inherits minFraud settings form the main minFraud extension.  To make
 * transactions run through minFraud outside of custom filters, set
 * $wgMinFraudStandalone = TRUE
 *
 * To install:
 *   require_once( "$IP/extensions/DonationInterface/payflowpro_gateway/extras/custom_filters/filters/minfraud.php" );
 */

 $wgExtensionCredits['payflowprogateway_extras_customfilters_minfraud'][] = array(
    'name' => 'minfraud custom filter',
	'author' =>'Arthur Richards', 
	'url' => '', 
	'description' => 'This extension uses the MaxMind minFraud service as a validator for the Payflow Pro gateway via custom filters.'
);

/**
 * Set minFraud to NOT run in standalone mode.
 *
 * If minFraud is set to run in standalone mode, it will not be run 
 * through custom filters.  If you do not know what you're doing 
 * or otherwise have this set up incorrectly, you may have unexpected
 * results.  If you want minFraud to run OUTSIDE of custom filters,
 * you will want to make sure you know whether minFraud queries are 
 * happening before or after custom filters, defined by the order of 
 * your require statements in LocalSettings.
 */
$wgMinFraudStandalone = FALSE;

$dir = dirname( __FILE__ ) . "/";
$wgAutoloadClasses['PayflowProGateway_Extras_MinFraud'] = $dir . "../../../minfraud/minfraud.body.php";
$wgAutoloadClasses['PayflowProGateway_Extras_CustomFilters_MinFraud'] = $dir . "minfraud.body.php";
$wgExtensionFunctions[] = 'efCustomFiltersMinFraudSetup';

function efCustomFiltersMinFraudSetup() {
	global $wgMinFraudStandalone, $wgHooks;
	if ( !$wgMinFraudStandalone ) $wgHooks[ 'PayflowGatewayCustomFilter' ][] = array( "PayflowProGateway_Extras_CustomFilters_MinFraud::onValidate" );
}
