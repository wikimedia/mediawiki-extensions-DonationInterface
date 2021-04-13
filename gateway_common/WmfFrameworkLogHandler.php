<?php
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

/**
 * Fallback log handler that dumps messages to WmfFramework's log backend when
 * not using syslog
 *
 * @author Elliott Eggleston <eeggleston@wikimedia.org>
 */
class WmfFrameworkLogHandler extends AbstractProcessingHandler {

	/**
	 * @var string
	 */
	protected $identifier;

	/**
	 * @param string $identifier String to write to wfDebugLog/watchdog
	 * @param int $level The minimum logging level at which this handler will be triggered, as defined in \Monolog\Logger
	 * @param Boolean $bubble Whether the messages that are handled can bubble up the stack or not
	 */
	public function __construct( $identifier, $level = Logger::DEBUG, $bubble = true ) {
		$this->identifier = $identifier;
		parent::__construct( $level, $bubble );
	}

	protected function write( array $record ): void {
		WmfFramework::debugLog( $this->identifier, $record['message'], $record['level_name'] );
	}
}
