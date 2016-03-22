<?php

/**
 * Used to try to add contribution tracking ID to the logs, even if we don't
 * have an adapter instance.
 */
class FallbackLogPrefixer implements LogPrefixProvider {

	protected $prefix;

	public function __construct( $prefix ) {
		$this->prefix = $prefix;
	}

	public function getLogMessagePrefix() {
		return $this->prefix;
	}
}
