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
	public function execute() {
		$this->requireExtension( 'Donation Interface' );

		$gateway_opts = array(
			'batch_mode' => true,
			'external_data' => array(
				'payment_method' => 'rtbt',
				'payment_submethod' => 'rtbt_ideal',
				'country' => 'NL',
				'currency' => 'EUR',

				// FIXME: nonsense to satisfy validation
				'amount' => 1,
			),
		);

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

$maintClass = 'GlobalCollectGetDirectory';
require_once RUN_MAINTENANCE_IF_MAIN;
