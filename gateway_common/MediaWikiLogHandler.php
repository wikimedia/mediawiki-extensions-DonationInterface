<?php

use MediaWiki\Logger\LoggerFactory;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

/**
 * Fallback log handler that dumps messages to MediaWiki's log backend when
 * not using syslog
 *
 * @author Elliott Eggleston <eeggleston@wikimedia.org>
 */
class MediaWikiLogHandler extends AbstractProcessingHandler {

	/**
	 * @var string
	 */
	protected $identifier;

	/**
	 * @param string $identifier String to write to wfDebugLog/watchdog
	 * @param int $level The minimum logging level at which this handler will be triggered, as defined in \Monolog\Logger
	 * @param bool $bubble Whether the messages that are handled can bubble up the stack or not
	 */
	public function __construct( $identifier, $level = Logger::DEBUG, $bubble = true ) {
		$this->identifier = $identifier;
		parent::__construct( $level, $bubble );
	}

	protected function write( array $record ): void {
		$logger = LoggerFactory::getInstance( $this->identifier );
		// Contains chars of all log levels and avoids using strtolower() which may have
		// strange results depending on locale (for example, "I" will become "ı" in Turkish locale)
		$lower = strtr( $record['level_name'], 'ABCDEFGILMNORTUWY', 'abcdefgilmnortuwy' );
		$logger->log( $lower, $record['message'] );
	}
}
