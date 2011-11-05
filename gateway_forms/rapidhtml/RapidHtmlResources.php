<?php
/**
 * For defining RapidHtml ResourceLoader resourcses
 *
 * This file is included in DonationInterface/payflowpro_gateway.php
 */

$wgDonationInterfaceRapidHtmlRemoteExtPath = 'DonationInterface/gateway_forms/rapidhtml';
$wgPayflowRapidHtmlRemoteExtPath = 'DonationInterface/payflowpro_gateway/rapidhtml';
$wgGlobalCollectRapidHtmlRemoteExtPath = 'DonationInterface/globalcollect_gateway/forms';

/**
 * LIGHTBOX
 */
// RapidHtml lightbox form resources
$wgResourceModules[ 'pfp.form.rapidhtml.lightbox.js' ] = array(
	'scripts' => array(
		'js/lightbox1.js',
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
	'localBasePath' => dirname( __FILE__ ),
	'remoteExtPath' => $wgPayflowRapidHtmlRemoteExtPath,
	'position' => 'top',
);

// RapidHtml lightbox form css resources (these are separate from the js
// resources for a good reason but I forget what - I believe to facilitate
// ensuring proper load order?
$wgResourceModules[ 'pfp.form.rapidhtml.lightbox.css' ] = array(
	'styles' => array(
		'css/lightbox1.css',	
	),
	'localBasePath' => dirname( __FILE__ ),
	'remoteExtPath' => $wgPayflowRapidHtmlRemoteExtPath,
	'position' => 'top',
);

/**
 * webitects
 */
$wgResourceModules[ 'di.form.rapidhtml.webitects' ] = array(
	'styles' => array(
		'css/lp1.css',
		'css/webitects.css',
	),
	'scripts' => '',
	'dependencies' => 'jquery.ui.accordion',
	'localBasePath' => dirname( __FILE__ ),
	'remoteExtPath' => $wgDonationInterfaceRapidHtmlRemoteExtPath,
);
$wgResourceModules[ 'di.form.rapidhtml.webitects.ie6' ] = array(
	'styles' => 'css/webitects.ie6.css',
	'scripts' => '',
	'dependencies' => 'di.form.rapidhtml.webitects',
	'localBasePath' => dirname( __FILE__ ),
	'remoteExtPath' => $wgDonationInterfaceRapidHtmlRemoteExtPath,
);
//$wgResourceModules[ 'di.form.rapidhtml.webitects.2nd' ] = array(
//	'styles' => 'css/webitects2nd.css',
//	'dependencies' => 'di.form.rapidhtml.webitects',
//	'localBasePath' => dirname( __FILE__ ),
//	'remoteExtPath' => $wgDonationInterfaceRapidHtmlRemoteExtPath,
//);

// GlobalCollect
$wgResourceModules[ 'gc.form.rapidhtml.webitects' ] = array(
	'styles' => '', //'css/webitects_2_3step.css',
	'scripts' => 'js/webitects_2_3step.js',
	'dependencies' => 'di.form.rapidhtml.webitects',
	'localBasePath' => dirname( __FILE__ ).'/../../globalcollect_gateway/forms',
	'remoteExtPath' => $wgGlobalCollectRapidHtmlRemoteExtPath,
);
$wgResourceModules[ 'gc.form.rapidhtml.webitects.ie6' ] = array(
	'dependencies' => array(
		'di.form.rapidhtml.webitects.ie6',
		'gc.form.rapidhtml.webitects'
	),
	'localBasePath' => dirname( __FILE__ ).'/../../globalcollect_gateway/forms',
	'remoteExtPath' => $wgGlobalCollectRapidHtmlRemoteExtPath,
);
//$wgResourceModules[ 'gc.form.rapidhtml.webitects.2nd' ] = array(
//	'styles' => '',
//	'dependencies' => array(
//		'gc.form.rapidhtml.webitects',
//		'di.form.rapidhtml.webitects.2nd'
//	),
//	'localBasePath' => dirname( __FILE__ ).'/../../globalcollect_gateway/forms',
//	'remoteExtPath' => $wgGlobalCollectRapidHtmlRemoteExtPath,
//);

// PayflowPro
$wgResourceModules[ 'pfp.form.rapidhtml.webitects' ] = array(
	'styles' => '',
	'scripts' => 'js/webitects_2_3step.js',
	'dependencies' => array(
		'di.form.rapidhtml.webitects',
		'pfp.form.core.validate'
	),
	'localBasePath' => dirname( __FILE__ ).'/../../payflowpro_gateway/forms',
	'remoteExtPath' => $wgPayflowRapidHtmlRemoteExtPath,
);
$wgResourceModules[ 'pfp.form.rapidhtml.webitects_2step' ] = array(
	'styles' => '',
	'scripts' => 'js/webitects_2_2step.js',
	'dependencies' => array(
		'di.form.rapidhtml.webitects',
		'pfp.form.core.validate'
	),
	'localBasePath' => dirname( __FILE__ ).'/../../payflowpro_gateway/forms',
	'remoteExtPath' => $wgPayflowRapidHtmlRemoteExtPath,
);
$wgResourceModules[ 'pfp.form.rapidhtml.webitects.ie6' ] = array(
	'dependencies' => array(
		'di.form.rapidhtml.webitects.ie6',
		'pfp.form.rapidhtml.webitects',	
	),
	'localBasePath' => dirname( __FILE__ ).'/../../payflowpro_gateway/forms',
	'remoteExtPath' => $wgPayflowRapidHtmlRemoteExtPath,
);

/**
 * globalcollect_test
 */
$wgResourceModules[ 'pfp.form.rapidhtml.globalcollect_test' ] = array(
	'dependencies' => 'pfp.form.TwoStepTwoColumnLetter3',
	'localBasePath' => dirname( __FILE__ ),
	'remoteExtPath' => $wgPayflowRapidHtmlRemoteExtPath,
);
$wgResourceModules[ 'pfp.form.rapidhtml.globalcollect_test_2' ] = array(
	'scripts' => 'js/globalcollect_test_2.js',
	'dependencies' => 'pfp.form.rapidhtml.globalcollect_test',
	'localBasePath' => dirname( __FILE__ ),
	'remoteExtPath' => $wgPayflowRapidHtmlRemoteExtPath,
);
