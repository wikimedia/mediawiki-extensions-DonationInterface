<?php
use Psr\Log\AbstractLogger;

/**
 * Dummy logger
 *
 * @author Elliott Eggleston <eeggleston@wikimedia.org>
 */
class TestingDonationLogger extends AbstractLogger {
	public $messages = [];

	public function log( $level, $message, array $context = [] ) {
		$this->messages[$level][] = $message;
	}
}
