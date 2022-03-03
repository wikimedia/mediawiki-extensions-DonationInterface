#!/usr/bin/env php
<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../../..';
}

// If you get errors on this next line, set (and export) your MW_INSTALL_PATH var.
require_once "$IP/maintenance/Maintenance.php";

// Cancels PayPal subscriptions listed in a file.
// Currently takes a CSV with no header and just one column: subscription_id
class PaypalCancelMaintenance extends Maintenance {
	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'Donation Interface' );

		$this->addOption( 'file', 'Read refund detail in from a file',
			true, true, 'f' );
	}

	public function execute() {
		global $wgDonationInterfaceEnableCustomFilters;

		// don't run fraud checks for cancels
		$wgDonationInterfaceEnableCustomFilters = false;
		$filename = $this->getOption( 'file' );
		$file = fopen( $filename, 'r' );
		if ( !$file ) {
			$this->error( 'Could not find cancellation file: ' . $filename, true );
		}
		while ( $refund = fgetcsv( $file ) ) {
			if ( count( $refund ) !== 1 ) {
				$this->error( 'Cancellation lines must have exactly 1 field: subscription_id', true );
			}
			$subscription_id = $refund[0];
			$gateway_opts = [
				'batch_mode' => true,
				'external_data' => [
					'payment_method' => 'paypal',
					'subscr_id' => $subscription_id,
					'currency' => 'USD', // dummy
					'amount' => 1, // dummy
					'contribution_tracking_id' => 1 // dummy, avoid getting a new one
				],
			];

			$adapter = new PaypalExpressAdapter( $gateway_opts );
			$this->output( "Cancelling subscription $subscription_id\n" );
			$result = $adapter->cancelSubscription();

			if ( $result->isFailed() ) {
				$this->error( "Failed cancelling subscription $subscription_id " . print_r( $result->getErrors(), true ) );
			} else {
				$this->output( "Successfully cancelled subscription $subscription_id\n" );
			}
		}
		fclose( $file );
	}
}

$maintClass = PaypalCancelMaintenance::class;
require_once RUN_MAINTENANCE_IF_MAIN;
