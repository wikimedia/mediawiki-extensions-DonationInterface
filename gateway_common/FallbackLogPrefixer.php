<?php

/**
 * Used to try to add contribution tracking ID to the logs, even if we don't
 * have an adapter instance.
 */
class FallbackLogPrefixer implements LogPrefixProvider {

	/** @var string */
	protected $prefix;

	/**
	 * @param string $prefix
	 */
	public function __construct( $prefix ) {
		$this->prefix = $prefix;
	}

	/**
	 * @return string
	 */
	public function getLogMessagePrefix() {
		return $this->prefix;
	}
}
