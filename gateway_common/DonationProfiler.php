<?php

use Psr\Log\LoggerInterface;

/**
 * Records duration of various operations.
 */

class DonationProfiler {
	protected $logger;
	protected $commLogger;
	protected $gatewayName;

	/**
	 * @var array The microtime at which each stopwatch was started.
	 */
	protected static $start = array();

	/**
	 * DonationProfiler constructor.
	 * @param LoggerInterface $logger Used to record time at each getStopwatch call
	 * @param LoggerInterface|null $commLogger If not null, used to record communications statistics
	 * @param string $gatewayName identifier
	 */
	public function __construct( LoggerInterface $logger, $commLogger, $gatewayName ) {
		$this->logger = $logger;
		$this->commLogger = $commLogger;
		$this->gatewayName = $gatewayName;
	}

	/**
	 * getStopwatch keeps track of how long things take, for logging,
	 * output, determining if we should loop on some method again... whatever.
	 * @param string $string Some identifier for each stopwatch value we want to
	 * keep. Each unique $string passed in will get its own value in $start.
	 * @param bool $reset If this is set to true, it will reset any $start value
	 * recorded for the $string identifier.
	 * @return float The difference in microtime (rounded to 4 decimal places)
	 * between the $start value, and now.
	 */
	public function getStopwatch( $string, $reset = false ) {
		$now = microtime( true );

		if ( empty( $start ) || !array_key_exists( $string, $start ) || $reset === true ) {
			$start[$string] = $now;
		}
		$clock = round( $now - $start[$string], 4 );
		$this->logger->info( "Clock at $string: $clock ($now)" );
		return $clock;
	}

	/**
	 * @param string $function This is the function name that identifies the
	 * stopwatch that should have already been started with the profiler.
	 * @param string $additional Additional information about the thing we're
	 * currently timing. Meant to be easily searchable.
	 * @param string $vars Intended to be particular values of any variables
	 * that might be of interest.
	 */
	public function saveCommunicationStats( $function = '', $additional = '', $vars = '' ) {
		if ( $this->commLogger === null ) {
			return;
		}

		$params = array(
			'duration' => $this->getStopwatch( $function ),
			'gateway' => $this->gatewayName,
			'function' => $function,
			'vars' => $vars,
			'additional' => $additional,
		);
		$msg = '';
		foreach ( $params as $key => $val ) {
			$msg .= "$key:$val - ";
		}
		$this->commLogger->info( $msg );
	}
}
