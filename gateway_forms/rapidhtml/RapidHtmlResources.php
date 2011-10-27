<?php
/**
 * For defining RapidHtml ResourceLoader resourcses
 *
 * This file is included in DonationInterfa/payflowpro_gateway.php
 */

$wgPayflowRapidHtmlRemoteExtPath = 'DonationInterface/gateway_forms/rapidhtml';
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
$wgResourceModules[ 'pfp.form.rapidhtml.webitects' ] = array(
	'styles' => array(
		'css/lp1.css',
		'css/Webitects.css',
	),
	'scripts' => array(
		'js/jquery.ezpz_hint.js',
	),
	'dependencies' => array(
		'jquery.ui.accordion',
		'pfp.form.core.validate'
	),
	'localBasePath' => dirname( __FILE__ ),
	'remoteExtPath' => $wgPayflowRapidHtmlRemoteExtPath,
);
$wgResourceModules[ 'gc.form.rapidhtml.webitects_2_3step' ] = array(
	'scripts' => array(
		'js/webitects_2_3step.js',
	),
	'dependencies' => array(
		'pfp.form.rapidhtml.webitects'
	),
	'localBasePath' => dirname( __FILE__ ).'/../../globalcollect_gateway/forms',
	'remoteExtPath' => $wgGlobalCollectRapidHtmlRemoteExtPath,
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
