<?php
//actually, as a maintenance script, this totally is a valid entry point. 
// FIXME: Prevent web access even if the security is misconfigured so this is runnable.

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../../..';
}

//If you get errors on this next line, set (and export) your MW_INSTALL_PATH var. 
require_once( "$IP/maintenance/Maintenance.php" );

class OrphanMaintenance extends Maintenance {
	public function execute() {
		$rectifier = new GlobalCollectOrphanRectifier_pooled();
		$rectifier->processOrphans();
	}
}

$maintClass = 'OrphanMaintenance';
require_once RUN_MAINTENANCE_IF_MAIN;
