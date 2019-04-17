#!/usr/bin/env php
<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../../..';
}

// If you get errors on this next line, set (and export) your MW_INSTALL_PATH var.
require_once "$IP/maintenance/Maintenance.php";

// Look up status codes for a batch of transactions
// Input file must be either a two column CSV with order ID and effort ID or
// a single column with one order ID per line, which will assume effort ID = 1
class IngenicoGetOrderStatusMaintenance extends Maintenance {
	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'Donation Interface' );

		$this->addOption( 'file', 'Read order IDs in from a file',
			true, true, 'f' );
	}

	public function execute() {
		global $wgGlobalCollectGatewayEnableCustomFilters;

		// don't run fraud checks
		$wgGlobalCollectGatewayEnableCustomFilters = false;

		$filename = $this->getOption( 'file' );
		if ( !( $file = fopen( $filename, 'r' ) ) ) {
			$this->error( 'Could not find order id file: ' . $filename, true );
		}
		while ( $order = fgetcsv( $file ) ) {
			$effort_id = 1;
			if ( count( $order ) === 2 ) {
				$effort_id = $order[1];
			} elseif ( count( $order ) !== 1 ) {
				$this->error( 'Input lines must have either one or two columns', true );
			}
			$oid = $order[0];
			$gateway_opts = [
				'batch_mode' => true,
				'external_data' => [
					'order_id' => $oid,
					'effort_id' => $effort_id,
					'payment_method' => 'cc',
					'payment_submethod' => 'visa',
					'currency_code' => 'USD',
					'amount' => 500,
				],
			];

			$this->output( "Looking up transaction $oid\n" );
			$adapter = new GlobalCollectAdapter( $gateway_opts );
			// FIXME: effort_id is clobbered in setGatewayDefaults
			$adapter->addRequestData( [ 'effort_id' => $effort_id ] );
			$result = $adapter->do_transaction( 'GET_ORDERSTATUS' );

			// TODO: better formatting?
			$this->output( print_r( $result->getData(), true ) );
		}
		fclose( $file );
	}
}

$maintClass = IngenicoGetOrderStatusMaintenance::class;
require_once RUN_MAINTENANCE_IF_MAIN;
