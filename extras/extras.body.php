<?php
use Psr\Log\LogLevel;

/**
 * An abstract class for gateway 'extras'
 */
abstract class Gateway_Extras {

	/**
	 * @var GatewayType
	 */
	public $gateway_adapter;

	/**
	 * Sends messages to the blah_gateway_trxn log
	 * @var \Psr\Log\LoggerInterface
	 */
	protected $transaction_logger;

	/**
	 * Sends messages to the standard gateway log
	 * @var \Psr\Log\LoggerInterface
	 */
	protected $gateway_logger;

	public function __construct( GatewayType $gateway_adapter ) {
		$this->gateway_adapter = $gateway_adapter;
		$this->transaction_logger = DonationLoggerFactory::getLogger( $this->gateway_adapter, '_trxn' );
		$this->gateway_logger = DonationLoggerFactory::getLogger( $this->gateway_adapter );
	}

	/**
	 * Writes message to the log
	 *
	 * @fixme Do formatting with a Monolog formatter
	 * @param string $id
	 * @param string $status
	 * @param string $data
	 * @param string $log_level One of the constants defined in @see \Psr\Log\LogLevel
	 */
	public function log( $id = '', $status = '', $data = '', $log_level = LogLevel::INFO ) {

		// format the message
		$msg = '"' . date( 'c' ) . '"';
		$msg .= "\t" . '"' . $id . '"';
		$msg .= "\t" . '"' . $status . '"';
		$msg .= "\t" . $data . "\n";

		// replace tabs with spaces - maybe do this universally?  cuz who needs tabs.
		$msg = str_replace( "\t", " ", $msg );

		$this->transaction_logger->log( $log_level, $msg );
	}

	/**
	 * Generate a hash of some data
	 * @param string $data the data to hash
	 * @return string The hash of the data
	 */
	public function generate_hash( $data ) {
		$salt = $this->gateway_adapter->getGlobal( 'Salt' );
		return hash( "sha512", $salt . $data );
	}

	/**
	 * Compare a hash to the hash of some given data
	 * @param string $hash A given hash
	 * @param string $data The data to hash and compare to $hash
	 * @return bool
	 */
	public function compare_hash( $hash, $data ) {
		if ( $hash === $this->generate_hash( $data ) ) {
			return TRUE;
		}

		return FALSE;
	}
}
