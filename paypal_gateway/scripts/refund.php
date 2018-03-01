<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../../..';
}

// If you get errors on this next line, set (and export) your MW_INSTALL_PATH var.
require_once "$IP/maintenance/Maintenance.php";

// Refunds credit card transactions listed in a file.
// Currently takes a CSV with no header and columns in this order:
// order_id, gateway_txn_id, subscription_id, currency
class PaypalLegacyRefundMaintenance extends Maintenance {
	public function __construct() {
		parent::__construct();

		if ( method_exists( $this, 'requireExtension' ) ) {
			$this->requireExtension( 'Donation Interface' );
		}
		$this->addOption( 'file', 'Read refund detail in from a file',
			true, true, 'f' );
		$this->addOption( 'unsubscribe', 'Cancel the subscription this charge is a part of',
			false, false );
	}

	public function execute() {
		global $wgDonationInterfaceEnableCustomFilters;

		// don't run fraud checks for refunds
		$wgDonationInterfaceEnableCustomFilters = false;

		$isRefund = $this->getOption( 'refund' );
		$isUnsubscribing = $this->getOption( 'unsubscribe' );

		$filename = $this->getOption( 'file' );
		if ( !( $file = fopen( $filename, 'r' ) ) ) {
			$this->error( 'Could not find refund file: ' . $filename, true );
		}
		while ( $refund = fgetcsv( $file ) ) {
			if ( count( $refund ) !== 4 ) {
				$this->error( 'Refund lines must have exactly 4 fields: order_id, gateway_txn_id, subscription_id, currency', true );
			}
			$subscription_id = $refund[2];
			$gateway_opts = array(
				'batch_mode' => true,
				'external_data' => array(
					'payment_method' => 'paypal',
					'currency' => $refund[3],
				),
			);

			$this->output( "Cancelling subscription $subscription_id from order $oid\n" );
			$adapter = new PaypalExpressAdapter( $gateway_opts );
			if ( $isRefund ) {
				$adapter->addRequestData( array( 'order_id' => $refund[0], 'gateway_txn_id' => $refund[1] ) );
				$result = $adapter->doRefund();
			}

			if ( $result->isFailed() ) {
				$this->error( "Failed refunding transaction $oid" . print_r( $result->getErrors(), true ) );
			} else {
				$this->output( "Successfully refunded transaction $oid\n" );
			}

			if ( $isUnsubscribing ) {
				$adapter->addRequestData( array( 'subscription_id' => $subscription_id ) );
				$result = $adapter->cancelSubscription( $subscription_id );

				if ( $result->isFailed() ) {
					$this->error( "Failed cancelling subscription $oid" . print_r( $result->getErrors(), true ) );
				} else {
					$this->output( "Successfully cancelled subscription $oid\n" );
				}
			}
		}
		fclose( $file );
	}
}

$maintClass = 'GlobalCollectRefundMaintenance';
require_once RUN_MAINTENANCE_IF_MAIN;
