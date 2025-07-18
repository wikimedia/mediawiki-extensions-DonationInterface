<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['target_php_version'] = '8.2';

// Ignored to allow upgrading Phan, to be fixed later.
$cfg['suppress_issue_types'][] = 'MediaWikiNoIssetIfDefined';
$cfg['suppress_issue_types'][] = 'PhanThrowTypeAbsent';

$cfg['file_list'] = array_merge(
	$cfg['file_list'],
	[
		'DonationInterface.class.php',
	]
);

$cfg['directory_list'] = array_merge(
	$cfg['directory_list'],
	[
		'../../extensions/cldr',
		'../../extensions/FundraisingEmailUnsubscribe',
		'adyen_gateway/',
		'amazon_gateway/',
		'astropay_gateway/',
		'braintree_gateway/',
		'dlocal_gateway/',
		'gravy_gateway/',
		'email_forms/',
		'extras/',
		'form_variants/',
		'gateway_common/',
		'gateway_forms/',
		'ingenico_gateway/',
		'modules/',
		'paypal_ec_gateway/',
		'special/',
		'vendor/addshore/psr-6-mediawiki-bagostuff-adapter/',
		'vendor/amzn/login-and-pay-with-amazon-sdk-php/',
		'vendor/maxmind/minfraud/',
		'vendor/relisten/forceutf8/',
		'vendor/whichbrowser/parser/',
		'vendor/wikimedia/smash-pig/',
	]
);

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'],
	[
		'../../extensions/cldr',
		'../../extensions/FundraisingEmailUnsubscribe',
		'vendor/addshore/psr-6-mediawiki-bagostuff-adapter/',
		'vendor/amzn/login-and-pay-with-amazon-sdk-php/',
		'vendor/maxmind/minfraud/',
		'vendor/relisten/forceutf8/',
		'vendor/whichbrowser/parser/',
		'vendor/wikimedia/smash-pig/',
	]
);

return $cfg;
