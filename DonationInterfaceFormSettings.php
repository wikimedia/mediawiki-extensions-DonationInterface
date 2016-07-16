<?php
// NOTE: 'dependencies' only implemented for RapidHTML, probably shouldn't for Mustache
global $wgDonationInterfaceAllowedHtmlForms, $wgDonationInterfaceFormDirs;
/**
 * Some setup vars to make our lives a little easier.
 * These are unset at the end of the file.
 */
$forms_whitelist = array();
$form_dirs = $wgDonationInterfaceFormDirs;

/*
 * Amazon dummy config - see AstroPay
 */
$forms_whitelist['amazon'] = array(
	'gateway' => 'amazon',
	'payment_methods' => array('amazon' => 'ALL'),
);

$forms_whitelist['amazon-recurring'] = array(
	'gateway' => 'amazon',
	'payment_methods' => array('amazon' => 'ALL'),
	'recurring',
);


/*******************************
 * RealTime Banking - Two Step *
 *******************************/

$forms_whitelist['rtbt-ideal'] = array(
	'gateway' => 'globalcollect',
	'payment_methods' => array('rtbt' => 'rtbt_ideal'),
	'countries' => array( '+' => 'NL' ),
	'currencies' => array( '+' => 'EUR' ),
);


/********
 * BPAY *
 ********/

$forms_whitelist['obt-bpay'] = array(
	'gateway' => 'globalcollect',
	'countries' => array( '+' => 'AU'),
	'currencies' => array( '+' => 'AUD'),
	'payment_methods' => array('obt' => 'bpay')
);


/**********************
 * Credit Card - Misc *
 **********************/

$forms_whitelist['cc-vmad'] = array(
	'gateway' => 'globalcollect',
	'payment_methods' => array('cc' => array( 'visa', 'mc', 'amex', 'discover' )),
	'countries' => array(
		'+' => array( 'US', ),
	),
);

$forms_whitelist['cc-vjma'] = array(
	'gateway' => 'globalcollect',
	'payment_methods' => array('cc' => array( 'visa', 'jcb', 'mc', 'amex' )),
	'countries' => array(
		'+' => array( 'JP', ),
	),
	'selection_weight' => 10,
);

$forms_whitelist['cc-jvma'] = array(
	'gateway' => 'globalcollect',
	'payment_methods' => array('cc' => array( 'jcb', 'visa', 'mc', 'amex' )),
	'countries' => array(
		'+' => array( 'JP', ),
	),
	'selection_weight' => 0,
);


$forms_whitelist['cc-vmaj'] = array(
	'gateway' => 'globalcollect',
	'payment_methods' => array('cc' => array( 'visa', 'mc', 'amex', 'jcb' )),
	'countries' => array(
		'+' => array( 'AD', 'AT', 'AU', 'BE', 'BH', 'DE', 'EC', 'ES', 'FI', 'GB',
					  'GF', 'GR', 'HK', 'IE', 'IT', 'KR', 'LU', 'MY', 'NL', 'PR', 'PT',
					  'SG', 'SI', 'SK', 'TH', 'TW', ),
	),
);

$forms_whitelist['cc-vmd'] = array(
	'gateway' => 'globalcollect',
	'payment_methods' => array('cc' => array( 'visa', 'mc', 'discover' )),
	'countries' => array(
		// Array merge with cc-vmad as fallback in case 'a' goes down
		'+' => array_merge(
			$forms_whitelist['cc-vmad']['countries']['+'],
			array() // as of right now, nothing specific here
		),
	),
);

$forms_whitelist['cc-vmj'] = array(
	'gateway' => 'globalcollect',
	'payment_methods' => array('cc' => array( 'visa', 'mc', 'jcb' )),
	'countries' => array(
		// Array merge with cc-vmaj as fallback in case 'a' goes down
		'+' => array_merge(
			$forms_whitelist['cc-vmaj']['countries']['+'],
			array( 'BR', 'ID', 'PH', )
		),
	),
);

$forms_whitelist['cc-vma'] = array(
	'gateway' => 'globalcollect',
	'payment_methods' => array('cc' => array( 'visa', 'mc', 'amex' )),
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
);

$forms_whitelist['cc-vm'] = array(
	'gateway' => 'globalcollect',
	'payment_methods' => array('cc' => array( 'visa', 'mc' )),
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
);

$forms_whitelist['cc-a'] = array(
	'gateway' => 'globalcollect',
	'payment_methods' => array('cc' => array( 'amex' )),
	'countries' => array(
		'+' => array_merge(
			$forms_whitelist['cc-vma']['countries']['+'],
			array() // as of right now, nothing specific here
		)
	),
);

$forms_whitelist['cc'] = array(
	'gateway' => 'globalcollect',
	'payment_methods' => array('cc' => 'ALL'),
	'countries' => array('-' => 'VN')
);

// FIXME: is this still needed?
/* Special case for Vietnam while GC is still having problems.
 * In the meantime: Visa & Mastercard, USD-only.
 */
$forms_whitelist['cc-vietnam'] = array (
	'gateway' => 'globalcollect',
	'payment_methods' => array ( 'cc' => array ( 'visa', 'mc' ) ),
	'countries' => array ( '+' => 'VN' ),
	'currencies' => array ( '+' => 'USD' ),
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
	'gateway' => 'globalcollect',
	'payment_methods' => array('cc' => array( 'visa', 'mc', 'amex', 'discover' )),
	'recurring',
	'countries' => array(
		'+' => $forms_whitelist['cc-vmad']['countries']['+']
	)
);

$forms_whitelist['rcc-vmaj'] = array(
	'gateway' => 'globalcollect',
	'payment_methods' => array('cc' => array( 'visa', 'mc', 'amex', 'jcb' )),
	'recurring',
	'countries' => array(
		'+' => $forms_whitelist['cc-vmaj']['countries']['+']
	)
);

$forms_whitelist['rcc-vmd'] = array(
	'gateway' => 'globalcollect',
	'payment_methods' => array('cc' => array( 'visa', 'mc', 'discover' )),
	'recurring',
	'countries' => array(
		'+' => $forms_whitelist['cc-vmd']['countries']['+']
	)
);

$forms_whitelist['rcc-vmj'] = array(
	'gateway' => 'globalcollect',
	'payment_methods' => array('cc' => array( 'visa', 'mc', 'jcb' )),
	'recurring',
	'countries' => array(
		'+' => $forms_whitelist['cc-vmj']['countries']['+']
	)
);

$forms_whitelist['rcc-vma'] = array(
	'gateway' => 'globalcollect',
	'payment_methods' => array('cc' => array( 'visa', 'mc', 'amex' )),
	'recurring',
	'countries' => array(
		'+' => $forms_whitelist['cc-vma']['countries']['+']
	)
);

$forms_whitelist['rcc-vm'] = array(
	'gateway' => 'globalcollect',
	'payment_methods' => array('cc' => array( 'visa', 'mc' )),
	'recurring',
	'countries' => array(
		'+' => $forms_whitelist['cc-vm']['countries']['+']
	)
);

$forms_whitelist['rcc'] = array(
	'gateway' => 'globalcollect',
	'payment_methods' => array('cc' => 'ALL'),
	'recurring'
);

/*************************
 * Paypal
 *************************/

$forms_whitelist['paypal'] = array(
	'gateway' => 'paypal',
	'payment_methods' => array('paypal' => 'ALL'),
);

$forms_whitelist['paypal-recurring'] = array(
	'gateway' => 'paypal',
	'payment_methods' => array('paypal' => 'ALL'),
	'recurring',
);

// FIXME: This is a dummy entry to allow FormChooser to route to astropay
// In the Mustache-ridden future, with a diminished form population,
// the chooser will route to the correct gateway based on gateway settings,
// and simply pass along any ffname from the banner to allow A/B testing
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
#			'visa',
#			'mc',
#			'amex',
#			'magna',
#			'diners',
#			'cmr',
#			'presto',
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
$forms_whitelist['astropay-uy'] = array(
	'gateway' => 'astropay',
	'countries' => array( '+' => 'UY' ),
	'currencies' => array( '+' => 'UYU' ),
	'payment_methods' => array(
		'cash' => array(
			'cash_red_pagos',
		),
	),
	'selection_weight' => 110,
);

/**********
 * Adyen *
 **********/
$forms_whitelist['adyen'] = array(
	'gateway' => 'adyen',
	'countries' => array( '+' => array( 'IL', ), ),
	'currencies' => array( '+' => array( 'ILS', ), ),
	'payment_methods' => array(
		'cc' => array( 'visa', 'mc', 'amex', 'discover', ),
	),
	'selection_weight' => 110,
);

$forms_whitelist['adyen-test'] = array(
	'gateway' => 'adyen',
	'countries' => array( '+' => array( 'FR', 'IL', 'JP', 'UA', 'US', ), ),
	'currencies' => array( '+' => array( 'EUR', 'ILS', 'JPY', 'UAH', 'USD', ), ),
	'payment_methods' => array(
		'cc' => array( 'visa', 'mc', 'amex', 'discover', 'cb', 'jcb', ),
	),
	// Setting form chooser weight to zero so this form is not chosen as default
	'selection_weight' => 0,
);

/* * ***********
 * Error Pages *
 * *********** */

$forms_whitelist['error-default'] = array (
	'gateway' => array ( 'globalcollect', 'adyen', 'amazon', 'astropay', 'paypal', 'worldpay' ),
	'special_type' => 'error',
);

$forms_whitelist['error-noform'] = array (
	'gateway' => array ( 'globalcollect', 'adyen', 'amazon', 'astropay', 'paypal', 'worldpay' ),
	'special_type' => 'error',
);

$forms_whitelist['error-cc'] = array (
	'gateway' => array ( 'globalcollect', 'adyen', 'astropay', 'worldpay' ),
	'payment_methods' => array ( 'cc' => 'ALL' ),
	'special_type' => 'error',
);

$wgDonationInterfaceAllowedHtmlForms = $forms_whitelist;

unset( $forms_whitelist );
unset( $form_dirs );
