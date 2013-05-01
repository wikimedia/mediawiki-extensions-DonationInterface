<?php

$wgResourceModules[ 'ext.donationInterface.mixins.DonationForm' ] = array(
	'localBasePath' => __DIR__ . '/DonationForm',
	'remoteExtPath' => 'DonationInterface/mixins/DonationForm',
	'scripts' => array(
		'PaymentMethods.js',
		'FormController.js',
	),
	'position' => 'top',
	'targets' => array( 'desktop' ),
);

$wgNoticeMixins['DonationForm'] = array(
	'localBasePath' => __DIR__ . "/DonationForm",

	'php' => "DonationForm.php",
	'resourceLoader' => "ext.donationInterface.mixins.DonationForm",
);
