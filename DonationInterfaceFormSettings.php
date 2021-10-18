<?php
/**
 * FIXME: These determine which gateways FormChooser picks for different
 * parameters. The chooser should instead query enabled gateway capabilities
 * and simply pass along any ffname from the banner to allow A/B testing.
 */
use SmashPig\PaymentData\FinalStatus;

global $wgDonationInterfaceAllowedHtmlForms;
/**
 * Temp var for terseness, unset at end of file
 */
$forms = [];

/*
 * Amazon
 */
$forms['amazon'] = [
	'gateway' => 'amazon',
	'payment_methods' => [ 'amazon' => 'ALL' ],
];

$forms['amazon-recurring'] = [
	'gateway' => 'amazon',
	'payment_methods' => [ 'amazon' => 'ALL' ],
	'recurring',
];

/*******************************
 * RealTime Banking - Two Step *
 *******************************/

$forms['rtbt-ideal'] = [
	'gateway' => 'globalcollect',
	'payment_methods' => [ 'rtbt' => 'rtbt_ideal' ],
	'countries' => [ '+' => 'NL' ],
	'currencies' => [ '+' => 'EUR' ],
];

/********
 * BPAY *
 ********/

$forms['obt-bpay'] = [
	'gateway' => 'globalcollect',
	'countries' => [ '+' => 'AU' ],
	'currencies' => [ '+' => 'AUD' ],
	'payment_methods' => [ 'obt' => 'bpay' ]
];

/**********************
 * Credit Card - Misc *
 **********************/

$forms['cc-vmad'] = [
	'gateway' => 'ingenico',
	'payment_methods' => [ 'cc' => [ 'visa', 'mc', 'amex', 'discover' ] ],
	'countries' => [
		'+' => [ 'US', ],
	],
	'currencies' => [
		'+' => [
			'AED', 'ALL', 'ANG', 'ARS', 'AUD', 'BBD', 'BDT', 'BGN', 'BHD',
			'BMD', 'BOB', 'BRL', 'BZD', 'CAD', 'CHF', 'CNY', 'COP', 'CRC',
			'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'EUR', 'FJD', 'GBP', 'GTQ',
			'HKD', 'HNL', 'HRK', 'HUF', 'IDR', 'ILS', 'INR', 'JMD', 'JOD',
			'JPY', 'KES', 'KRW', 'KWD', 'KZT', 'LBP', 'LKR', 'MAD', 'MKD',
			'MVR', 'MXN', 'MYR', 'NIO', 'NOK', 'NZD', 'OMR', 'PAB', 'PEN',
			'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RUB', 'SAR', 'SCR',
			'SEK', 'SGD', 'THB', 'TND', 'TRY', 'TTD', 'TWD', 'UAH', 'USD',
			'UYU', 'VEF', 'VND', 'VUV', 'XCD', 'ZAR',
		]
	],
];

$forms['cc-vjma'] = [
	'gateway' => 'ingenico',
	'payment_methods' => [ 'cc' => [ 'visa', 'jcb', 'mc', 'amex' ] ],
	'countries' => [
		'+' => [ 'JP', ],
	],
	'currencies' => [
		'+' => [
			'AED', 'ALL', 'ANG', 'ARS', 'AUD', 'BBD', 'BDT', 'BGN', 'BHD',
			'BMD', 'BOB', 'BRL', 'BZD', 'CAD', 'CHF', 'CNY', 'COP', 'CRC',
			'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'EUR', 'FJD', 'GBP', 'GTQ',
			'HKD', 'HNL', 'HRK', 'HUF', 'IDR', 'ILS', 'INR', 'JMD', 'JOD',
			'JPY', 'KES', 'KRW', 'KWD', 'KZT', 'LBP', 'LKR', 'MAD', 'MKD',
			'MVR', 'MXN', 'MYR', 'NIO', 'NOK', 'NZD', 'OMR', 'PAB', 'PEN',
			'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RUB', 'SAR', 'SCR',
			'SEK', 'SGD', 'THB', 'TND', 'TRY', 'TTD', 'TWD', 'UAH', 'USD',
			'UYU', 'VEF', 'VND', 'VUV', 'XCD', 'ZAR',
		]
	],
	'selection_weight' => 10,
];

$forms['cc-jvma'] = [
	'gateway' => 'ingenico',
	'payment_methods' => [ 'cc' => [ 'jcb', 'visa', 'mc', 'amex' ] ],
	'countries' => [
		'+' => [ 'JP', ],
	],
	'currencies' => [
		'+' => [
			'AED', 'ALL', 'ANG', 'ARS', 'AUD', 'BBD', 'BDT', 'BGN', 'BHD',
			'BMD', 'BOB', 'BRL', 'BZD', 'CAD', 'CHF', 'CNY', 'COP', 'CRC',
			'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'EUR', 'FJD', 'GBP', 'GTQ',
			'HKD', 'HNL', 'HRK', 'HUF', 'IDR', 'ILS', 'INR', 'JMD', 'JOD',
			'JPY', 'KES', 'KRW', 'KWD', 'KZT', 'LBP', 'LKR', 'MAD', 'MKD',
			'MVR', 'MXN', 'MYR', 'NIO', 'NOK', 'NZD', 'OMR', 'PAB', 'PEN',
			'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RUB', 'SAR', 'SCR',
			'SEK', 'SGD', 'THB', 'TND', 'TRY', 'TTD', 'TWD', 'UAH', 'USD',
			'UYU', 'VEF', 'VND', 'VUV', 'XCD', 'ZAR',
		]
	],
	'selection_weight' => 0,
];

$forms['cc-vmaj'] = [
	'gateway' => 'ingenico',
	'payment_methods' => [ 'cc' => [ 'visa', 'mc', 'amex', 'jcb' ] ],
	'countries' => [
		'+' => [
			'AD', 'AT', 'AU', 'BE', 'BH', 'DE', 'EC', 'ES', 'FI', 'GB',
			'GF', 'GR', 'HK', 'IE', 'IT', 'KR', 'LU', 'MY', 'NL', 'PR', 'PT',
			'SG', 'SI', 'SK', 'TH', 'TW',
		],
	],
	'currencies' => [
		'+' => [
			'AED', 'ALL', 'ANG', 'ARS', 'AUD', 'BBD', 'BDT', 'BGN', 'BHD',
			'BMD', 'BOB', 'BRL', 'BZD', 'CAD', 'CHF', 'CNY', 'COP', 'CRC',
			'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'EUR', 'FJD', 'GBP', 'GTQ',
			'HKD', 'HNL', 'HRK', 'HUF', 'IDR', 'ILS', 'INR', 'JMD', 'JOD',
			'JPY', 'KES', 'KRW', 'KWD', 'KZT', 'LBP', 'LKR', 'MAD', 'MKD',
			'MVR', 'MXN', 'MYR', 'NIO', 'NOK', 'NZD', 'OMR', 'PAB', 'PEN',
			'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RUB', 'SAR', 'SCR',
			'SEK', 'SGD', 'THB', 'TND', 'TRY', 'TTD', 'TWD', 'UAH', 'USD',
			'UYU', 'VEF', 'VND', 'VUV', 'XCD', 'ZAR',
		]
	],
];

$forms['cc-vmd'] = [
	'gateway' => 'ingenico',
	'payment_methods' => [ 'cc' => [ 'visa', 'mc', 'discover' ] ],
	'countries' => [
		// Array merge with cc-vmad as fallback in case 'a' goes down
		'+' => array_merge(
			$forms['cc-vmad']['countries']['+'],
			[] // as of right now, nothing specific here
		),
	],
	'currencies' => [
		'+' => [
			'AED', 'ALL', 'ANG', 'ARS', 'AUD', 'BBD', 'BDT', 'BGN', 'BHD',
			'BMD', 'BOB', 'BRL', 'BZD', 'CAD', 'CHF', 'CNY', 'COP', 'CRC',
			'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'EUR', 'FJD', 'GBP', 'GTQ',
			'HKD', 'HNL', 'HRK', 'HUF', 'IDR', 'ILS', 'INR', 'JMD', 'JOD',
			'JPY', 'KES', 'KRW', 'KWD', 'KZT', 'LBP', 'LKR', 'MAD', 'MKD',
			'MVR', 'MXN', 'MYR', 'NIO', 'NOK', 'NZD', 'OMR', 'PAB', 'PEN',
			'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RUB', 'SAR', 'SCR',
			'SEK', 'SGD', 'THB', 'TND', 'TRY', 'TTD', 'TWD', 'UAH', 'USD',
			'UYU', 'VEF', 'VND', 'VUV', 'XCD', 'ZAR',
		]
	],
];

$forms['cc-vmj'] = [
	'gateway' => 'ingenico',
	'payment_methods' => [ 'cc' => [ 'visa', 'mc', 'jcb' ] ],
	'countries' => [
		// Array merge with cc-vmaj as fallback in case 'a' goes down
		'+' => array_merge(
			$forms['cc-vmaj']['countries']['+'],
			[ 'BR', 'ID', 'PH', ]
		),
	],
	'currencies' => [
		'+' => [
			'AED', 'ALL', 'ANG', 'ARS', 'AUD', 'BBD', 'BDT', 'BGN', 'BHD',
			'BMD', 'BOB', 'BRL', 'BZD', 'CAD', 'CHF', 'CNY', 'COP', 'CRC',
			'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'EUR', 'FJD', 'GBP', 'GTQ',
			'HKD', 'HNL', 'HRK', 'HUF', 'IDR', 'ILS', 'INR', 'JMD', 'JOD',
			'JPY', 'KES', 'KRW', 'KWD', 'KZT', 'LBP', 'LKR', 'MAD', 'MKD',
			'MVR', 'MXN', 'MYR', 'NIO', 'NOK', 'NZD', 'OMR', 'PAB', 'PEN',
			'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RUB', 'SAR', 'SCR',
			'SEK', 'SGD', 'THB', 'TND', 'TRY', 'TTD', 'TWD', 'UAH', 'USD',
			'UYU', 'VEF', 'VND', 'VUV', 'XCD', 'ZAR',
		]
	],
];

$forms['cc-vma'] = [
	'gateway' => 'ingenico',
	'payment_methods' => [ 'cc' => [ 'visa', 'mc', 'amex' ] ],
	'countries' => [
		// Array merge with cc-vmaj as fallback in case 'j' goes down
		// Array merge with cc-vmad as fallback in case 'd' goes down
		'+' => array_merge(
			$forms['cc-vmaj']['countries']['+'],
			$forms['cc-vmad']['countries']['+'],
			[
				'AE', 'AL', 'AN', 'AR', 'BG', 'CA', 'CH', 'CN', 'CR', 'CY', 'CZ', 'DK',
				'DZ', 'EE', 'EG', 'JO', 'KE', 'HR', 'HU', 'IL', 'KW', 'KZ', 'LB', 'LI',
				'LK', 'LT', 'LV', 'MA', 'MT', 'NO', 'NZ', 'OM', 'PK', 'PL', 'QA', 'RO',
				'RU', 'SA', 'SE', 'TN', 'TR', 'UA',
			]
		)
	],
	'currencies' => [
		'+' => [
			'AED', 'ALL', 'ANG', 'ARS', 'AUD', 'BBD', 'BDT', 'BGN', 'BHD',
			'BMD', 'BOB', 'BRL', 'BZD', 'CAD', 'CHF', 'CNY', 'COP', 'CRC',
			'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'EUR', 'FJD', 'GBP', 'GTQ',
			'HKD', 'HNL', 'HRK', 'HUF', 'IDR', 'ILS', 'INR', 'JMD', 'JOD',
			'JPY', 'KES', 'KRW', 'KWD', 'KZT', 'LBP', 'LKR', 'MAD', 'MKD',
			'MVR', 'MXN', 'MYR', 'NIO', 'NOK', 'NZD', 'OMR', 'PAB', 'PEN',
			'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RUB', 'SAR', 'SCR',
			'SEK', 'SGD', 'THB', 'TND', 'TRY', 'TTD', 'TWD', 'UAH', 'USD',
			'UYU', 'VEF', 'VND', 'VUV', 'XCD', 'ZAR',
		]
	],
];

$forms['cc-vm'] = [
	'gateway' => 'ingenico',
	'payment_methods' => [ 'cc' => [ 'visa', 'mc' ] ],
	'countries' => [
		// Array merge with cc-vmj as fallback in case 'j' goes down
		// Array merge with cc-vmd as fallback in case 'd' goes down
		'+' => array_merge(
			$forms['cc-vmj']['countries']['+'],
			$forms['cc-vmd']['countries']['+'],
			[
				'AG', 'AM', 'AO', 'AS', 'AW', 'AZ', 'BA', 'BB', 'BD', 'BF', 'BI', 'BJ',
				'BM', 'BN', 'BO', 'BS', 'BW', 'BY', 'BZ', 'CF', 'CG', 'CI', 'CK', 'CL',
				'CM', 'CO', 'CV', 'DJ', 'DM', 'DO', 'ER', 'ET', 'FJ', 'FM', 'FO', 'GA',
				'GD', 'GE', 'GL', 'GM', 'GT', 'GU', 'HN', 'IN', 'IQ', 'IS', 'JM', 'KH',
				'KI', 'KM', 'KN', 'KP', 'LC', 'LR', 'LY', 'MC', 'MD', 'ME', 'MG', 'MH',
				'MK', 'ML', 'MO', 'MP', 'MR', 'MV', 'MW', 'MX', 'MZ', 'NA', 'NE', 'NG',
				'NI', 'NR', 'PA', 'PE', 'PG', 'PS', 'PW', 'PY', 'RE', 'RS', 'SB', 'SC',
				'SD', 'SM', 'SN', 'SR', 'TD', 'TG', 'TM', 'TO', 'TP', 'TT', 'TZ', 'UY',
				'UZ', 'VA', 'VC', 'VE', 'VI', 'VU', 'YE', 'ZA', 'ZM', 'ZW',
			]
		),
	],
	'currencies' => [
		'+' => [
			'AED', 'ALL', 'ANG', 'ARS', 'AUD', 'BBD', 'BDT', 'BGN', 'BHD',
			'BMD', 'BOB', 'BRL', 'BZD', 'CAD', 'CHF', 'CNY', 'COP', 'CRC',
			'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'EUR', 'FJD', 'GBP', 'GTQ',
			'HKD', 'HNL', 'HRK', 'HUF', 'IDR', 'ILS', 'INR', 'JMD', 'JOD',
			'JPY', 'KES', 'KRW', 'KWD', 'KZT', 'LBP', 'LKR', 'MAD', 'MKD',
			'MVR', 'MXN', 'MYR', 'NIO', 'NOK', 'NZD', 'OMR', 'PAB', 'PEN',
			'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RUB', 'SAR', 'SCR',
			'SEK', 'SGD', 'THB', 'TND', 'TRY', 'TTD', 'TWD', 'UAH', 'USD',
			'UYU', 'VEF', 'VND', 'VUV', 'XCD', 'ZAR',
		]
	],
];

$forms['cc-a'] = [
	'gateway' => 'ingenico',
	'payment_methods' => [ 'cc' => [ 'amex' ] ],
	'countries' => [
		'+' => array_merge(
			$forms['cc-vma']['countries']['+'],
			[] // as of right now, nothing specific here
		)
	],
	'currencies' => [
		'+' => [
			'AED', 'ALL', 'ANG', 'ARS', 'AUD', 'BBD', 'BDT', 'BGN', 'BHD',
			'BMD', 'BOB', 'BRL', 'BZD', 'CAD', 'CHF', 'CNY', 'COP', 'CRC',
			'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'EUR', 'FJD', 'GBP', 'GTQ',
			'HKD', 'HNL', 'HRK', 'HUF', 'IDR', 'ILS', 'INR', 'JMD', 'JOD',
			'JPY', 'KES', 'KRW', 'KWD', 'KZT', 'LBP', 'LKR', 'MAD', 'MKD',
			'MVR', 'MXN', 'MYR', 'NIO', 'NOK', 'NZD', 'OMR', 'PAB', 'PEN',
			'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RUB', 'SAR', 'SCR',
			'SEK', 'SGD', 'THB', 'TND', 'TRY', 'TTD', 'TWD', 'UAH', 'USD',
			'UYU', 'VEF', 'VND', 'VUV', 'XCD', 'ZAR',
		]
	],
];

$forms['cc'] = [
	'gateway' => 'ingenico',
	'payment_methods' => [ 'cc' => 'ALL' ],
	'countries' => [ '-' => 'VN' ],
	'currencies' => [
		'+' => [
			'AED', 'ALL', 'ANG', 'ARS', 'AUD', 'BBD', 'BDT', 'BGN', 'BHD',
			'BMD', 'BOB', 'BRL', 'BZD', 'CAD', 'CHF', 'CNY', 'COP', 'CRC',
			'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'EUR', 'FJD', 'GBP', 'GTQ',
			'HKD', 'HNL', 'HRK', 'HUF', 'IDR', 'ILS', 'INR', 'JMD', 'JOD',
			'JPY', 'KES', 'KRW', 'KWD', 'KZT', 'LBP', 'LKR', 'MAD', 'MKD',
			'MVR', 'MXN', 'MYR', 'NIO', 'NOK', 'NZD', 'OMR', 'PAB', 'PEN',
			'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RUB', 'SAR', 'SCR',
			'SEK', 'SGD', 'THB', 'TND', 'TRY', 'TTD', 'TWD', 'UAH', 'USD',
			'UYU', 'VEF', 'VND', 'VUV', 'XCD', 'ZAR',
		]
	],
];

/**
 * FIXME: remove as soon as we know this isn't being sent by anyone.
 */
$forms['cc-ingenico'] = [
	'gateway' => 'ingenico',
	'payment_methods' => [ 'cc' => 'ALL' ],
	'currencies' => [
		'+' => [
			'AED', 'ALL', 'ANG', 'ARS', 'AUD', 'BBD', 'BDT', 'BGN', 'BHD',
			'BMD', 'BOB', 'BRL', 'BZD', 'CAD', 'CHF', 'CNY', 'COP', 'CRC',
			'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'EUR', 'FJD', 'GBP', 'GTQ',
			'HKD', 'HNL', 'HRK', 'HUF', 'IDR', 'ILS', 'INR', 'JMD', 'JOD',
			'JPY', 'KES', 'KRW', 'KWD', 'KZT', 'LBP', 'LKR', 'MAD', 'MKD',
			'MVR', 'MXN', 'MYR', 'NIO', 'NOK', 'NZD', 'OMR', 'PAB', 'PEN',
			'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RUB', 'SAR', 'SCR',
			'SEK', 'SGD', 'THB', 'TND', 'TRY', 'TTD', 'TWD', 'UAH', 'USD',
			'UYU', 'VEF', 'VND', 'VUV', 'XCD', 'ZAR',
		]
	],
	'selection_weight' => 10,
];

/****************************
 * Name and Email-Only Test *
 ****************************/

$forms['email-cc-vmaj'] = $forms['cc-vmaj'];
$forms['email-cc-vma'] = $forms['cc-vma'];
$forms['email-cc-vm'] = $forms['cc-vm'];

/*************************
 * Recurring Credit Card *
 *************************/

$forms['rcc-vmad'] = [
	'gateway' => 'ingenico',
	'payment_methods' => [ 'cc' => [ 'visa', 'mc', 'amex', 'discover' ] ],
	'recurring',
	'countries' => [
		'+' => $forms['cc-vmad']['countries']['+']
	],
	'currencies' => [
		'+' => [
			'AED', 'ALL', 'ANG', 'ARS', 'AUD', 'BBD', 'BDT', 'BGN', 'BHD',
			'BMD', 'BOB', 'BRL', 'BZD', 'CAD', 'CHF', 'CNY', 'COP', 'CRC',
			'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'EUR', 'FJD', 'GBP', 'GTQ',
			'HKD', 'HNL', 'HRK', 'HUF', 'IDR', 'ILS', 'INR', 'JMD', 'JOD',
			'JPY', 'KES', 'KRW', 'KWD', 'KZT', 'LBP', 'LKR', 'MAD', 'MKD',
			'MVR', 'MXN', 'MYR', 'NIO', 'NOK', 'NZD', 'OMR', 'PAB', 'PEN',
			'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RUB', 'SAR', 'SCR',
			'SEK', 'SGD', 'THB', 'TND', 'TRY', 'TTD', 'TWD', 'UAH', 'USD',
			'UYU', 'VEF', 'VND', 'VUV', 'XCD', 'ZAR',
		]
	],
];

$forms['rcc-vmaj'] = [
	'gateway' => 'ingenico',
	'payment_methods' => [ 'cc' => [ 'visa', 'mc', 'amex', 'jcb' ] ],
	'recurring',
	'countries' => [
		'+' => $forms['cc-vmaj']['countries']['+']
	],
	'currencies' => [
		'+' => [
			'AED', 'ALL', 'ANG', 'ARS', 'AUD', 'BBD', 'BDT', 'BGN', 'BHD',
			'BMD', 'BOB', 'BRL', 'BZD', 'CAD', 'CHF', 'CNY', 'COP', 'CRC',
			'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'EUR', 'FJD', 'GBP', 'GTQ',
			'HKD', 'HNL', 'HRK', 'HUF', 'IDR', 'ILS', 'INR', 'JMD', 'JOD',
			'JPY', 'KES', 'KRW', 'KWD', 'KZT', 'LBP', 'LKR', 'MAD', 'MKD',
			'MVR', 'MXN', 'MYR', 'NIO', 'NOK', 'NZD', 'OMR', 'PAB', 'PEN',
			'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RUB', 'SAR', 'SCR',
			'SEK', 'SGD', 'THB', 'TND', 'TRY', 'TTD', 'TWD', 'UAH', 'USD',
			'UYU', 'VEF', 'VND', 'VUV', 'XCD', 'ZAR',
		]
	],
];

$forms['rcc-vmd'] = [
	'gateway' => 'ingenico',
	'payment_methods' => [ 'cc' => [ 'visa', 'mc', 'discover' ] ],
	'recurring',
	'countries' => [
		'+' => $forms['cc-vmd']['countries']['+']
	],
	'currencies' => [
		'+' => [
			'AED', 'ALL', 'ANG', 'ARS', 'AUD', 'BBD', 'BDT', 'BGN', 'BHD',
			'BMD', 'BOB', 'BRL', 'BZD', 'CAD', 'CHF', 'CNY', 'COP', 'CRC',
			'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'EUR', 'FJD', 'GBP', 'GTQ',
			'HKD', 'HNL', 'HRK', 'HUF', 'IDR', 'ILS', 'INR', 'JMD', 'JOD',
			'JPY', 'KES', 'KRW', 'KWD', 'KZT', 'LBP', 'LKR', 'MAD', 'MKD',
			'MVR', 'MXN', 'MYR', 'NIO', 'NOK', 'NZD', 'OMR', 'PAB', 'PEN',
			'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RUB', 'SAR', 'SCR',
			'SEK', 'SGD', 'THB', 'TND', 'TRY', 'TTD', 'TWD', 'UAH', 'USD',
			'UYU', 'VEF', 'VND', 'VUV', 'XCD', 'ZAR',
		]
	],
];

$forms['rcc-vma'] = [
	'gateway' => 'ingenico',
	'payment_methods' => [ 'cc' => [ 'visa', 'mc', 'amex' ] ],
	'recurring',
	'countries' => [
		'+' => $forms['cc-vma']['countries']['+']
	],
	'currencies' => [
		'+' => [
			'AED', 'ALL', 'ANG', 'ARS', 'AUD', 'BBD', 'BDT', 'BGN', 'BHD',
			'BMD', 'BOB', 'BRL', 'BZD', 'CAD', 'CHF', 'CNY', 'COP', 'CRC',
			'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'EUR', 'FJD', 'GBP', 'GTQ',
			'HKD', 'HNL', 'HRK', 'HUF', 'IDR', 'ILS', 'INR', 'JMD', 'JOD',
			'JPY', 'KES', 'KRW', 'KWD', 'KZT', 'LBP', 'LKR', 'MAD', 'MKD',
			'MVR', 'MXN', 'MYR', 'NIO', 'NOK', 'NZD', 'OMR', 'PAB', 'PEN',
			'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RUB', 'SAR', 'SCR',
			'SEK', 'SGD', 'THB', 'TND', 'TRY', 'TTD', 'TWD', 'UAH', 'USD',
			'UYU', 'VEF', 'VND', 'VUV', 'XCD', 'ZAR',
		]
	],
];

$forms['rcc-vm'] = [
	'gateway' => 'ingenico',
	'payment_methods' => [ 'cc' => [ 'visa', 'mc' ] ],
	'recurring',
	'countries' => [
		'+' => $forms['cc-vm']['countries']['+']
	],
	'currencies' => [
		'+' => [
			'AED', 'ALL', 'ANG', 'ARS', 'AUD', 'BBD', 'BDT', 'BGN', 'BHD',
			'BMD', 'BOB', 'BRL', 'BZD', 'CAD', 'CHF', 'CNY', 'COP', 'CRC',
			'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'EUR', 'FJD', 'GBP', 'GTQ',
			'HKD', 'HNL', 'HRK', 'HUF', 'IDR', 'ILS', 'INR', 'JMD', 'JOD',
			'JPY', 'KES', 'KRW', 'KWD', 'KZT', 'LBP', 'LKR', 'MAD', 'MKD',
			'MVR', 'MXN', 'MYR', 'NIO', 'NOK', 'NZD', 'OMR', 'PAB', 'PEN',
			'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RUB', 'SAR', 'SCR',
			'SEK', 'SGD', 'THB', 'TND', 'TRY', 'TTD', 'TWD', 'UAH', 'USD',
			'UYU', 'VEF', 'VND', 'VUV', 'XCD', 'ZAR',
		]
	],
];

$forms['rcc'] = [
	'gateway' => 'ingenico',
	'payment_methods' => [ 'cc' => 'ALL' ],
	'recurring',
	'currencies' => [
		'+' => [
			'AED', 'ALL', 'ANG', 'ARS', 'AUD', 'BBD', 'BDT', 'BGN', 'BHD',
			'BMD', 'BOB', 'BRL', 'BZD', 'CAD', 'CHF', 'CNY', 'COP', 'CRC',
			'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'EUR', 'FJD', 'GBP', 'GTQ',
			'HKD', 'HNL', 'HRK', 'HUF', 'IDR', 'ILS', 'INR', 'JMD', 'JOD',
			'JPY', 'KES', 'KRW', 'KWD', 'KZT', 'LBP', 'LKR', 'MAD', 'MKD',
			'MVR', 'MXN', 'MYR', 'NIO', 'NOK', 'NZD', 'OMR', 'PAB', 'PEN',
			'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RUB', 'SAR', 'SCR',
			'SEK', 'SGD', 'THB', 'TND', 'TRY', 'TTD', 'TWD', 'UAH', 'USD',
			'UYU', 'VEF', 'VND', 'VUV', 'XCD', 'ZAR',
		]
	],
	'selection_weight' => 100,
];

/**
 * FIXME: remove as soon as we know this isn't being sent by anyone.
 */
$forms['rcc-ingenico'] = [
	'gateway' => 'ingenico',
	'payment_methods' => [ 'cc' => 'ALL' ],
	'recurring',
	'currencies' => [
		'+' => [
			'AED', 'ALL', 'ANG', 'ARS', 'AUD', 'BBD', 'BDT', 'BGN', 'BHD',
			'BMD', 'BOB', 'BRL', 'BZD', 'CAD', 'CHF', 'CNY', 'COP', 'CRC',
			'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'EUR', 'FJD', 'GBP', 'GTQ',
			'HKD', 'HNL', 'HRK', 'HUF', 'IDR', 'ILS', 'INR', 'JMD', 'JOD',
			'JPY', 'KES', 'KRW', 'KWD', 'KZT', 'LBP', 'LKR', 'MAD', 'MKD',
			'MVR', 'MXN', 'MYR', 'NIO', 'NOK', 'NZD', 'OMR', 'PAB', 'PEN',
			'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RUB', 'SAR', 'SCR',
			'SEK', 'SGD', 'THB', 'TND', 'TRY', 'TTD', 'TWD', 'UAH', 'USD',
			'UYU', 'VEF', 'VND', 'VUV', 'XCD', 'ZAR',
		]
	],
	'selection_weight' => 10,
];

/*************************
 * Paypal
 *************************/

$forms['paypal'] = [
	'gateway' => 'paypal',
	'payment_methods' => [ 'paypal' => 'ALL' ],
	'selection_weight' => 10,
];

$forms['paypal-recurring'] = [
	'gateway' => 'paypal',
	'payment_methods' => [ 'paypal' => 'ALL' ],
	'recurring',
	'selection_weight' => 10,
];

$forms['paypal_ec'] = [
	'gateway' => 'paypal_ec',
	'payment_methods' => [ 'paypal' => 'ALL' ],
	'selection_weight' => 100,
];

$forms['paypal_ec-recurring'] = [
	'gateway' => 'paypal_ec',
	'payment_methods' => [ 'paypal' => 'ALL' ],
	'recurring',
	'selection_weight' => 100,
];

/************
 * AstroPay *
 ************/
$forms['astropay'] = [
	'gateway' => 'astropay',
	'countries' => [ '+' => 'BR' ],
	'currencies' => [ '+' => 'BRL' ],
	'payment_methods' => [
		'cc' => [
			'visa',
			'mc',
			'amex',
			'elo',
			'diners',
			'hiper',
			'mercadolivre',
		],
		'cash' => [
			'cash_boleto',
		],
		'bt' => [
			'banco_do_brasil',
			'itau',
			'bradesco',
			'santander',
		],
	],
	'selection_weight' => 110,
];
$forms['astropay-ar'] = [
	'gateway' => 'astropay',
	'countries' => [ '+' => 'AR' ],
	'currencies' => [ '+' => 'ARS' ],
	'payment_methods' => [
		'cc' => [
			'visa',
			'mc',
			'amex',
			'cabal',
			'naranja',
			'shopping',
			'nativa',
			'cencosud',
			'argen',
		],
		'cash' => [
			'cash_rapipago',
			'cash_pago_facil',
			'cash_provencia_pagos',
		],
		'bt' => [
			'santander_rio',
		],
	],
	'selection_weight' => 110,
];
$forms['astropay-cl'] = [
	'gateway' => 'astropay',
	'countries' => [ '+' => 'CL' ],
	'currencies' => [ '+' => 'CLP' ],
	'payment_methods' => [
		'cc' => [
# 'visa',
# 'mc',
# 'amex',
# 'magna',
# 'diners',
# 'cmr',
# 'presto',
			'webpay',
		],
		'bt' => [
			'webpay_bt',
		],
	],
	'selection_weight' => 110,
];
$forms['astropay-co'] = [
	'gateway' => 'astropay',
	'countries' => [ '+' => 'CO' ],
	'currencies' => [ '+' => 'COP' ],
	'payment_methods' => [
		'cc' => [
			'visa',
			'mc',
			'amex',
			'diners',
		],
		'cash' => [
			'cash_efecty',
			'cash_davivienda',
		],
		'bt' => [
			'pse',
		],
	],
	'selection_weight' => 110,
];
$forms['astropay-in'] = [
	'gateway' => 'astropay',
	'countries' => [ '+' => 'IN' ],
	'currencies' => [ '+' => 'INR' ],
	'payment_methods' => [
		'cc' => [
			'visa',
			'mc',
			'amex',
			'diners',
			'rupay'
		],
		'bt' => [
			'netbanking',
			// 'paytmwallet'
			'upi'
		],
	],
	'selection_weight' => 0,
];
$forms['astropay-mx'] = [
	'gateway' => 'astropay',
	'countries' => [ '+' => 'MX' ],
	'currencies' => [ '+' => 'MXN' ],
	'payment_methods' => [
		'cc' => [
			'visa',
			'mc',
			'visa-debit',
			'mc-debit',
		],
		'cash' => [
			'cash_oxxo',
			'cash_santander',
			'cash_bancomer',
		],
	],
	'selection_weight' => 110,
];
/*$forms_whitelist['astropay-pe'] = [
	'gateway' => 'astropay',
	'countries' => [ '+' => 'PE' ],
	'currencies' => [ '+' => 'PEN' ],
	'payment_methods' => [
		'cc' => [
			'visa',
			'mc',
			'amex',
			'diners',
			'visa-debit',
		],
		'cash' => [
			'cash_pago_efectivo',
		],
	],
	'selection_weight' => 1,
];
*/

$forms['astropay-uy'] = [
	'gateway' => 'astropay',
	'countries' => [ '+' => 'UY' ],
	'currencies' => [ '+' => 'UYU' ],
	'payment_methods' => [
/*		'cc' => [
			'visa',
			'mc',
			'diners',
		],*/
		'cash' => [
			'cash_red_pagos',
		],
	],
	'selection_weight' => 1,
];

/**********
 * Adyen *
 **********/
$forms['adyen-au'] = [
	'gateway' => 'adyen',
	'countries' => [ '+' => [ 'AU' ], ],
	'currencies' => [ '+' => [ 'AUD', 'USD', ], ],
	'payment_methods' => [
		'apple' => [ 'ALL' ],
		'cc' => [ 'visa', 'mc', 'amex', 'jcb' ],
	],
	// Setting form chooser weight very low so this form is not chosen as default
	'selection_weight' => 1,
];

$forms['adyen-au-recurring'] = [
	'gateway' => 'adyen',
	'countries' => [ '+' => [ 'AU' ], ],
	'currencies' => [ '+' => [ 'AUD', 'USD', ], ],
	'payment_methods' => [
		'cc' => [ 'visa', 'mc', 'amex', 'jcb' ],
	],
	'recurring',
	// Setting form chooser weight very low so this form is not chosen as default
	'selection_weight' => 1,
];

$forms['adyen-ca'] = [
	'gateway' => 'adyen',
	'countries' => [ '+' => [ 'CA' ], ],
	'payment_methods' => [
		'apple' => [ 'ALL' ],
		'cc' => [ 'visa', 'mc', 'amex', ],
	],
	// Setting form chooser weight very low so this form is not chosen as default
	'selection_weight' => 1,
];

$forms['adyen-fr'] = [
	'gateway' => 'adyen',
	'countries' => [ '+' => [ 'FR' ], ],
	'payment_methods' => [
		'apple' => [ 'ALL' ],
		'cc' => [ 'cb', 'visa', 'mc', 'amex', ],
	],
	// Setting form chooser weight very low so this form is not chosen as default
	'selection_weight' => 1,
];

$forms['adyen-gb'] = [
	'gateway' => 'adyen',
	'countries' => [ '+' => [ 'GB' ], ],
	'payment_methods' => [
		'apple' => [ 'ALL' ],
		'cc' => [ 'visa', 'mc', 'amex', ],
	],
	// Setting form chooser weight very low so this form is not chosen as default
	'selection_weight' => 1,
];

$forms['adyen-ie'] = [
	'gateway' => 'adyen',
	'countries' => [ '+' => [ 'IE' ], ],
	'payment_methods' => [
		'apple' => [ 'ALL' ],
		'cc' => [ 'visa', 'mc', 'amex', 'jcb', ],
	],
	// Setting form chooser weight very low so this form is not chosen as default
	'selection_weight' => 1,
];

$forms['adyen-il'] = [
	'gateway' => 'adyen',
	'countries' => [ '+' => [ 'IL', ], ],
	'currencies' => [ '+' => [ 'ILS', 'USD' ], ],
	'payment_methods' => [
		'apple' => [ 'ALL' ],
		'cc' => [ 'visa', 'mc', 'amex', 'discover' ],
	],
	'selection_weight' => 110,
];

$forms['adyen-jp'] = [
	'gateway' => 'adyen',
	'countries' => [ '+' => [ 'JP', ], ],
	'payment_methods' => [
		'apple' => [ 'ALL' ],
		'cc' => [ 'visa', 'mc', 'amex', 'jcb' ],
	],
	// Setting form chooser weight very low so this form is not chosen as default
	'selection_weight' => 1,
];

$forms['adyen-nl'] = [
	'gateway' => 'adyen',
	'countries' => [ '+' => [ 'NL', ], ],
	'currencies' => [ '+' => [ 'EUR', ], ],
	'payment_methods' => [
		'apple' => [ 'ALL' ],
		'cc' => [ 'visa', 'mc', 'amex', 'jcb' ],
		'rtbt' => [ 'rtbt_ideal' ],
	],
	'selection_weight' => 101,
];

$forms['adyen-nl-recurring'] = [
	'gateway' => 'adyen',
	'countries' => [ '+' => [ 'NL', ], ],
	'currencies' => [ '+' => [ 'EUR', ], ],
	'payment_methods' => [
		'cc' => [ 'visa', 'mc', 'amex', 'jcb' ],
		'rtbt' => [ 'rtbt_ideal' ],
	],
	'recurring',
	'selection_weight' => 101,
];

$forms['adyen-nz'] = [
	'gateway' => 'adyen',
	'countries' => [ '+' => [ 'NZ' ], ],
	'currencies' => [ '+' => [ 'NZD', 'USD', ], ],
	'payment_methods' => [
		'apple' => [ 'ALL' ],
		'cc' => [ 'visa', 'mc', 'amex', ],
	],
	// Setting form chooser weight very low so this form is not chosen as default
	'selection_weight' => 1,
];

$forms['adyen-nz-recurring'] = [
	'gateway' => 'adyen',
	'countries' => [ '+' => [ 'NZ' ], ],
	'currencies' => [ '+' => [ 'NZD', 'USD', ], ],
	'payment_methods' => [
		'cc' => [ 'visa', 'mc', 'amex', ],
	],
	'recurring',
	// Setting form chooser weight very low so this form is not chosen as default
	'selection_weight' => 1,
];

$forms['adyen-se'] = [
	'gateway' => 'adyen',
	'countries' => [ '+' => [ 'SE' ], ],
	'currencies' => [ '+' => [ 'EUR', 'SEK', 'USD', ], ],
	'payment_methods' => [
		'apple' => [ 'ALL' ],
		'cc' => [ 'visa', 'mc', 'amex' ],
	],
	// Setting form chooser weight very low so this form is not chosen as default
	'selection_weight' => 1,
];

$forms['adyen-se-recurring'] = [
	'gateway' => 'adyen',
	'countries' => [ '+' => [ 'SE' ], ],
	'currencies' => [ '+' => [ 'EUR', 'SEK', 'USD', ], ],
	'payment_methods' => [
		'cc' => [ 'visa', 'mc', 'amex' ],
	],
	'recurring',
	// Setting form chooser weight very low so this form is not chosen as default
	'selection_weight' => 1,
];

$forms['adyen-ua'] = [
	'gateway' => 'adyen',
	'countries' => [ '+' => [ 'UA', ], ],
	'currencies' => [ '+' => [ 'USD', ], ],
	'payment_methods' => [
		'apple' => [ 'ALL' ],
		'cc' => [ 'visa', 'mc', 'amex' ],
	],
	// Setting form chooser weight very low so this form is not chosen as default
	'selection_weight' => 1,
];

$forms['adyen-us'] = [
	'gateway' => 'adyen',
	'countries' => [ '+' => [ 'US' ], ],
	'currencies' => [ '+' => [ 'USD', ], ],
	'payment_methods' => [
		'apple' => [ 'ALL' ],
		'cc' => [ 'visa', 'mc', 'amex', 'discover' ],
	],
	// Setting form chooser weight very low so this form is not chosen as default
	'selection_weight' => 1,
];

$forms['adyen-test'] = [
	'gateway' => 'adyen',
	'countries' => [ '+' => [ 'CA', 'FR', 'GB', 'IE', 'IL', 'JP', 'NL', 'UA', 'US', ], ],
	'currencies' => [ '+' => [ 'CAD', 'EUR', 'GBP', 'JPY', 'ILS', 'UAH', 'USD', ], ],
	'payment_methods' => [
		'apple' => [ 'ALL' ],
		'cc' => [ 'visa', 'mc', 'amex', 'discover', 'cb' ],
		'rtbt' => [ 'rtbt_ideal' ],
	],
	// Setting form chooser weight to zero so this form is not chosen as default
	'selection_weight' => 0,
];

$forms['adyen-test-recurring'] = [
	'gateway' => 'adyen',
	'countries' => [ '+' => [ 'CA', 'FR', 'GB', 'IE', 'IL', 'JP', 'NL', 'UA', 'US', ], ],
	'currencies' => [ '+' => [ 'CAD', 'EUR', 'GBP', 'JPY', 'ILS', 'UAH', 'USD', ], ],
	'payment_methods' => [
		'cc' => [ 'visa', 'mc', 'amex', 'discover', 'cb' ],
		'rtbt' => [ 'rtbt_ideal' ],
	],
	'recurring',
	// Setting form chooser weight to zero so this form is not chosen as default
	'selection_weight' => 0,
];

/* * ***********
 * Error Pages *
 * *********** */

$forms['error-default'] = [
	'gateway' => [ 'globalcollect', 'ingenico', 'adyen', 'amazon', 'astropay', 'paypal', 'paypal_ec' ],
	'special_type' => 'error',
];

$forms['error-noform'] = [
	'gateway' => [ 'globalcollect', 'ingenico', 'adyen', 'amazon', 'astropay', 'paypal', 'paypal_ec' ],
	'special_type' => 'error',
];

$forms['error-cc'] = [
	'gateway' => [ 'globalcollect', 'ingenico', 'adyen', 'astropay' ],
	'payment_methods' => [ 'cc' => 'ALL' ],
	'special_type' => 'error',
];

$forms['error-cancel'] = [
	'gateway' => [ 'globalcollect', 'ingenico', 'adyen', 'amazon', 'astropay', 'paypal', 'paypal_ec' ],
	'payment_status' => [ FinalStatus::CANCELLED ],
	'special_type' => 'error',
];

$wgDonationInterfaceAllowedHtmlForms = $forms;

unset( $forms );
unset( $form_dirs );
