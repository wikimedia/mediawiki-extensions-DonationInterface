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
// order_id, gateway_txn_id, subscription_id, currency, amount
class PaypalRefundMaintenance extends Maintenance {
	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'Donation Interface' );

		$this->addOption( 'file', 'Read refund detail in from a file',
			true, true, 'f' );
		$this->addOption( 'unsubscribe', 'Cancel the subscription this charge is a part of',
			false, false );
	}

	public function execute() {
		global $wgDonationInterfaceEnableCustomFilters;

		// don't run fraud checks for refunds
		$wgDonationInterfaceEnableCustomFilters = false;

		$isUnsubscribing = $this->getOption( 'unsubscribe' );

		$filename = $this->getOption( 'file' );
		$file = fopen( $filename, 'r' );
		if ( !$file ) {
			$this->fatalError( 'Could not find refund file: ' . $filename );
		}
		while ( $refund = fgetcsv( $file ) ) {
			if ( count( $refund ) !== 5 ) {
				$this->fatalError( 'Refund lines must have exactly 5 fields: order_id, gateway_txn_id, subscription_id, currency, amount' );
			}
			$oid = $refund[0];
			$gateway_txn_id = $refund[1];
			$subscription_id = $refund[2];
			$gateway_opts = [
				'batch_mode' => true,
				'external_data' => [
					'payment_method' => 'paypal',
					'currency' => $refund[3],
					'amount' => $refund[4]
				],
			];

			$adapter = new PaypalExpressAdapter( $gateway_opts );

			$this->output( "Refunding transaction $oid from gateway transaction $oid\n" );
			$adapter->addRequestData( [ 'order_id' => $oid, 'gateway_txn_id' => $gateway_txn_id, 'subscr_id' => $subscription_id ] );
			$result = $adapter->doRefund();
			if ( $result->isFailed() || count( $result->getErrors() ) > 0 ) {
				$this->error( "Failed refunding transaction $oid " . print_r( $result->getErrors(), true ) );
			} else {
				$this->output( "Successfully refunded transaction $oid\n" );
			}

			if ( $isUnsubscribing ) {
				$this->output( "Cancelling subscription $subscription_id from gateway transaction $oid\n" );
				$result = $adapter->cancelSubscription();

				if ( $result->isFailed() ) {
					$this->error( "Failed cancelling subscription $oid " . print_r( $result->getErrors(), true ) );
				} else {
					$this->output( "Successfully cancelled subscription $oid\n" );
				}
			}
		}
		fclose( $file );
	}
}

$maintClass = PaypalRefundMaintenance::class;
require_once RUN_MAINTENANCE_IF_MAIN;
