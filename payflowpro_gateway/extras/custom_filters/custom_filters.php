<?php
/**
 * Provides a unified way to define and run custom filters for incoming transactions
 *
 * Running filters through 'custom filters' rather than directly through the validate hook in the gateway
 * offers the advantage of simplifying the passage of relvent data between filters/validators that's 
 * needed to perform more complex validation/filtering of transactions.
 *
 * The actual filters themselves are regular MW extensions and can optional be organized in filters/
 * They should be invoked by using the 'PayflowGatewayCustomFilter' hook, which will pass the entire
 * CustomFilter object to the filter.  The gateway object and its data are included in the CustomFilter
 * object.
 */

if ( !defined( 'MEDIAWIKI' ) ) { 
    die( "This file is part of the MinFraud for PayflowPro Gateway extension. It is not a valid entry point.\n" );  
}

$wgExtensionCredits['payflowprogateway_custom_filters'][] = array(
    'name' => 'custom filters',
    'author' =>'Arthur Richards', 
    'url' => '', 
    'description' => 'This extension provides a way to define custom filters for incoming transactions for the Payflow Pro gateway.'
);

/** 
 * Define the action to take for a given $risk_score
 */
$wgPayflowGatewayCustomFiltersActionRanges = array(
	'process'   => array( 0, 100 ),
	'review'    => array( -1, -1 ),
	'challenge' => array( -1, -1 ),
	'reject'    => array( -1, -1 ),
);

/**
 * A value for tracking the 'riskiness' of a transaction
 *
 * The action to take based on a transaction's riskScore is determined by 
 * $action_ranges.  This is built assuming a range of possible risk scores
 * as 0-100, although you can probably bend this as needed.
 */
$wgPayflowGatewayCustomFiltersRiskScore = 0;

$dir = dirname( __FILE__ ) . "/";
$wgAutoloadClasses['PayflowProGateway_Extras_CustomFilters'] = $dir . "custom_filters.body.php";

$wgHooks["PayflowGatewayValidate"][] = array( 'PayflowProGateway_Extras_CustomFilters::onValidate' );
