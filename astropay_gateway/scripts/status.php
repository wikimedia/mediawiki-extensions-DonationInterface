#!/usr/bin/env php
<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../../..';
}

// If you get errors on this next line, set (and export) your MW_INSTALL_PATH var.
require_once "$IP/maintenance/Maintenance.php";

// Asks the AstroPay API for info about a particular donation
class AstroPayStatusQuery extends Maintenance {
	public function __construct() {
		parent::__construct();

		if ( method_exists( $this, 'requireExtension' ) ) {
			$this->requireExtension( 'Donation Interface' );
		}
		$this->addArg( 'id', 'Contribution tracking ID', true );
	}

	public function execute() {
		$oid = $this->getArg( 0 );
		$parts = explode( '.', $oid );
		$ctid = $parts[0];
		$gateway_opts = [
			'batch_mode' => true,
			'external_data' => [
				'order_id' => $this->getArg( 'id' ),
				'contribution_tracking_id' => $ctid,
				// Dummy data to satisfy validation :P
				'payment_method' => 'cc',
				'country' => 'BR',
				'currency' => 'BRL',
				'amount' => 10,
				'email' => 'dummy@example.org',
			],
		];
		$this->output( "Checking order $oid\n" );
		$adapter = new AstroPayAdapter( $gateway_opts );
		$result = $adapter->do_transaction( 'PaymentStatus' );
		$this->output( print_r( $result->getData(), true ) );
	}
}

$maintClass = AstroPayStatusQuery::class;
require_once RUN_MAINTENANCE_IF_MAIN;
