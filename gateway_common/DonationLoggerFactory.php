<?php
use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\SyslogHandler;

/**
 * Creates loggers for DonationInterface
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
	 * @param GatewayAdapter $gateway Get settings from this instance
	 * @param string $suffix Append this string to the gateway identifier
	 * @param LogPrefixProvider $prefixer Optionally use this to override
	 *        prefixing via the gateway.
	 * @return \Psr\Log\LoggerInterface
	 */
	public static function getLogger( GatewayAdapter $gateway = null, $suffix = '', LogPrefixProvider $prefixer = null ) {
		if ( self::$overrideLogger !== null ) {
			return self::$overrideLogger;
		}
		if ( $gateway === null ) {
			$identifier = GatewayAdapter::getLogIdentifier();
			$useSyslog = GatewayAdapter::getGlobal( 'UseSyslog' );
			$debug = GatewayAdapter::getGlobal( 'LogDebug' );
		} else {
			$identifier = $gateway::getLogIdentifier();
			$useSyslog = $gateway::getGlobal( 'UseSyslog' );
			$debug = $gateway::getGlobal( 'LogDebug' );
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
			$prefixer = $gateway;
		}

		// If either prefixer or gateway were non-null, add a processor
		if ( $prefixer !== null ) {
			$processor = new DonationLogProcessor( $prefixer );
			$handler->pushProcessor( $processor );
		}
		$logger->pushHandler( $handler );
		return $logger;
	}
}
