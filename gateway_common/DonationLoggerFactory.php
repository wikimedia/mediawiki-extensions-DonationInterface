<?php
use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\SyslogHandler;

/**
 * Creates loggers and profilers for DonationInterface
 *
 * @author Elliott Eggleston <eeggleston@wikimedia.org>
 */
class DonationLoggerFactory {
	/**
	 * For use by test harnesses to override the instance returned
	 * @var \Psr\Log\LoggerInterface
	 */
	public static $overrideLogger = null;

	/**
	 * @param GatewayType $adapter Get settings from this instance
	 * @param string $suffix Append this string to the adapter identifier
	 * @param LogPrefixProvider $prefixer Optionally use this to override
	 *        prefixing via the adapter.
	 * @return \Psr\Log\LoggerInterface
	 */
	public static function getLogger( GatewayType $adapter = null, $suffix = '', LogPrefixProvider $prefixer = null ) {
		if ( self::$overrideLogger !== null ) {
			return self::$overrideLogger;
		}
		if ( $adapter === null ) {
			$identifier = GatewayAdapter::getLogIdentifier();
			$useSyslog = GatewayAdapter::getGlobal( 'UseSyslog' );
			$debug = GatewayAdapter::getGlobal( 'LogDebug' );
		} else {
			$identifier = $adapter::getLogIdentifier();
			$useSyslog = $adapter::getGlobal( 'UseSyslog' );
			$debug = $adapter::getGlobal( 'LogDebug' );
		}
		$identifier = $identifier . $suffix;

		$logger = new Logger( $identifier );
		$logThreshold = $debug ? Logger::DEBUG : Logger::INFO;

		if ( $useSyslog ) {
			$handler = new SyslogHandler( $identifier, LOG_USER, $logThreshold, true, 0 );
		} else {
			$handler = new WmfFrameworkLogHandler( $identifier, $logThreshold );
		}

		$formatter = new LineFormatter( '%message%' );
		$handler->setFormatter( $formatter );

		if ( $prefixer === null ) {
			$prefixer = $adapter;
		}

		// If either prefixer or adapter were non-null, add a processor
		if ( $prefixer !== null ) {
			$processor = new DonationLogProcessor( $prefixer );
			$handler->pushProcessor( $processor );
		}
		$logger->pushHandler( $handler );
		return $logger;
	}

	/**
	 * Retrieve a profiler instance which saves communication statistics
	 * if the adapter's SaveCommStats global is set to true.
	 * @param GatewayType $adapter
	 * @return DonationProfiler
	 */
	public static function getProfiler( GatewayType $adapter ) {
		if ( $adapter->getGlobal( 'SaveCommStats' ) ) {
			$commLogger = self::getLogger( $adapter, '_commstats' );
		} else {
			$commLogger = null;
		}
		return new DonationProfiler(
			self::getLogger( $adapter ),
			$commLogger,
			$adapter->getGatewayName()
		);
	}
}
