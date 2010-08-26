<?php
/**
 * An abstract class and set up for payflowpro gateway 'extras'
 *
 * To install:
 *      require_once( "$IP/extensions/DonationInterface/payflowpro_gateway/extras/extras.php"
 * Note: This should be specified in LocalSettings.php BEFORE requiring any of the other 'extras'
 */

if ( !defined( 'MEDIAWIKI' ) ) { 
        die( "This file is part of PayflowPro Gateway extension. It is not a valid entry point.\n");
}

$wgExtensionCredits['payflowprogateway_extras'][] = array(
        'name' => 'extras',
        'author' =>'Arthur Richards', 
        'url' => '', 
        'description' => "This extension handles some of the set up required for PayflowPro Gateway extras"
);

/**
 * Full path to file to use for logging for Payflowpro Gateway scripts
 *
 * Declare in LocalSettings.php
 */
$wgPayflowGatewayLog = '';

$dir = dirname( __FILE__ ) . "/";
$wgAutoloadClasses['PayflowProGateway_Extras'] = $dir . "extras.body.php";
