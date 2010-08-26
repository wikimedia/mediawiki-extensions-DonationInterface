<?php
/**
 * Extra to log payflow response during post processing hook
 *
 * @fixme Class/file names should likely change to reflect change in purpose...
 *
 * To install:
 *      require_once( "$IP/extensions/DonationInterface/payflowpro_gateway/extras/conversion_log/conversion_log.php"
 */

if ( !defined( 'MEDIAWIKI' ) ) { 
        die( "This file is part of the Conversion Log for PayflowPro Gateway extension. It is not a valid entry point.\n");
}

$wgExtensionCredits['validextensionclass'][] = array(
        'name' => 'conversion log',
        'author' =>'Arthur Richards', 
        'url' => '', 
        'description' => "This extension handles logging for Payflow Gateway extension 'extras'"
);

$dir = dirname( __FILE__ ) . "/";
$wgAutoloadClasses['PayflowProGateway_Extras_ConversionLog'] = $dir . "conversion_log.body.php";

// Sets the 'conversion log' as logger for post-processing
$wgHooks["PayflowGatewayPostProcess"][] = array( "PayflowProGateway_Extras_ConversionLog::onPostProcess" );
