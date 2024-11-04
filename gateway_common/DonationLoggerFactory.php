<?php
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\SyslogHandler;
use Monolog\Logger;

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
	 * @param LogPrefixProvider|null $prefixer Optionally use this to override
	 *        prefixing via the adapter.
	 * @return \Psr\Log\LoggerInterface
	 */
	public static function getLogger( GatewayType $adapter, $suffix = '', ?LogPrefixProvider $prefixer = null ) {
		if ( $prefixer === null ) {
			$prefixer = $adapter;
		}
		return self::getLoggerFromParams(
			$adapter->getLogIdentifier(),
			$adapter->getGlobal( 'UseSyslog' ),
			$adapter->getGlobal( 'LogDebug' ),
			$suffix,
			$prefixer
		);
	}

	/**
	 * Get a logger without an adapter instance
	 * @param string $adapterType
	 * @param string $prefix
	 * @return \Psr\Log\LoggerInterface
	 */
	public static function getLoggerForType( $adapterType, $prefix = '' ) {
		if ( $prefix === '' ) {
			$prefixer = null;
		} else {
			$prefixer = new FallbackLogPrefixer( $prefix );
		}
		return self::getLoggerFromParams(
			$adapterType::getLogIdentifier(),
			$adapterType::getGlobal( 'UseSyslog' ),
			$adapterType::getGlobal( 'LogDebug' ),
			'',
			$prefixer
		);
	}

	public static function getLoggerFromParams( $identifier, $useSyslog, $debug, $suffix, $prefixer ) {
		if ( self::$overrideLogger !== null ) {
			return self::$overrideLogger;
		}
		$identifier .= $suffix;

		$logger = new Logger( $identifier );
		$logThreshold = $debug ? Logger::DEBUG : Logger::INFO;

		if ( $useSyslog ) {
			$handler = new SyslogHandler( $identifier, LOG_USER, $logThreshold, true, 0 );
		} else {
			$handler = new MediaWikiLogHandler( $identifier, $logThreshold );
		}

		$formatter = new LineFormatter( '%message%' );
		$handler->setFormatter( $formatter );

		// If we have a prefixer, add a processor
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
