<?php

/**
 * Represents an internal, payment processor, or general error.
 */
class PaymentError {
	protected $error_code;
	protected $debug_message;
	protected $log_level;

	public function __construct( $error_code, $debug_message, $log_level ) {
		$this->error_code = $error_code;
		$this->debug_message = $debug_message;
		$this->log_level = $log_level;
	}

	public function getErrorCode() {
		return $this->error_code;
	}

	public function getDebugMessage() {
		return $this->debug_message;
	}

	public function getLogLevel() {
		return $this->log_level;
	}
}
