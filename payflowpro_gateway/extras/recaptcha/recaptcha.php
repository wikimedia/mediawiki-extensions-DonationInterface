<?php
/**
 * Extra to expose a recaptcha for 'challenged' transactions
 *
 * To install:
 *      require_once( "$IP/extensions/DonationInterface/payflowpro_gateway/extras/recaptcha/recaptcha.php"
 */

if ( !defined( 'MEDIAWIKI' ) ) { 
        die( "This file is part of the ReCaptcha for PayflowPro Gateway extension. It is not a valid entry point.\n");
}

$wgExtensionCredits['payflowgateway_extras_recaptcha'][] = array(
        'name' => 'reCaptcha',
        'author' =>'Arthur Richards', 
        'url' => '', 
        'description' => "This extension exposes a reCpathca for 'challenged' transactions in the Payflowpro Gateway"
);

/**
 * Public and Private reCaptcha keys
 *
 * These can be obtained at:
 *   http://www.google.com/recaptcha/whyrecaptcha
 */
$wgPayflowRecaptchaPublicKey = '';
$wgPayflowRecaptchaPrivateKey = '';

$dir = dirname( __FILE__ ) . "/";
$wgAutoloadClasses['PayflowProGateway_Extras_ReCaptcha'] = $dir . "recaptcha.body.php";

// Set reCpatcha as plugin for 'challenge' action
$wgHooks["PayflowGatewayChallenge"][] = array( "PayflowProGateway_Extras_ReCaptcha::onChallenge");
