<?php

/**
 * Adds a prefix from a LogPrefixProvider to log messages
 *
 * @author Elliott Eggleston <eeggleston@wikimedia.org>
 */
class DonationLogProcessor {

	protected LogPrefixProvider $prefixer;

	public function __construct( LogPrefixProvider $prefixer ) {
		$this->prefixer = $prefixer;
	}

	public function __invoke( $record ) {
		try {
			$record['message'] = $this->prefixer->getLogMessagePrefix() . $record['message'];
		} catch ( Exception ) {
			// logging shouldn't throw any exceptions
		}
		return $record;
	}
}
