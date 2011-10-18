<?php
/**
 * Provides a method for filtering transactions based on source
 *
 * To install:
 *   require_once( "$IP/extensions/DonationInterface/payflowpro_gateway/extras/custom_filters/filters/source/source.php" );
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	die( "This file is part of the source custom filter part of the PayflowPro Gateway extension.  It is not a valid entry point\n" );
}

$wgExtensionCredits['payflowprogateway_customfilters_source'][] = array(
	'name' => 'custom filter: source',
	'author' => 'Arthur Richards',
	'url' => '',
	'description' => 'This extension provides a way to filter transactions based on their source.'
);

/**
 * An array defining source strings and their associated risk score amount
 *
 * The key of the array is a regular expression to run against the source and the value is
 * the amount  to add to the risk score.  The regex is run through preg_match and does not
 * need to include staring/ending delimiters - be sure to escape your characters!
 *
 * eg:
 *   $wgCustomFiltersSrcRules['support.cc'] = "100";
 *   // increases risk score for trxns with source of 'support.cc' referrals by 100
 */
$wgCustomFiltersSrcRules = array();

$wgAutoloadClasses['PayflowProGateway_Extras_CustomFilters_Source'] = dirname( __FILE__ ) . "/source.body.php";
$wgHooks["PayflowGatewayCustomFilter"][] = array( 'PayflowProGateway_Extras_CustomFilters_Source::onFilter' );
