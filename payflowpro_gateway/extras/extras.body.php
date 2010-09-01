<?php
/**
 * An abstract class for payflowpro gateway 'extras'
 */

abstract class PayflowProGateway_Extras {
	/**
	 * File handle for log file
	 * @var public
	 */
	public $log_fh = NULL;

	public function __construct() {
		global $wgPayflowGatewayLog;
		// prepare the log file if the user has specified one
		if ( strlen( $wgPayflowGatewayLog) > 0 ) $this->prepare_log_file( $wgPayflowGatewayLog );
	}

	/**
	 * Prepare a log file
	 *
	 * @param string path to log file
	 * @return resource Pointer for the log file
	 */
	protected function prepare_log_file( $log_file ){
		$this->log_fh = fopen( $log_file, 'a+' );
	}

	/**
	 * Writes message to a log file
	 * 
	 * If a log file does not exist and could not be created,
	 * do nothing.
	 * @fixme Perhaps lack of log file can be handled better?
	 * @param string The message to log
	 */
	public function log( $id='', $status='', $data='' ) {
		if ( !$this->log_fh ) {
			echo "what log file?";
			return;
		}
		$msg = '"' . date( 'c' ) . '"';
		$msg .= "\t" . '"' . $id . '"';
		$msg .= "\t" . '"' . $status . '"';
		$msg .= "\t" . $data . "\n";
		fwrite( $this->log_fh, $msg );
	}

	/**
	 * Generate a hash of some data
	 * @param string the data to hash
	 * @return string The hash of the data
	 */
	public function generate_hash( $data ) {
		global $wgPayflowGatewaySalt;
		return hash( "sha512", $wgPayflowGatewaySalt . $data );
	}

	/**
	 * Compare a hash to the hash of some given data
	 * @param string $hash A given hash
	 * @param string $data The data to hash and compare to $hash
	 * @return bool
	 */
	public function compare_hash( $hash, $data ) {
		if ( $hash == $this->generate_hash( $data )) {
			return TRUE;
		}

		return FALSE;
	}

	/**
	 * Close the open log file handler if it's open
	 */
	public function __destruct() {
		if ( $this->log_fh ) fclose( $this->log_fh );
	}	
}
