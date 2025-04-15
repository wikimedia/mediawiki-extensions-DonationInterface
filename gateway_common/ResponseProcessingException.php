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

	/**
	 * @param string $message
	 * @param string|int $errorCode
	 * @param array $retryVars
	 */
	public function __construct( $message, $errorCode, $retryVars = [] ) {
		parent::__construct( $message );
		$this->errorCode = (string)$errorCode;
		$this->retryVars = $retryVars;
	}

	/**
	 * @return array
	 */
	public function getRetryVars() {
		return $this->retryVars;
	}

	/**
	 * @return string
	 */
	public function getErrorCode() {
		return $this->errorCode;
	}
}
