#!/usr/bin/env php
<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../../..';
}

// If you get errors on this next line, set (and export) your MW_INSTALL_PATH var.
require_once "$IP/maintenance/Maintenance.php";

// Refunds credit card transactions listed in a file.
// Currently takes a CSV with no header and columns in this order:
// order_id, merchant_reference, effort_id, payment_submethod, country, currency, amount
class GlobalCollectGetDirectory extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'Donation Interface' );
	}

	public function execute() {
		$gateway_opts = [
			'batch_mode' => true,
			'external_data' => [
				'payment_method' => 'rtbt',
				'payment_submethod' => 'rtbt_ideal',
				'country' => 'NL',
				'currency' => 'EUR',

				// FIXME: nonsense to satisfy validation
				'amount' => 1,
			],
		];

		$this->output( "Querying available banks.\n" );
		$adapter = new GlobalCollectAdapter( $gateway_opts );
		$result = $adapter->do_transaction( 'GET_DIRECTORY' );

		if ( $result->getErrors() ) {
			$this->error( "API call failed:" . implode( ', ', $result->getErrors() ) );
			return;
		}

		$this->output( "Barbarically raw response:\n" . $result->getRawResponse() );
	}
}

$maintClass = GlobalCollectGetDirectory::class;
require_once RUN_MAINTENANCE_IF_MAIN;
