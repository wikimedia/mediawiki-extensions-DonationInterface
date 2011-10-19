<?php

/**
 * Provides a method for filtering transactions based on referrer
 *
 * To install:
 *   require_once( "$IP/extensions/DonationInterface/extras/custom_filters/filters/referrer/referrer.php" );
 */
if ( !defined( 'MEDIAWIKI' ) ) {
	die( "This file is part of the referrer custom filter part of the Gateway extension.  It is not a valid entry point\n" );
}

$wgExtensionCredits['gateway_customfilters_referrer'][] = array(
	'name' => 'custom filter: referrer',
	'author' => 'Arthur Richards',
	'url' => '',
	'description' => 'This extension provides a way to filter transactions based on their referrer.'
);

/**
 * An array defining a regex to match referrer URLs and their associated risk score amount
 *
 * The key of the array is a regular expression to run against the referrer and the value is
 * the amount  to add to the risk score.  The regex is run through preg_match and does not
 * need to include staring/ending delimiters - be sure to escape your characters!
 *
 * eg:
 *   $wgCustomFiltersRefRules['fraud\.com'] = "100";
 *   // increases risk score for trxns with http://fraud.com referrals by 100
 */
$wgCustomFiltersRefRules = array( );

$wgAutoloadClasses['Gateway_Extras_CustomFilters_Referrer'] = dirname( __FILE__ ) . "/referrer.body.php";
$wgHooks["GatewayCustomFilter"][] = array( 'Gateway_Extras_CustomFilters_Referrer::onFilter' );
