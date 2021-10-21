<?php

use SmashPig\Core\PaymentError;

/**
 * Contains information parsed out of a payment processor's response to a
 * transaction.
 * TODO: Prune aggressively!
 */
class PaymentTransactionResponse {
	/**
	 * @var array List of PaymentErrors
	 */
	protected $errors = [];

	/**
	 * @var string Raw return data from the cURL transaction
	 */
	protected $rawResponse;

	/**
	 * @var string Originally supposed to be an i18n label, but somewhere
	 * along the line this just turned into a message that would be marginally
	 * okay to display to a user.
	 */
	protected $message;

	/**
	 * @var bool denoting if there were internal errors on our end,
	 * or at the gateway.
	 */
	protected $communicationStatus;

	/**
	 * @var array all parsed transaction data
	 */
	protected $data;

	/**
	 * FIXME: get rid of this, maybe make it a new action?
	 * @var bool whether to force cancellation of this transaction (flags
	 * things we could get fined for retrying)
	 */
	protected $forceCancel;

	/**
	 * TODO: get rid of this.  Maybe status should be an enum
	 * @var string Special case internal messages about the success or
	 * failure of the transaction.
	 */
	protected $txnMessage;

	/**
	 * @var string where the donor should go next.
	 */
	protected $redirect;

	/**
	 * @return array
	 */
	public function getErrors() {
		return $this->errors;
	}

	/**
	 * @return string
	 */
	public function getRawResponse() {
		return $this->rawResponse;
	}

	/**
	 * @return string
	 */
	public function getMessage() {
		return $this->message;
	}

	/**
	 * @return bool
	 */
	public function getCommunicationStatus() {
		return $this->communicationStatus;
	}

	/**
	 * @return array
	 */
	public function getData() {
		return $this->data;
	}

	/**
	 * @param array $errors List of PaymentError
	 */
	public function addErrors( $errors ) {
		$this->errors += $errors;
	}

	/**
	 * Add an error to the internal $errors array
	 * @param PaymentError $error
	 */
	public function addError( $error ) {
		$this->errors[] = $error;
	}

	/**
	 * @param string $rawResponse
	 */
	public function setRawResponse( $rawResponse ) {
		$this->rawResponse = $rawResponse;
	}

	/**
	 * @param string $message
	 */
	public function setMessage( $message ) {
		$this->message = $message;
	}

	/**
	 * @param bool $communicationStatus
	 */
	public function setCommunicationStatus( $communicationStatus ) {
		$this->communicationStatus = $communicationStatus;
	}

	/**
	 * @param array $data
	 */
	public function setData( $data ) {
		$this->data = $data;
	}

	/**
	 * @return bool
	 */
	public function getForceCancel() {
		return $this->forceCancel;
	}

	/**
	 * @param bool $forceCancel
	 */
	public function setForceCancel( $forceCancel ) {
		$this->forceCancel = $forceCancel;
	}

	/**
	 * @return string
	 */
	public function getTxnMessage() {
		return $this->txnMessage;
	}

	/**
	 * @return string
	 */
	public function getRedirect() {
		return $this->redirect;
	}

	/**
	 * @param string $txnMessage
	 */
	public function setTxnMessage( $txnMessage ) {
		$this->txnMessage = $txnMessage;
	}

	/**
	 * @param string $redirect
	 */
	public function setRedirect( $redirect ) {
		$this->redirect = $redirect;
	}

	public function __toString() {
		$stringVal = "Communication Status: '{$this->communicationStatus}', " .
			"Communication Status: '{$this->communicationStatus}', " .
			"Message: '{$this->message}', " .
			"Txn Message: '{$this->txnMessage}', " .
			'Data: ' . print_r( $this->data, true ) . ', ' .
			"Force cancel: '{$this->forceCancel}', " .
			"Redirect: '{$this->redirect}', " .
			"Raw response: '{$this->rawResponse}', Errors: ";
		$errorStrings = [];
		foreach ( $this->errors as $error ) {
			if ( $error instanceof PaymentError ) {
				$errorStrings[] .= ' code ' . $error->getErrorCode() .
					': ' . $error->getDebugMessage();
			}
		}
		return $stringVal . implode( ';', $errorStrings );
	}
}
