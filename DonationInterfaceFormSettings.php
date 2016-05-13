<?php

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

/****************************
 * Bank Transfer - Two-Step *
 ****************************/

$forms_whitelist['bt'] = array(
	'file' => $form_dirs['gc'] . '/bt/bt.html',
	'gateway' => 'globalcollect',
	'countries' => array(
		'-' => 'VN',
	),
	'currencies' => array(
		'+' => array('AED', 'BGN', 'BHD', 'CAD', 'CLP', 'CZK', 'DKK', 'EEK', 'EGP', 'EUR', 'HRK',
					 'HUF', 'IDR', 'JPY', 'LBP', 'MXN', 'MYR', 'NOK', 'NZD', 'PEN', 'PLN',
					 'QAR', 'RON', 'RUB', 'SEK', 'THB', 'TRY', 'TWD', 'USD', 'ZAR'),
	),
	'payment_methods' => array('bt' => 'ALL')
);


/****************
 * Direct Debit *
 ****************/
//commenting out to disable the form, and the whole dd paymentmethod
//$forms_whitelist['dd'] = array(
//	'file' => $form_dirs['gc'] . '/dd/dd.html',
//	'gateway' => 'globalcollect',
//	'countries' => array(
//		'+' => array('AT', 'DE', 'ES', 'NL'),
//	),
//	'payment_methods' => array('dd' => 'ALL')
//);
//
//$forms_whitelist['dd-recurring'] = array_merge(
//	$forms_whitelist['dd'],
//	array(
//		 'file' => $form_dirs['gc'] . '/dd/dd-recurring.html',
//		 'recurring',
//	)
//);

/*********************
 * Electronic Wallet *
 *********************/

$forms_whitelist['ew-alipay'] = array(
	'file' => $form_dirs['gc'] . '/ew/ew-alipay.html',
	'gateway' => 'globalcollect',
	'payment_methods' => array('ew' => 'ew_alipay'),
	'countries' => array( '+' => array( 'CN', 'HK', ), ),
	'currencies' => array( '+' => array( 'CNY', 'HKD', ), ),
);


/*******************************
 * RealTime Banking - Two Step *
 *******************************/

$forms_whitelist['rtbt-sofo'] = array(
	'file' => $form_dirs['gc'] . '/rtbt/rtbt-sofo.html',
	'gateway' => 'globalcollect',
	'countries' => array(
		'+' => array( 'AT', 'BE', 'CH', 'DE' ),
		'-' => 'GB'
	),
	'currencies' => array( '+' => 'EUR' ),
	'payment_methods' => array('rtbt' => 'rtbt_sofortuberweisung'),
);

$forms_whitelist['rtbt-sofo-GB'] = array(
	'file' => $form_dirs['gc'] . '/rtbt/rtbt-sofo-GB.html',
	'gateway' => 'globalcollect',
	'countries' => array( '+' => 'GB' ),
	'currencies' => array( '+' => 'GBP' ),
	'payment_methods' => array('rtbt' => 'rtbt_sofortuberweisung')
);

$forms_whitelist['rtbt-ideal'] = array(
	'file' => $form_dirs['gc'] . '/rtbt/rtbt-ideal.html',
	'gateway' => 'globalcollect',
	'payment_methods' => array('rtbt' => 'rtbt_ideal'),
	'countries' => array( '+' => 'NL' ),
	'currencies' => array( '+' => 'EUR' ),
);

$forms_whitelist['rtbt-enets'] = array(
	'file' => $form_dirs['gc'] . '/rtbt/rtbt-enets.html',
	'gateway' => 'globalcollect',
	'payment_methods' => array('rtbt' => 'rtbt_enets'),
	'countries' => array( '+' => 'SG' ),
	'currencies' => array( '+' => 'SGD' ),
);

/*
$forms_whitelist['rtbt-eps'] = array(
	'file' => $form_dirs['gc'] . '/rtbt/rtbt-eps.html',
	'gateway' => 'globalcollect',
	'payment_methods' => array('rtbt' => 'rtbt_eps')
);
*/

$forms_whitelist['rtbt-ideal-noadd'] = array(
	'file' => $form_dirs['gc'] . '/rtbt/rtbt-ideal-noadd.html',
	'gateway' => 'globalcollect',
	'payment_methods' => array('rtbt' => 'rtbt_ideal'),
	'countries' => array( '+' => 'NL' ),
	'currencies' => array( '+' => 'EUR' ),
);


/********
 * BPAY *
 ********/

$forms_whitelist['obt-bpay'] = array(
	'file' => $form_dirs['gc'] . '/obt/obt-bpay.html',
	'gateway' => 'globalcollect',
	'countries' => array( '+' => 'AU'),
	'currencies' => array( '+' => 'AUD'),
	'payment_methods' => array('obt' => 'bpay')
);

/*****************************
 * Credit Card - Single Step *
 *****************************/
/*
$forms_whitelist['webitects_2_3step'] = array(
	'file' => $form_dirs['gc'] . '/webitects_2_3step.html',
	'gateway' => 'globalcollect',
	'payment_methods' => array('cc' => array( 'visa', 'mc' ))
);

$forms_whitelist['webitects_2_3step-CA'] = array(
	'file' => $form_dirs['gc'] . '/webitects_2_3step-CA.html',
	'gateway' => 'globalcollect',
	'countries' => array( '+' => 'CA' ),
	'payment_methods' => array('cc' => array( 'visa', 'mc' ))
);

$forms_whitelist['webitects_2_3stepB-US'] = array(
	'file' => $form_dirs['gc'] . '/webitects_2_3stepB-US.html',
	'gateway' => 'globalcollect',
	'countries' => array( '+' => 'US' ),
	'payment_methods' => array('cc' => array( 'visa', 'mc' ))
);
*/


/**************************
 * Credit Card - Two Step *
 **************************/

$forms_whitelist['webitects2nd'] = array(
	'file' => $form_dirs['gc'] . '/webitects2nd.html',
	'gateway' => 'globalcollect',
//	'payment_methods' => array('cc' => array( 'visa', 'mc' ))
);

$forms_whitelist['webitects2nd-US'] = array(
	'file' => $form_dirs['gc'] . '/webitects2nd-US.html',
	'gateway' => 'globalcollect',
//	'payment_methods' => array('cc' => array( 'visa', 'mc' ))
);

$forms_whitelist['webitects2nd_green-US'] = array(
	'file' => $form_dirs['gc'] . '/webitects2nd_green-US.html',
	'gateway' => 'globalcollect',
//	'payment_methods' => array('cc' => array( 'visa', 'mc' ))
);

$forms_whitelist['webitects2nd-amex'] = array(
	'file' => $form_dirs['gc'] . '/webitects2nd-amex.html',
	'gateway' => 'globalcollect',
//	'payment_methods' => array('cc' => array( 'visa', 'mc', 'amex' ))
);


/**********************
 * Credit Card - Misc *
 **********************/

$forms_whitelist['cc-vmad'] = array(
	'file' => $form_dirs['gc'] . '/cc/cc-vmad.html',
	'gateway' => 'globalcollect',
	'payment_methods' => array('cc' => array( 'visa', 'mc', 'amex', 'discover' )),
	'countries' => array(
		'+' => array( 'US', ),
	),
);

$forms_whitelist['cc-vjma'] = array(
	'file' => $form_dirs['gc'] . '/cc/cc-vjma.html',
	'gateway' => 'globalcollect',
	'payment_methods' => array('cc' => array( 'visa', 'jcb', 'mc', 'amex' )),
	'countries' => array(
		'+' => array( 'JP', ),
	),
	'selection_weight' => 10,
);

$forms_whitelist['cc-jvma'] = array(
	'file' => $form_dirs['gc'] . '/cc/cc-jvma.html',
	'gateway' => 'globalcollect',
	'payment_methods' => array('cc' => array( 'jcb', 'visa', 'mc', 'amex' )),
	'countries' => array(
		'+' => array( 'JP', ),
	),
	'selection_weight' => 0,
);


$forms_whitelist['cc-vmaj'] = array(
	'file' => $form_dirs['gc'] . '/cc/cc-vmaj.html',
	'gateway' => 'globalcollect',
	'payment_methods' => array('cc' => array( 'visa', 'mc', 'amex', 'jcb' )),
	'countries' => array(
		'+' => array( 'AD', 'AT', 'AU', 'BE', 'BH', 'DE', 'EC', 'ES', 'FI', 'GB',
					  'GF', 'GR', 'HK', 'IE', 'IT', 'KR', 'LU', 'MY', 'NL', 'PR', 'PT',
					  'SG', 'SI', 'SK', 'TH', 'TW', ),
	),
);

$forms_whitelist['cc-vmd'] = array(
	'file' => $form_dirs['gc'] . '/cc/cc-vmd.html',
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
	'file' => $form_dirs['gc'] . '/cc/cc-vmj.html',
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
	'file' => $form_dirs['gc'] . '/cc/cc-vma.html',
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
	'file' => $form_dirs['gc'] . '/cc/cc-vm.html',
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
	'file' => $form_dirs['gc'] . '/cc/cc-a.html',
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
	'file' => $form_dirs['gc'] . '/cc/cc.html',
	'gateway' => 'globalcollect',
	'payment_methods' => array('cc' => 'ALL'),
	'countries' => array('-' => 'VN')
);

/* Special case for Vietnam while GC is still having problems.
 * In the meantime: Visa & Mastercard, USD-only.
 */
$forms_whitelist['cc-vietnam'] = array (
	'file' => $form_dirs['gc'] . '/cc/cc-vm.html',
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
	'file' => $form_dirs['gc'] . '/rcc/rcc-vmad.html',
	'gateway' => 'globalcollect',
	'payment_methods' => array('cc' => array( 'visa', 'mc', 'amex', 'discover' )),
	'recurring',
	'countries' => array(
		'+' => $forms_whitelist['cc-vmad']['countries']['+']
	)
);

$forms_whitelist['rcc-vmaj'] = array(
	'file' => $form_dirs['gc'] . '/rcc/rcc-vmaj.html',
	'gateway' => 'globalcollect',
	'payment_methods' => array('cc' => array( 'visa', 'mc', 'amex', 'jcb' )),
	'recurring',
	'countries' => array(
		'+' => $forms_whitelist['cc-vmaj']['countries']['+']
	)
);

$forms_whitelist['rcc-vmd'] = array(
	'file' => $form_dirs['gc'] . '/rcc/rcc-vmd.html',
	'gateway' => 'globalcollect',
	'payment_methods' => array('cc' => array( 'visa', 'mc', 'discover' )),
	'recurring',
	'countries' => array(
		'+' => $forms_whitelist['cc-vmd']['countries']['+']
	)
);

$forms_whitelist['rcc-vmj'] = array(
	'file' => $form_dirs['gc'] . '/rcc/rcc-vmj.html',
	'gateway' => 'globalcollect',
	'payment_methods' => array('cc' => array( 'visa', 'mc', 'jcb' )),
	'recurring',
	'countries' => array(
		'+' => $forms_whitelist['cc-vmj']['countries']['+']
	)
);

$forms_whitelist['rcc-vma'] = array(
	'file' => $form_dirs['gc'] . '/rcc/rcc-vma.html',
	'gateway' => 'globalcollect',
	'payment_methods' => array('cc' => array( 'visa', 'mc', 'amex' )),
	'recurring',
	'countries' => array(
		'+' => $forms_whitelist['cc-vma']['countries']['+']
	)
);

$forms_whitelist['rcc-vm'] = array(
	'file' => $form_dirs['gc'] . '/rcc/rcc-vm.html',
	'gateway' => 'globalcollect',
	'payment_methods' => array('cc' => array( 'visa', 'mc' )),
	'recurring',
	'countries' => array(
		'+' => $forms_whitelist['cc-vm']['countries']['+']
	)
);

$forms_whitelist['rcc'] = array(
	'file' => $form_dirs['gc'] . '/rcc/rcc.html',
	'gateway' => 'globalcollect',
	'payment_methods' => array('cc' => 'ALL'),
	'recurring'
);

/*************************
 * Boletos
 *************************/

$forms_whitelist['boletos'] = array(
	'file' => $form_dirs['gc'] . '/cash/boletos.html',
	'gateway' => 'globalcollect',
	'payment_methods' => array('cash' => 'boleto'),
	'countries' => array(
		'+' => array('BR'),
	),
	'currencies' => array(
		'+' => array('BRL'),
	),
);

/*************************
 * Paypal
 *************************/

$forms_whitelist['paypal'] = array(
	'file' => $form_dirs['paypal'] . '/paypal.html',
	'gateway' => 'paypal',
	'payment_methods' => array('paypal' => 'ALL'),
	// FIXME: 'redirect' is not necessary?
);

$forms_whitelist['paypal-recurring'] = array(
	'file' => $form_dirs['paypal'] . '/paypal-recurring.html',
	'gateway' => 'paypal',
	'payment_methods' => array('paypal' => 'ALL'),
	'recurring',
);

// FIXME: This is a dummy entry to allow FormChooser to route to astropay
// In the Mustache-ridden future, with a diminished form population,
// the chooser will route to the correct gateway based on gateway settings,
// and simply pass along any ffname from the banner to allow A/B testing
/************
 * Astropay *
 ************/
$forms_whitelist['astropay'] = array(
	'file' => __DIR__ . '/gateway_forms/mustache/index.html.mustache',
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
	'file' => __DIR__ . '/gateway_forms/mustache/index.html.mustache',
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
	'file' => __DIR__ . '/gateway_forms/mustache/index.html.mustache',
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
	'file' => __DIR__ . '/gateway_forms/mustache/index.html.mustache',
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
	'file' => __DIR__ . '/gateway_forms/mustache/index.html.mustache',
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
	'file' => __DIR__ . '/gateway_forms/mustache/index.html.mustache',
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
// This is at the bottom so that we prefer GC over adyen
$forms_whitelist['adyen'] = array(
	'file' => __DIR__ . '/gateway_forms/mustache/index.html.mustache',
	'gateway' => 'adyen',
	'countries' => array( '+' => 'US', 'IL', 'FR' ),
	'currencies' => array( '+' => 'USD', 'ILS', 'EUR' ),
	'payment_methods' => array('cc' => array( 'visa', 'mc', 'amex', 'discover', 'cb' )),
	'selection_weight' => 0
);

/**********
 * Worldpay *
 **********/
// This is at the bottom so that we prefer GC over Worldpay
#$forms_whitelist['worldpay'] = array(
#	'file' => $form_dirs['worldpay'] . '/worldpay.html',
#	'gateway' => 'worldpay',
#	'countries' => array( '+' => array( 'AU', 'BE', 'CA', 'FR', 'GB', 'IL', 'NZ', 'US' ) ),
#	'currencies' => array( '+' => 'ALL' ),
#	'payment_methods' => array( 'cc' => 'ALL' ),
#	'selection_weight' => 10
#);

// Worldpay ESOP feat iframe
$forms_whitelist['wp-if'] = array(
	'file' => __DIR__ . '/gateway_forms/mustache/index.html.mustache',
	'gateway' => 'worldpay',
	'countries' => array( '+' => array( 'AU', 'BE', 'CA', 'FR', 'GB', 'IL', 'NZ', 'US' ) ),
	'currencies' => array( '+' => 'ALL' ),
	'payment_methods' => array( 'cc' => 'ALL' ),
	'selection_weight' => 0
);

/*************************
 * Worldpay Form Tests *
 *************************/

#$worldpay_test_spec = array(
#	'file' => $form_dirs['worldpay'] . '/worldpay-test.html',
#	'selection_weight' => 0,
#) + $forms_whitelist['worldpay'];
#
#//until we are ready for US testing with the other test forms, we have to limit to the old list.
#$worldpay_test_spec['countries'] = array( '+' => array( 'BE', 'FR', 'US' ) );
#
#$forms_whitelist['wp-sn'] = $worldpay_test_spec;
#$forms_whitelist['wp-sw'] = $worldpay_test_spec;
#$forms_whitelist['wp-fud'] = $worldpay_test_spec;
#$forms_whitelist['wp-btnb'] = $worldpay_test_spec;
#$forms_whitelist['wp-btng'] = $worldpay_test_spec;
#
#
#$forms_whitelist['wp-ddcc'] = array(
#	   'file' => $form_dirs['worldpay'] . '/worldpay-dd-test.html',
#	   'gateway' => 'worldpay',
#	   'countries' => array( '+' => array( 'BE', 'FR', 'US' ) ),
#	   'currencies' => array( '+' => 'ALL' ),
#	   'payment_methods' => array( 'cc' => 'ALL' ),
#	   'selection_weight' => 0
#);

/* * ***********
 * Error Pages *
 * *********** */

$forms_whitelist['error-default'] = array (
	'file' => $form_dirs['default'] . '/error-cc.html',
	'gateway' => array ( 'globalcollect', 'adyen', 'amazon', 'astropay', 'paypal', 'worldpay' ),
	'special_type' => 'error', //buuuurble
);

$forms_whitelist['error-noform'] = array (
	'file' => $form_dirs['default'] . '/error-noform.html',
	'gateway' => array ( 'globalcollect', 'adyen', 'amazon', 'astropay', 'paypal', 'worldpay' ),
	'special_type' => 'error',
);

$forms_whitelist['error-cc'] = array (
	'file' => $form_dirs['default'] . '/error-cc.html',
	'gateway' => array ( 'globalcollect', 'adyen', 'astropay', 'worldpay' ),
	'payment_methods' => array ( 'cc' => 'ALL' ),
	'special_type' => 'error',
);

$wgDonationInterfaceAllowedHtmlForms = $forms_whitelist;

unset( $forms_whitelist );
unset( $form_dirs );
