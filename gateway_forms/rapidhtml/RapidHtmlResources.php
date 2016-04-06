<?php
/**
 * For defining RapidHtml ResourceLoader resourcses
 */

$wgDonationInterfaceRapidHtmlRemoteExtPath = 'DonationInterface/gateway_forms/rapidhtml';
$wgGlobalCollectRapidHtmlRemoteExtPath = 'DonationInterface/globalcollect_gateway/forms';
$wgAdyenRapidHtmlRemoteExtPath = 'DonationInterface/adyen_gateway/forms';
$wgPaypalRapidHtmlRemoteExtPath = 'DonationInterface/paypal_gateway/forms';
$wgWorldpayRapidHtmlRemoteExtPath = 'DonationInterface/worldpay_gateway/forms';

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
	'localBasePath' => __DIR__,
	'remoteExtPath' => $wgDonationInterfaceRapidHtmlRemoteExtPath,
);
$wgResourceModules[ 'di.form.rapidhtml.webitects.ie6' ] = array(
	'styles' => 'css/webitects.ie6.css',
	'scripts' => '',
	'dependencies' => 'di.form.rapidhtml.webitects',
	'localBasePath' => __DIR__,
	'remoteExtPath' => $wgDonationInterfaceRapidHtmlRemoteExtPath,
);
$wgResourceModules[ 'di.form.rapidhtml.webitects.2nd' ] = array(
	'styles' => 'css/webitects2nd.css',
	'dependencies' => 'di.form.rapidhtml.webitects',
	'localBasePath' => __DIR__,
	'remoteExtPath' => $wgDonationInterfaceRapidHtmlRemoteExtPath,
);

// GlobalCollect

$wgResourceModules[ 'gc.form.rapidhtml.webitects' ] = array(
	'styles' => '',
	'scripts' => array(
		'js/webitects.js',
		#'js/webitects.accordian.js',
	),
	'dependencies' => 'di.form.rapidhtml.webitects',
	'localBasePath' => __DIR__ . '/../../globalcollect_gateway/forms',
	'remoteExtPath' => $wgGlobalCollectRapidHtmlRemoteExtPath,
);
$wgResourceModules[ 'gc.form.rapidhtml.webitects.ie6' ] = array(
	'dependencies' => array(
		'di.form.rapidhtml.webitects.ie6',
		'gc.form.rapidhtml.webitects'
	),
	'localBasePath' => __DIR__ . '/../../globalcollect_gateway/forms',
	'remoteExtPath' => $wgGlobalCollectRapidHtmlRemoteExtPath,
);
$wgResourceModules[ 'gc.form.rapidhtml.webitects.1st' ] = array(
	'styles' => '',
	'scripts' => 'js/webitects_2_3step.js',
	'dependencies' => array(
		'gc.form.rapidhtml.webitects',
	),
	'localBasePath' => __DIR__ . '/../../globalcollect_gateway/forms',
	'remoteExtPath' => $wgGlobalCollectRapidHtmlRemoteExtPath,
);
$wgResourceModules[ 'gc.form.rapidhtml.webitects.2nd' ] = array(
	'styles' => '',
	'scripts' => 'js/webitects2nd.js',
	'dependencies' => array(
		'gc.form.rapidhtml.webitects',
		'di.form.rapidhtml.webitects.2nd'
	),
	'localBasePath' => __DIR__ . '/../../globalcollect_gateway/forms',
	'remoteExtPath' => $wgGlobalCollectRapidHtmlRemoteExtPath,
);
$wgResourceModules[ 'gc.form.rapidhtml.webitects.2nd.US' ] = array(
	'styles' => '',
	'scripts' => 'js/webitects2nd-US.js',
	'dependencies' => array(
		'gc.form.rapidhtml.webitects',
		'di.form.rapidhtml.webitects.2nd'
	),
	'localBasePath' => __DIR__ . '/../../globalcollect_gateway/forms',
	'remoteExtPath' => $wgGlobalCollectRapidHtmlRemoteExtPath,
);
$wgResourceModules[ 'gc.form.rapidhtml.webitects.bt' ] = array(
	'styles' => '',
//	'scripts' => 'js/webitects.bt.js',
	'dependencies' => array(
		'gc.form.rapidhtml.webitects.2nd',
		#'gc.form.core.validate'
	),
	'localBasePath' => __DIR__ . '/../../globalcollect_gateway/forms',
	'remoteExtPath' => $wgGlobalCollectRapidHtmlRemoteExtPath,
);
$wgResourceModules[ 'gc.form.rapidhtml.webitects.dd' ] = array(
	'styles' => '',
	'scripts' => 'js/webitects.bt.js',
	'dependencies' => 'gc.form.rapidhtml.webitects.2nd',
	'localBasePath' => __DIR__ . '/../../globalcollect_gateway/forms',
	'remoteExtPath' => $wgGlobalCollectRapidHtmlRemoteExtPath,
);

$wgResourceModules[ 'gc.normalinterface' ] = array(
	'scripts' => array(
		'js/gc.interface.js'
	),
	'localBasePath' => __DIR__ . '/../../globalcollect_gateway/forms',
	'remoteExtPath' => $wgGlobalCollectRapidHtmlRemoteExtPath
);

$wgResourceModules[ 'gc.form.rapidhtml.cc' ] = array(
	'styles' => 'css/gc.css',
	'scripts' => array(
        'js/gc.js',
        'js/gc.cc.js'
    ),
	'dependencies' => array(
		'di.form.core.validate',
		'mediawiki.Uri'
	),
	'localBasePath' => __DIR__ . '/../../globalcollect_gateway/forms',
	'remoteExtPath' => $wgGlobalCollectRapidHtmlRemoteExtPath
);

// NOTE deployment branch only, ordinarily there is a testing conditional here.
$wgResourceModules[ 'gc.form.rapidhtml.cc' ]['dependencies'][] = 'gc.normalinterface';

$wgResourceModules[ 'gc.form.rapidhtml.dd' ] = array(
	'styles' => 'css/gc.css',
	'scripts' => array(
		'js/gc.dd.js'
	),
	'dependencies' => array( 'di.form.core.validate' ),
	'localBasePath' => __DIR__ . '/../../globalcollect_gateway/forms',
	'remoteExtPath' => $wgGlobalCollectRapidHtmlRemoteExtPath,
);
$wgResourceModules[ 'gc.form.rapidhtml.ew' ] = array(
	'styles' => 'css/gc.css',
	'scripts' => array(
        'js/gc.ew.js'
    ),
	'dependencies' => array( 'di.form.core.validate' ),
	'localBasePath' => __DIR__ . '/../../globalcollect_gateway/forms',
	'remoteExtPath' => $wgGlobalCollectRapidHtmlRemoteExtPath,
);
$wgResourceModules[ 'gc.form.rapidhtml.boletos' ] = array(
	'styles' => 'css/gc.css',
	'scripts' => array(
        'js/gc.boletos.js'
    ),
	'dependencies' => array( 'di.form.core.validate' ),
	'localBasePath' => __DIR__ . '/../../globalcollect_gateway/forms',
	'remoteExtPath' => $wgGlobalCollectRapidHtmlRemoteExtPath,
);
$wgResourceModules[ 'gc.form.rapidhtml.rtbt' ] = array(
	'styles' => 'css/gc.css',
	'dependencies' => array(
		'di.form.core.validate',
		'mediawiki.Uri',
		'gc.form.rapidhtml.webitects.bt',
	),
	'localBasePath' => __DIR__ . '/../../globalcollect_gateway/forms',
	'remoteExtPath' => $wgGlobalCollectRapidHtmlRemoteExtPath,
);
$wgResourceModules[ 'gc.iframe' ] = array(
	'styles' => 'css/iframe.css',
	'localBasePath' => __DIR__ . '/../../globalcollect_gateway/forms',
	'remoteExtPath' => $wgGlobalCollectRapidHtmlRemoteExtPath,
);

/*************************************************************
 *************************************************************
 *************************************************************/

$wgResourceModules[ 'adyen.js' ] = array(
	'styles' => 'css/adyen.css',
	'scripts' => 'js/adyen.js',
	'localBasePath' => __DIR__ . '/../../adyen_gateway/forms',
	'remoteExtPath' => $wgAdyenRapidHtmlRemoteExtPath,
);

$wgResourceModules['ext.donationinterface.worldpay.styles'] = array (
	'styles' => array('css/worldpay.css', 'css/bootstrap.css'),
	'localBasePath' => __DIR__ . '/../../worldpay_gateway/forms',
	'remoteExtPath' => $wgWorldpayRapidHtmlRemoteExtPath,
	'position' => 'top',
);

$wgResourceModules['ext.donationinterface.worldpay.code'] = array (
	'scripts' => 'js/worldpay.js',
	'dependencies' => array ( 'di.form.core.validate', 'jquery.payment' ),
	'localBasePath' => __DIR__ . '/../../worldpay_gateway/forms',
	'remoteExtPath' => $wgWorldpayRapidHtmlRemoteExtPath,
);

$wgResourceModules[ 'basicDonationForm' ] = array(
	'scripts' => 'js/basicForm.js',
	'localBasePath' => __DIR__,
	'remoteExtPath' => $wgDonationInterfaceRapidHtmlRemoteExtPath,
);
