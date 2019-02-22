<?php

/**
 * Exception indicating an error processing an API response from the payment
 * processor
 */
class ResponseProcessingException extends Exception {
	/**
	 * @var array If the transaction suffered a recoverable error, this will
	 *      be an array of all variables that need to be recreated and restaged.
	 */
	protected $retryVars;
	/**
	 * @var string A code identifying the specific error encountered
	 */
	protected $errorCode;

	public function __construct( $message, $errorCode, $retryVars = [] ) {
		parent::__construct( $message );
		$this->errorCode = $errorCode;
		$this->retryVars = $retryVars;
	}

	public function getRetryVars() {
		return $this->retryVars;
	}

	public function getErrorCode() {
		return $this->errorCode;
	}
}
