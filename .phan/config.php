<?php
$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';
// T191668 and T191666
$cfg['suppress_issue_types'][] = 'PhanParamTooMany';
$cfg['directory_list'] = array_merge(
	$cfg['directory_list'],
	[
		'../../extensions/cldr',
		'../../extensions/FundraisingEmailUnsubscribe',
		'./special',
		'./gateway_common',
		'./vendor/wikimedia/smash-pig',
	]
);
$cfg['file_list'] = array_merge(
	$cfg['file_list'],
	[
		'./DonationInterface.class.php',
	]
);
$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'],
	[
		'./maintenance',
		'../../extensions/cldr',
		'../../extensions/FundraisingEmailUnsubscribe',
		'./special',
		'./gateway_common',
		'./vendor/wikimedia/smash-pig',
	]
);
return $cfg;
