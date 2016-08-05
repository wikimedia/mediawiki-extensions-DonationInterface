<?php
use Psr\Log\AbstractLogger;

/**
 * Dummy logger
 *
 * @author Elliott Eggleston <eeggleston@wikimedia.org>
 */
class TestingDonationLogger extends AbstractLogger {
	public $messages = array();

	public function log( $level, $message, array $context = array() ) {
		$this->messages[$level][] = $message;
	}
}
