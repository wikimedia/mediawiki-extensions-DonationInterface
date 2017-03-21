<?php

/**
 * Represents a validation error associated with a field or the 'general' bucket.
 */
class ValidationError {
	protected $field;
	protected $message_key;
	protected $message_params;

	public function __construct( $field, $message_key, $message_params = array() ) {
		$this->field = $field;
		$this->message_key = $message_key;
		$this->message_params = $message_params;
	}

	public function getField() {
		return $this->field;
	}

	public function getMessageKey() {
		return $this->message_key;
	}

	public function getMessageParams() {
		return $this->message_params;
	}
}
