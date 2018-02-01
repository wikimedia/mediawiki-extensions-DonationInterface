<?php
// XXX only deployed for a quick debuggery party

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../../..';
}

// If you get errors on this next line, set (and export) your MW_INSTALL_PATH var.
require_once "$IP/maintenance/Maintenance.php";

class TestCrash extends Maintenance {
	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'Donation Interface' );
		$this->addOption( 'error', 'Yell ->error and exit' );
		$this->addOption( 'exception', 'Crash with an exception' );
		$this->addOption( 'fatal', 'Do something unexpected' );
	}

	public function execute() {
		if ( $this->getOption( 'error' ) ) {
			$this->error( 'CRASHTEST: error', true );
		}
		if ( $this->getOption( 'exception' ) ) {
			throw new Exception( 'CRASHTEST: uncaught exception' );
		}
		if ( $this->getOption( 'fatal' ) ) {
			$this->error( 'CRASHTEST: fatal' );
			$everything_and_nothing = FOO::BAR();
		}

		$this->error( 'CRASHTEST: should not reach this line.' );
	}
}

$maintClass = 'TestCrash';
require_once RUN_MAINTENANCE_IF_MAIN;
