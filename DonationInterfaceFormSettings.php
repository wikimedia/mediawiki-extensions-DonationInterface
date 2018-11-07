<?php
/**
 * FIXME: These determine which gateways FormChooser picks for different
 * parameters. The chooser should instead query enabled gateway capabilities
 * and simply pass along any ffname from the banner to allow A/B testing.
 */
global $wgDonationInterfaceAllowedHtmlForms;
/**
 * Temp var for terseness, unset at end of file
 */
$forms_whitelist = array();

/*
 * Amazon
 */
$forms_whitelist['amazon'] = array(
	'gateway' => 'amazon',
	'payment_methods' => array( 'amazon' => 'ALL' ),
);

$forms_whitelist['amazon-recurring'] = array(
	'gateway' => 'amazon',
	'payment_methods' => array( 'amazon' => 'ALL' ),
	'recurring',
);

/*******************************
 * RealTime Banking - Two Step *
 *******************************/

$forms_whitelist['rtbt-ideal'] = array(
	'gateway' => 'globalcollect',
	'payment_methods' => array( 'rtbt' => 'rtbt_ideal' ),
	'countries' => array( '+' => 'NL' ),
	'currencies' => array( '+' => 'EUR' ),
);

/********
 * BPAY *
 ********/

$forms_whitelist['obt-bpay'] = array(
	'gateway' => 'globalcollect',
	'countries' => array( '+' => 'AU' ),
	'currencies' => array( '+' => 'AUD' ),
	'payment_methods' => array( 'obt' => 'bpay' )
);

/**********************
 * Credit Card - Misc *
 **********************/

$forms_whitelist['cc-vmad'] = array(
	'gateway' => 'ingenico',
	'payment_methods' => array( 'cc' => array( 'visa', 'mc', 'amex', 'discover' ) ),
	'countries' => array(
		'+' => array( 'US', ),
	),
	'currencies' => array(
		'+' => array(
			'AED', 'ALL', 'ANG', 'ARS', 'AUD', 'BBD', 'BDT', 'BGN', 'BHD',
			'BMD', 'BOB', 'BRL', 'BZD', 'CAD', 'CHF', 'CNY', 'COP', 'CRC',
			'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'EUR', 'FJD', 'GBP', 'GTQ',
			'HKD', 'HNL', 'HRK', 'HUF', 'IDR', 'ILS', 'INR', 'JMD', 'JOD',
			'JPY', 'KES', 'KRW', 'KWD', 'KZT', 'LBP', 'LKR', 'MAD', 'MKD',
			'MVR', 'MXN', 'MYR', 'NIO', 'NOK', 'NZD', 'OMR', 'PAB', 'PEN',
			'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RUB', 'SAR', 'SCR',
			'SEK', 'SGD', 'THB', 'TND', 'TRY', 'TTD', 'TWD', 'UAH', 'USD',
			'UYU', 'VEF', 'VND', 'VUV', 'XCD', 'ZAR',
		)
	),
);

$forms_whitelist['cc-vjma'] = array(
	'gateway' => 'ingenico',
	'payment_methods' => array( 'cc' => array( 'visa', 'jcb', 'mc', 'amex' ) ),
	'countries' => array(
		'+' => array( 'JP', ),
	),
	'currencies' => array(
		'+' => array(
			'AED', 'ALL', 'ANG', 'ARS', 'AUD', 'BBD', 'BDT', 'BGN', 'BHD',
			'BMD', 'BOB', 'BRL', 'BZD', 'CAD', 'CHF', 'CNY', 'COP', 'CRC',
			'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'EUR', 'FJD', 'GBP', 'GTQ',
			'HKD', 'HNL', 'HRK', 'HUF', 'IDR', 'ILS', 'INR', 'JMD', 'JOD',
			'JPY', 'KES', 'KRW', 'KWD', 'KZT', 'LBP', 'LKR', 'MAD', 'MKD',
			'MVR', 'MXN', 'MYR', 'NIO', 'NOK', 'NZD', 'OMR', 'PAB', 'PEN',
			'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RUB', 'SAR', 'SCR',
			'SEK', 'SGD', 'THB', 'TND', 'TRY', 'TTD', 'TWD', 'UAH', 'USD',
			'UYU', 'VEF', 'VND', 'VUV', 'XCD', 'ZAR',
		)
	),
	'selection_weight' => 10,
);

$forms_whitelist['cc-jvma'] = array(
	'gateway' => 'ingenico',
	'payment_methods' => array( 'cc' => array( 'jcb', 'visa', 'mc', 'amex' ) ),
	'countries' => array(
		'+' => array( 'JP', ),
	),
	'currencies' => array(
		'+' => array(
			'AED', 'ALL', 'ANG', 'ARS', 'AUD', 'BBD', 'BDT', 'BGN', 'BHD',
			'BMD', 'BOB', 'BRL', 'BZD', 'CAD', 'CHF', 'CNY', 'COP', 'CRC',
			'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'EUR', 'FJD', 'GBP', 'GTQ',
			'HKD', 'HNL', 'HRK', 'HUF', 'IDR', 'ILS', 'INR', 'JMD', 'JOD',
			'JPY', 'KES', 'KRW', 'KWD', 'KZT', 'LBP', 'LKR', 'MAD', 'MKD',
			'MVR', 'MXN', 'MYR', 'NIO', 'NOK', 'NZD', 'OMR', 'PAB', 'PEN',
			'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RUB', 'SAR', 'SCR',
			'SEK', 'SGD', 'THB', 'TND', 'TRY', 'TTD', 'TWD', 'UAH', 'USD',
			'UYU', 'VEF', 'VND', 'VUV', 'XCD', 'ZAR',
		)
	),
	'selection_weight' => 0,
);

$forms_whitelist['cc-vmaj'] = array(
	'gateway' => 'ingenico',
	'payment_methods' => array( 'cc' => array( 'visa', 'mc', 'amex', 'jcb' ) ),
	'countries' => array(
		'+' => array( 'AD', 'AT', 'AU', 'BE', 'BH', 'DE', 'EC', 'ES', 'FI', 'GB',
					  'GF', 'GR', 'HK', 'IE', 'IT', 'KR', 'LU', 'MY', 'NL', 'PR', 'PT',
					  'SG', 'SI', 'SK', 'TH', 'TW', ),
	),
	'currencies' => array(
		'+' => array(
			'AED', 'ALL', 'ANG', 'ARS', 'AUD', 'BBD', 'BDT', 'BGN', 'BHD',
			'BMD', 'BOB', 'BRL', 'BZD', 'CAD', 'CHF', 'CNY', 'COP', 'CRC',
			'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'EUR', 'FJD', 'GBP', 'GTQ',
			'HKD', 'HNL', 'HRK', 'HUF', 'IDR', 'ILS', 'INR', 'JMD', 'JOD',
			'JPY', 'KES', 'KRW', 'KWD', 'KZT', 'LBP', 'LKR', 'MAD', 'MKD',
			'MVR', 'MXN', 'MYR', 'NIO', 'NOK', 'NZD', 'OMR', 'PAB', 'PEN',
			'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RUB', 'SAR', 'SCR',
			'SEK', 'SGD', 'THB', 'TND', 'TRY', 'TTD', 'TWD', 'UAH', 'USD',
			'UYU', 'VEF', 'VND', 'VUV', 'XCD', 'ZAR',
		)
	),
);

$forms_whitelist['cc-vmd'] = array(
	'gateway' => 'ingenico',
	'payment_methods' => array( 'cc' => array( 'visa', 'mc', 'discover' ) ),
	'countries' => array(
		// Array merge with cc-vmad as fallback in case 'a' goes down
		'+' => array_merge(
			$forms_whitelist['cc-vmad']['countries']['+'],
			array() // as of right now, nothing specific here
		),
	),
	'currencies' => array(
		'+' => array(
			'AED', 'ALL', 'ANG', 'ARS', 'AUD', 'BBD', 'BDT', 'BGN', 'BHD',
			'BMD', 'BOB', 'BRL', 'BZD', 'CAD', 'CHF', 'CNY', 'COP', 'CRC',
			'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'EUR', 'FJD', 'GBP', 'GTQ',
			'HKD', 'HNL', 'HRK', 'HUF', 'IDR', 'ILS', 'INR', 'JMD', 'JOD',
			'JPY', 'KES', 'KRW', 'KWD', 'KZT', 'LBP', 'LKR', 'MAD', 'MKD',
			'MVR', 'MXN', 'MYR', 'NIO', 'NOK', 'NZD', 'OMR', 'PAB', 'PEN',
			'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RUB', 'SAR', 'SCR',
			'SEK', 'SGD', 'THB', 'TND', 'TRY', 'TTD', 'TWD', 'UAH', 'USD',
			'UYU', 'VEF', 'VND', 'VUV', 'XCD', 'ZAR',
		)
	),
);

$forms_whitelist['cc-vmj'] = array(
	'gateway' => 'ingenico',
	'payment_methods' => array( 'cc' => array( 'visa', 'mc', 'jcb' ) ),
	'countries' => array(
		// Array merge with cc-vmaj as fallback in case 'a' goes down
		'+' => array_merge(
			$forms_whitelist['cc-vmaj']['countries']['+'],
			array( 'BR', 'ID', 'PH', )
		),
	),
	'currencies' => array(
		'+' => array(
			'AED', 'ALL', 'ANG', 'ARS', 'AUD', 'BBD', 'BDT', 'BGN', 'BHD',
			'BMD', 'BOB', 'BRL', 'BZD', 'CAD', 'CHF', 'CNY', 'COP', 'CRC',
			'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'EUR', 'FJD', 'GBP', 'GTQ',
			'HKD', 'HNL', 'HRK', 'HUF', 'IDR', 'ILS', 'INR', 'JMD', 'JOD',
			'JPY', 'KES', 'KRW', 'KWD', 'KZT', 'LBP', 'LKR', 'MAD', 'MKD',
			'MVR', 'MXN', 'MYR', 'NIO', 'NOK', 'NZD', 'OMR', 'PAB', 'PEN',
			'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RUB', 'SAR', 'SCR',
			'SEK', 'SGD', 'THB', 'TND', 'TRY', 'TTD', 'TWD', 'UAH', 'USD',
			'UYU', 'VEF', 'VND', 'VUV', 'XCD', 'ZAR',
		)
	),
);

$forms_whitelist['cc-vma'] = array(
	'gateway' => 'ingenico',
	'payment_methods' => array( 'cc' => array( 'visa', 'mc', 'amex' ) ),
	'countries' => array(
		// Array merge with cc-vmaj as fallback in case 'j' goes down
		// Array merge with cc-vmad as fallback in case 'd' goes down
		'+' => array_merge(
			$forms_whitelist['cc-vmaj']['countries']['+'],
			$forms_whitelist['cc-vmad']['countries']['+'],
			array( 'AE', 'AL', 'AN', 'AR', 'BG', 'CA', 'CH', 'CN', 'CR', 'CY', 'CZ', 'DK',
				 'DZ', 'EE', 'EG', 'JO', 'KE', 'HR', 'HU', 'IL', 'KW', 'KZ', 'LB', 'LI',
				 'LK', 'LT', 'LV', 'MA', 'MT', 'NO', 'NZ', 'OM', 'PK', 'PL', 'QA', 'RO',
				 'RU', 'SA', 'SE', 'TN', 'TR', 'UA', )
		)
	),
	'currencies' => array(
		'+' => array(
			'AED', 'ALL', 'ANG', 'ARS', 'AUD', 'BBD', 'BDT', 'BGN', 'BHD',
			'BMD', 'BOB', 'BRL', 'BZD', 'CAD', 'CHF', 'CNY', 'COP', 'CRC',
			'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'EUR', 'FJD', 'GBP', 'GTQ',
			'HKD', 'HNL', 'HRK', 'HUF', 'IDR', 'ILS', 'INR', 'JMD', 'JOD',
			'JPY', 'KES', 'KRW', 'KWD', 'KZT', 'LBP', 'LKR', 'MAD', 'MKD',
			'MVR', 'MXN', 'MYR', 'NIO', 'NOK', 'NZD', 'OMR', 'PAB', 'PEN',
			'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RUB', 'SAR', 'SCR',
			'SEK', 'SGD', 'THB', 'TND', 'TRY', 'TTD', 'TWD', 'UAH', 'USD',
			'UYU', 'VEF', 'VND', 'VUV', 'XCD', 'ZAR',
		)
	),
);

$forms_whitelist['cc-vm'] = array(
	'gateway' => 'ingenico',
	'payment_methods' => array( 'cc' => array( 'visa', 'mc' ) ),
	'countries' => array(
		// Array merge with cc-vmj as fallback in case 'j' goes down
		// Array merge with cc-vmd as fallback in case 'd' goes down
		'+' => array_merge(
			$forms_whitelist['cc-vmj']['countries']['+'],
			$forms_whitelist['cc-vmd']['countries']['+'],
			array( 'AG', 'AM', 'AO', 'AS', 'AW', 'AZ', 'BA', 'BB', 'BD', 'BF', 'BI', 'BJ',
				 'BM', 'BN', 'BO', 'BS', 'BW', 'BY', 'BZ', 'CF', 'CG', 'CI', 'CK', 'CL',
				 'CM', 'CO', 'CV', 'DJ', 'DM', 'DO', 'ER', 'ET', 'FJ', 'FM', 'FO', 'GA',
				 'GD', 'GE', 'GL', 'GM', 'GT', 'GU', 'HN', 'IN', 'IQ', 'IS', 'JM', 'KH',
				 'KI', 'KM', 'KN', 'KP', 'LC', 'LR', 'LY', 'MC', 'MD', 'ME', 'MG', 'MH',
				 'MK', 'ML', 'MO', 'MP', 'MR', 'MV', 'MW', 'MX', 'MZ', 'NA', 'NE', 'NG',
				 'NI', 'NR', 'PA', 'PE', 'PG', 'PS', 'PW', 'PY', 'RE', 'RS', 'SB', 'SC',
				 'SD', 'SM', 'SN', 'SR', 'TD', 'TG', 'TM', 'TO', 'TP', 'TT', 'TZ', 'UY',
				 'UZ', 'VA', 'VC', 'VE', 'VI', 'VU', 'YE', 'ZA', 'ZM', 'ZW', )
		),
	),
	'currencies' => array(
		'+' => array(
			'AED', 'ALL', 'ANG', 'ARS', 'AUD', 'BBD', 'BDT', 'BGN', 'BHD',
			'BMD', 'BOB', 'BRL', 'BZD', 'CAD', 'CHF', 'CNY', 'COP', 'CRC',
			'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'EUR', 'FJD', 'GBP', 'GTQ',
			'HKD', 'HNL', 'HRK', 'HUF', 'IDR', 'ILS', 'INR', 'JMD', 'JOD',
			'JPY', 'KES', 'KRW', 'KWD', 'KZT', 'LBP', 'LKR', 'MAD', 'MKD',
			'MVR', 'MXN', 'MYR', 'NIO', 'NOK', 'NZD', 'OMR', 'PAB', 'PEN',
			'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RUB', 'SAR', 'SCR',
			'SEK', 'SGD', 'THB', 'TND', 'TRY', 'TTD', 'TWD', 'UAH', 'USD',
			'UYU', 'VEF', 'VND', 'VUV', 'XCD', 'ZAR',
		)
	),
);

$forms_whitelist['cc-a'] = array(
	'gateway' => 'ingenico',
	'payment_methods' => array( 'cc' => array( 'amex' ) ),
	'countries' => array(
		'+' => array_merge(
			$forms_whitelist['cc-vma']['countries']['+'],
			array() // as of right now, nothing specific here
		)
	),
	'currencies' => array(
		'+' => array(
			'AED', 'ALL', 'ANG', 'ARS', 'AUD', 'BBD', 'BDT', 'BGN', 'BHD',
			'BMD', 'BOB', 'BRL', 'BZD', 'CAD', 'CHF', 'CNY', 'COP', 'CRC',
			'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'EUR', 'FJD', 'GBP', 'GTQ',
			'HKD', 'HNL', 'HRK', 'HUF', 'IDR', 'ILS', 'INR', 'JMD', 'JOD',
			'JPY', 'KES', 'KRW', 'KWD', 'KZT', 'LBP', 'LKR', 'MAD', 'MKD',
			'MVR', 'MXN', 'MYR', 'NIO', 'NOK', 'NZD', 'OMR', 'PAB', 'PEN',
			'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RUB', 'SAR', 'SCR',
			'SEK', 'SGD', 'THB', 'TND', 'TRY', 'TTD', 'TWD', 'UAH', 'USD',
			'UYU', 'VEF', 'VND', 'VUV', 'XCD', 'ZAR',
		)
	),
);

$forms_whitelist['cc'] = array(
	'gateway' => 'ingenico',
	'payment_methods' => array( 'cc' => 'ALL' ),
	'countries' => array( '-' => 'VN' ),
	'currencies' => array(
		'+' => array(
			'AED', 'ALL', 'ANG', 'ARS', 'AUD', 'BBD', 'BDT', 'BGN', 'BHD',
			'BMD', 'BOB', 'BRL', 'BZD', 'CAD', 'CHF', 'CNY', 'COP', 'CRC',
			'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'EUR', 'FJD', 'GBP', 'GTQ',
			'HKD', 'HNL', 'HRK', 'HUF', 'IDR', 'ILS', 'INR', 'JMD', 'JOD',
			'JPY', 'KES', 'KRW', 'KWD', 'KZT', 'LBP', 'LKR', 'MAD', 'MKD',
			'MVR', 'MXN', 'MYR', 'NIO', 'NOK', 'NZD', 'OMR', 'PAB', 'PEN',
			'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RUB', 'SAR', 'SCR',
			'SEK', 'SGD', 'THB', 'TND', 'TRY', 'TTD', 'TWD', 'UAH', 'USD',
			'UYU', 'VEF', 'VND', 'VUV', 'XCD', 'ZAR',
		)
	),
);

/**
 * FIXME: remove as soon as we know this isn't being sent by anyone.
 */
$forms_whitelist['cc-ingenico'] = array(
	'gateway' => 'ingenico',
	'payment_methods' => array( 'cc' => 'ALL' ),
	'currencies' => array(
		'+' => array(
			'AED', 'ALL', 'ANG', 'ARS', 'AUD', 'BBD', 'BDT', 'BGN', 'BHD',
			'BMD', 'BOB', 'BRL', 'BZD', 'CAD', 'CHF', 'CNY', 'COP', 'CRC',
			'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'EUR', 'FJD', 'GBP', 'GTQ',
			'HKD', 'HNL', 'HRK', 'HUF', 'IDR', 'ILS', 'INR', 'JMD', 'JOD',
			'JPY', 'KES', 'KRW', 'KWD', 'KZT', 'LBP', 'LKR', 'MAD', 'MKD',
			'MVR', 'MXN', 'MYR', 'NIO', 'NOK', 'NZD', 'OMR', 'PAB', 'PEN',
			'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RUB', 'SAR', 'SCR',
			'SEK', 'SGD', 'THB', 'TND', 'TRY', 'TTD', 'TWD', 'UAH', 'USD',
			'UYU', 'VEF', 'VND', 'VUV', 'XCD', 'ZAR',
		)
	),
	'selection_weight' => 10,
);

/**
 * Fallback for old Ingenico API
 */
$forms_whitelist['cc-globalcollect'] = array(
	'gateway' => 'globalcollect',
	'payment_methods' => array( 'cc' => 'ALL' ),
	'currencies' => array(
		'+' => array(
			'AED', 'ALL', 'ANG', 'ARS', 'AUD', 'BBD', 'BDT', 'BGN', 'BHD',
			'BMD', 'BOB', 'BRL', 'BZD', 'CAD', 'CHF', 'CNY', 'COP', 'CRC',
			'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'EUR', 'FJD', 'GBP', 'GTQ',
			'HKD', 'HNL', 'HRK', 'HUF', 'IDR', 'ILS', 'INR', 'JMD', 'JOD',
			'JPY', 'KES', 'KRW', 'KWD', 'KZT', 'LBP', 'LKR', 'MAD', 'MKD',
			'MVR', 'MXN', 'MYR', 'NIO', 'NOK', 'NZD', 'OMR', 'PAB', 'PEN',
			'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RUB', 'SAR', 'SCR',
			'SEK', 'SGD', 'THB', 'TND', 'TRY', 'TTD', 'TWD', 'UAH', 'USD',
			'UYU', 'VEF', 'VND', 'VUV', 'XCD', 'ZAR',
		)
	),
	'selection_weight' => 10,
);

// FIXME: is this still needed?
/* Special case for Vietnam while GC is still having problems.
 * In the meantime: Visa & Mastercard, USD-only.
 */
$forms_whitelist['cc-vietnam'] = array(
	'gateway' => 'ingenico',
	'payment_methods' => array( 'cc' => array( 'visa', 'mc' ) ),
	'countries' => array( '+' => 'VN' ),
	'currencies' => array( '+' => 'USD' ),
);

/****************************
 * Name and Email-Only Test *
 ****************************/

$forms_whitelist['email-cc-vmaj'] = $forms_whitelist['cc-vmaj'];
$forms_whitelist['email-cc-vma'] = $forms_whitelist['cc-vma'];
$forms_whitelist['email-cc-vm'] = $forms_whitelist['cc-vm'];

/*************************
 * Recurring Credit Card *
 *************************/

$forms_whitelist['rcc-vmad'] = array(
	'gateway' => 'ingenico',
	'payment_methods' => array( 'cc' => array( 'visa', 'mc', 'amex', 'discover' ) ),
	'recurring',
	'countries' => array(
		'+' => $forms_whitelist['cc-vmad']['countries']['+']
	),
	'currencies' => array(
		'+' => array(
			'AED', 'ALL', 'ANG', 'ARS', 'AUD', 'BBD', 'BDT', 'BGN', 'BHD',
			'BMD', 'BOB', 'BRL', 'BZD', 'CAD', 'CHF', 'CNY', 'COP', 'CRC',
			'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'EUR', 'FJD', 'GBP', 'GTQ',
			'HKD', 'HNL', 'HRK', 'HUF', 'IDR', 'ILS', 'INR', 'JMD', 'JOD',
			'JPY', 'KES', 'KRW', 'KWD', 'KZT', 'LBP', 'LKR', 'MAD', 'MKD',
			'MVR', 'MXN', 'MYR', 'NIO', 'NOK', 'NZD', 'OMR', 'PAB', 'PEN',
			'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RUB', 'SAR', 'SCR',
			'SEK', 'SGD', 'THB', 'TND', 'TRY', 'TTD', 'TWD', 'UAH', 'USD',
			'UYU', 'VEF', 'VND', 'VUV', 'XCD', 'ZAR',
		)
	),
);

$forms_whitelist['rcc-vmaj'] = array(
	'gateway' => 'ingenico',
	'payment_methods' => array( 'cc' => array( 'visa', 'mc', 'amex', 'jcb' ) ),
	'recurring',
	'countries' => array(
		'+' => $forms_whitelist['cc-vmaj']['countries']['+']
	),
	'currencies' => array(
		'+' => array(
			'AED', 'ALL', 'ANG', 'ARS', 'AUD', 'BBD', 'BDT', 'BGN', 'BHD',
			'BMD', 'BOB', 'BRL', 'BZD', 'CAD', 'CHF', 'CNY', 'COP', 'CRC',
			'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'EUR', 'FJD', 'GBP', 'GTQ',
			'HKD', 'HNL', 'HRK', 'HUF', 'IDR', 'ILS', 'INR', 'JMD', 'JOD',
			'JPY', 'KES', 'KRW', 'KWD', 'KZT', 'LBP', 'LKR', 'MAD', 'MKD',
			'MVR', 'MXN', 'MYR', 'NIO', 'NOK', 'NZD', 'OMR', 'PAB', 'PEN',
			'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RUB', 'SAR', 'SCR',
			'SEK', 'SGD', 'THB', 'TND', 'TRY', 'TTD', 'TWD', 'UAH', 'USD',
			'UYU', 'VEF', 'VND', 'VUV', 'XCD', 'ZAR',
		)
	),
);

$forms_whitelist['rcc-vmd'] = array(
	'gateway' => 'ingenico',
	'payment_methods' => array( 'cc' => array( 'visa', 'mc', 'discover' ) ),
	'recurring',
	'countries' => array(
		'+' => $forms_whitelist['cc-vmd']['countries']['+']
	),
	'currencies' => array(
		'+' => array(
			'AED', 'ALL', 'ANG', 'ARS', 'AUD', 'BBD', 'BDT', 'BGN', 'BHD',
			'BMD', 'BOB', 'BRL', 'BZD', 'CAD', 'CHF', 'CNY', 'COP', 'CRC',
			'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'EUR', 'FJD', 'GBP', 'GTQ',
			'HKD', 'HNL', 'HRK', 'HUF', 'IDR', 'ILS', 'INR', 'JMD', 'JOD',
			'JPY', 'KES', 'KRW', 'KWD', 'KZT', 'LBP', 'LKR', 'MAD', 'MKD',
			'MVR', 'MXN', 'MYR', 'NIO', 'NOK', 'NZD', 'OMR', 'PAB', 'PEN',
			'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RUB', 'SAR', 'SCR',
			'SEK', 'SGD', 'THB', 'TND', 'TRY', 'TTD', 'TWD', 'UAH', 'USD',
			'UYU', 'VEF', 'VND', 'VUV', 'XCD', 'ZAR',
		)
	),
);

$forms_whitelist['rcc-vmj'] = array(
	'gateway' => 'globalcollect',
	'payment_methods' => array( 'cc' => array( 'visa', 'mc', 'jcb' ) ),
	'recurring',
	'countries' => array(
		'+' => $forms_whitelist['cc-vmj']['countries']['+']
	),
	'currencies' => array(
		'+' => array(
			'AED', 'ALL', 'ANG', 'ARS', 'AUD', 'BBD', 'BDT', 'BGN', 'BHD',
			'BMD', 'BOB', 'BRL', 'BZD', 'CAD', 'CHF', 'CNY', 'COP', 'CRC',
			'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'EUR', 'FJD', 'GBP', 'GTQ',
			'HKD', 'HNL', 'HRK', 'HUF', 'IDR', 'ILS', 'INR', 'JMD', 'JOD',
			'JPY', 'KES', 'KRW', 'KWD', 'KZT', 'LBP', 'LKR', 'MAD', 'MKD',
			'MVR', 'MXN', 'MYR', 'NIO', 'NOK', 'NZD', 'OMR', 'PAB', 'PEN',
			'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RUB', 'SAR', 'SCR',
			'SEK', 'SGD', 'THB', 'TND', 'TRY', 'TTD', 'TWD', 'UAH', 'USD',
			'UYU', 'VEF', 'VND', 'VUV', 'XCD', 'ZAR',
		)
	),
);

$forms_whitelist['rcc-vma'] = array(
	'gateway' => 'ingenico',
	'payment_methods' => array( 'cc' => array( 'visa', 'mc', 'amex' ) ),
	'recurring',
	'countries' => array(
		'+' => $forms_whitelist['cc-vma']['countries']['+']
	),
	'currencies' => array(
		'+' => array(
			'AED', 'ALL', 'ANG', 'ARS', 'AUD', 'BBD', 'BDT', 'BGN', 'BHD',
			'BMD', 'BOB', 'BRL', 'BZD', 'CAD', 'CHF', 'CNY', 'COP', 'CRC',
			'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'EUR', 'FJD', 'GBP', 'GTQ',
			'HKD', 'HNL', 'HRK', 'HUF', 'IDR', 'ILS', 'INR', 'JMD', 'JOD',
			'JPY', 'KES', 'KRW', 'KWD', 'KZT', 'LBP', 'LKR', 'MAD', 'MKD',
			'MVR', 'MXN', 'MYR', 'NIO', 'NOK', 'NZD', 'OMR', 'PAB', 'PEN',
			'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RUB', 'SAR', 'SCR',
			'SEK', 'SGD', 'THB', 'TND', 'TRY', 'TTD', 'TWD', 'UAH', 'USD',
			'UYU', 'VEF', 'VND', 'VUV', 'XCD', 'ZAR',
		)
	),
);

$forms_whitelist['rcc-vm'] = array(
	'gateway' => 'ingenico',
	'payment_methods' => array( 'cc' => array( 'visa', 'mc' ) ),
	'recurring',
	'countries' => array(
		'+' => $forms_whitelist['cc-vm']['countries']['+']
	),
	'currencies' => array(
		'+' => array(
			'AED', 'ALL', 'ANG', 'ARS', 'AUD', 'BBD', 'BDT', 'BGN', 'BHD',
			'BMD', 'BOB', 'BRL', 'BZD', 'CAD', 'CHF', 'CNY', 'COP', 'CRC',
			'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'EUR', 'FJD', 'GBP', 'GTQ',
			'HKD', 'HNL', 'HRK', 'HUF', 'IDR', 'ILS', 'INR', 'JMD', 'JOD',
			'JPY', 'KES', 'KRW', 'KWD', 'KZT', 'LBP', 'LKR', 'MAD', 'MKD',
			'MVR', 'MXN', 'MYR', 'NIO', 'NOK', 'NZD', 'OMR', 'PAB', 'PEN',
			'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RUB', 'SAR', 'SCR',
			'SEK', 'SGD', 'THB', 'TND', 'TRY', 'TTD', 'TWD', 'UAH', 'USD',
			'UYU', 'VEF', 'VND', 'VUV', 'XCD', 'ZAR',
		)
	),
);

$forms_whitelist['rcc'] = array(
	'gateway' => 'ingenico',
	'payment_methods' => array( 'cc' => 'ALL' ),
	'recurring',
	'currencies' => array(
		'+' => array(
			'AED', 'ALL', 'ANG', 'ARS', 'AUD', 'BBD', 'BDT', 'BGN', 'BHD',
			'BMD', 'BOB', 'BRL', 'BZD', 'CAD', 'CHF', 'CNY', 'COP', 'CRC',
			'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'EUR', 'FJD', 'GBP', 'GTQ',
			'HKD', 'HNL', 'HRK', 'HUF', 'IDR', 'ILS', 'INR', 'JMD', 'JOD',
			'JPY', 'KES', 'KRW', 'KWD', 'KZT', 'LBP', 'LKR', 'MAD', 'MKD',
			'MVR', 'MXN', 'MYR', 'NIO', 'NOK', 'NZD', 'OMR', 'PAB', 'PEN',
			'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RUB', 'SAR', 'SCR',
			'SEK', 'SGD', 'THB', 'TND', 'TRY', 'TTD', 'TWD', 'UAH', 'USD',
			'UYU', 'VEF', 'VND', 'VUV', 'XCD', 'ZAR',
		)
	),
	'selection_weight' => 100,
);

/**
 * FIXME: remove as soon as we know this isn't being sent by anyone.
 */
$forms_whitelist['rcc-ingenico'] = array(
	'gateway' => 'ingenico',
	'payment_methods' => array( 'cc' => 'ALL' ),
	'recurring',
	'currencies' => array(
		'+' => array(
			'AED', 'ALL', 'ANG', 'ARS', 'AUD', 'BBD', 'BDT', 'BGN', 'BHD',
			'BMD', 'BOB', 'BRL', 'BZD', 'CAD', 'CHF', 'CNY', 'COP', 'CRC',
			'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'EUR', 'FJD', 'GBP', 'GTQ',
			'HKD', 'HNL', 'HRK', 'HUF', 'IDR', 'ILS', 'INR', 'JMD', 'JOD',
			'JPY', 'KES', 'KRW', 'KWD', 'KZT', 'LBP', 'LKR', 'MAD', 'MKD',
			'MVR', 'MXN', 'MYR', 'NIO', 'NOK', 'NZD', 'OMR', 'PAB', 'PEN',
			'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RUB', 'SAR', 'SCR',
			'SEK', 'SGD', 'THB', 'TND', 'TRY', 'TTD', 'TWD', 'UAH', 'USD',
			'UYU', 'VEF', 'VND', 'VUV', 'XCD', 'ZAR',
		)
	),
	'selection_weight' => 10,
);

/**
 * Fallback for old Ingenico API
 */
$forms_whitelist['rcc-globalcollect'] = array(
	'gateway' => 'globalcollect',
	'payment_methods' => array( 'cc' => 'ALL' ),
	'recurring',
	'currencies' => array(
		'+' => array(
			'AED', 'ALL', 'ANG', 'ARS', 'AUD', 'BBD', 'BDT', 'BGN', 'BHD',
			'BMD', 'BOB', 'BRL', 'BZD', 'CAD', 'CHF', 'CNY', 'COP', 'CRC',
			'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'EUR', 'FJD', 'GBP', 'GTQ',
			'HKD', 'HNL', 'HRK', 'HUF', 'IDR', 'ILS', 'INR', 'JMD', 'JOD',
			'JPY', 'KES', 'KRW', 'KWD', 'KZT', 'LBP', 'LKR', 'MAD', 'MKD',
			'MVR', 'MXN', 'MYR', 'NIO', 'NOK', 'NZD', 'OMR', 'PAB', 'PEN',
			'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RUB', 'SAR', 'SCR',
			'SEK', 'SGD', 'THB', 'TND', 'TRY', 'TTD', 'TWD', 'UAH', 'USD',
			'UYU', 'VEF', 'VND', 'VUV', 'XCD', 'ZAR',
		)
	),
	'selection_weight' => 10,
);

/*************************
 * Paypal
 *************************/

$forms_whitelist['paypal'] = array(
	'gateway' => 'paypal',
	'payment_methods' => array( 'paypal' => 'ALL' ),
	'selection_weight' => 10,
);

$forms_whitelist['paypal-recurring'] = array(
	'gateway' => 'paypal',
	'payment_methods' => array( 'paypal' => 'ALL' ),
	'recurring',
	'selection_weight' => 10,
);

$forms_whitelist['paypal_ec'] = array(
	'gateway' => 'paypal_ec',
	'payment_methods' => array( 'paypal' => 'ALL' ),
	'selection_weight' => 100,
);

$forms_whitelist['paypal_ec-recurring'] = array(
	'gateway' => 'paypal_ec',
	'payment_methods' => array( 'paypal' => 'ALL' ),
	'recurring',
	'selection_weight' => 100,
);

/************
 * AstroPay *
 ************/
$forms_whitelist['astropay'] = array(
	'gateway' => 'astropay',
	'countries' => array( '+' => 'BR' ),
	'currencies' => array( '+' => 'BRL' ),
	'payment_methods' => array(
		'cc' => array(
			'visa',
			'mc',
			'amex',
			'elo',
			'diners',
			'hiper',
			'mercadolivre',
		),
		'cash' => array(
			'cash_boleto',
		),
		'bt' => array(
			'banco_do_brasil',
			'itau',
			'bradesco',
			'santander',
		),
	),
	'selection_weight' => 110,
);
$forms_whitelist['astropay-ar'] = array(
	'gateway' => 'astropay',
	'countries' => array( '+' => 'AR' ),
	'currencies' => array( '+' => 'ARS' ),
	'payment_methods' => array(
		'cc' => array(
			'visa',
			'mc',
			'amex',
			'cabal',
			'naranja',
			'shopping',
			'nativa',
			'cencosud',
			'argen',
		),
		'cash' => array(
			'cash_rapipago',
			'cash_pago_facil',
			'cash_provencia_pagos',
		),
		'bt' => array(
			'santander_rio',
		),
	),
	'selection_weight' => 110,
);
$forms_whitelist['astropay-cl'] = array(
	'gateway' => 'astropay',
	'countries' => array( '+' => 'CL' ),
	'currencies' => array( '+' => 'CLP' ),
	'payment_methods' => array(
		'cc' => array(
# 'visa',
# 'mc',
# 'amex',
# 'magna',
# 'diners',
# 'cmr',
# 'presto',
			'webpay',
		),
		'bt' => array(
			'webpay_bt',
		),
	),
	'selection_weight' => 110,
);
$forms_whitelist['astropay-co'] = array(
	'gateway' => 'astropay',
	'countries' => array( '+' => 'CO' ),
	'currencies' => array( '+' => 'COP' ),
	'payment_methods' => array(
		'cc' => array(
			'visa',
			'mc',
			'amex',
			'diners',
		),
		'cash' => array(
			'cash_efecty',
			'cash_davivienda',
		),
		'bt' => array(
			'pse',
		),
	),
	'selection_weight' => 110,
);
$forms_whitelist['astropay-mx'] = array(
	'gateway' => 'astropay',
	'countries' => array( '+' => 'MX' ),
	'currencies' => array( '+' => 'MXN' ),
	'payment_methods' => array(
		'cc' => array(
			'visa',
			'mc',
			'visa-debit',
			'mc-debit',
		),
		'cash' => array(
			'cash_oxxo',
			'cash_santander',
			'cash_banamex',
			'cash_bancomer',
		),
	),
	'selection_weight' => 110,
);
$forms_whitelist['astropay-pe'] = array(
	'gateway' => 'astropay',
	'countries' => array( '+' => 'PE' ),
	'currencies' => array( '+' => 'PEN' ),
	'payment_methods' => array(
		'cc' => array(
			'visa',
			'mc',
			'amex',
			'diners',
			'visa-debit',
		),
		'cash' => array(
			'cash_pago_efectivo',
		),
	),
	'selection_weight' => 1,
);
$forms_whitelist['astropay-uy'] = array(
	'gateway' => 'astropay',
	'countries' => array( '+' => 'UY' ),
	'currencies' => array( '+' => 'UYU' ),
	'payment_methods' => array(
		'cc' => array(
			'visa',
			'mc',
			'diners',
		),
		'cash' => array(
			'cash_red_pagos',
		),
	),
	'selection_weight' => 1,
);

/**********
 * Adyen *
 **********/
$forms_whitelist['adyen'] = array(
	'gateway' => 'adyen',
	'countries' => array( '+' => array( 'IL', 'JP' ), ),
	'currencies' => array( '+' => array( 'ILS', 'USD' ), ),
	'payment_methods' => array(
		'cc' => array( 'visa', 'mc', 'amex', 'discover', 'jcb' ),
	),
	'selection_weight' => 110,
);

$forms_whitelist['adyen-usd'] = array(
	'gateway' => 'adyen',
	'countries' => array( '+' => array( 'CA', 'FR', 'GB', 'IL', 'JP', 'NL', 'UA', 'US', ), ),
	'currencies' => array( '+' => array( 'USD', ), ),
	'payment_methods' => array(
		'cc' => array( 'visa', 'mc', 'amex', 'discover', 'cb', 'jcb' ),
	),
	// Setting form chooser weight to zero so this form is not chosen as default
	'selection_weight' => 0,
);

$forms_whitelist['adyen-test'] = array(
	'gateway' => 'adyen',
	'countries' => array( '+' => array( 'CA', 'FR', 'GB', 'IL', 'JP', 'NL', 'UA', 'US', ), ),
	'currencies' => array( '+' => array( 'CAD', 'EUR', 'GBP', 'JPY'. 'ILS', 'UAH', 'USD', ), ),
	'payment_methods' => array(
		'cc' => array( 'visa', 'mc', 'amex', 'discover', 'cb' ),
	),
	// Setting form chooser weight to zero so this form is not chosen as default
	'selection_weight' => 0,
);

/* * ***********
 * Error Pages *
 * *********** */

$forms_whitelist['error-default'] = array(
	'gateway' => array( 'globalcollect', 'ingenico', 'adyen', 'amazon', 'astropay', 'paypal', 'paypal_ec' ),
	'special_type' => 'error',
);

$forms_whitelist['error-noform'] = array(
	'gateway' => array( 'globalcollect', 'ingenico', 'adyen', 'amazon', 'astropay', 'paypal', 'paypal_ec' ),
	'special_type' => 'error',
);

$forms_whitelist['error-cc'] = array(
	'gateway' => array( 'globalcollect', 'ingenico', 'adyen', 'astropay' ),
	'payment_methods' => array( 'cc' => 'ALL' ),
	'special_type' => 'error',
);

$wgDonationInterfaceAllowedHtmlForms = $forms_whitelist;

unset( $forms_whitelist );
unset( $form_dirs );
