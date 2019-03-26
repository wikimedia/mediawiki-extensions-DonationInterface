<?php

use SmashPig\Core\PaymentError;
use SmashPig\Core\ValidationError;

class ErrorState {
	protected $errors = [];

	/**
	 * Unset validation error for a specific field
	 *
	 * @param string $field data field name
	 */
	public function clearValidationError( $field ) {
		$toDelete = -1;
		foreach ( $this->errors as $index => $error ) {
			if (
				$error instanceof ValidationError &&
				$error->getField() === $field
			) {
				$toDelete = $index;
			}
		}
		if ( $toDelete > -1 ) {
			array_splice( $this->errors, $toDelete, 1 );
		}
	}

	/**
	 * @param PaymentError|ValidationError $error
	 */
	public function addError( $error ) {
		if ( $error instanceof ValidationError ) {
			$field = $error->getField();
			if ( $this->hasValidationError( $field ) ) {
				$this->clearValidationError( $field );
			}
		}
		$this->errors[] = $error;
	}

	public function addErrors( $errors ) {
		foreach ( $errors as $error ) {
			$this->addError( $error );
		}
	}

	/**
	 * @return array PaymentError|ValidationError
	 */
	public function getErrors() {
		return $this->errors;
	}

	public function hasErrors() {
		return !empty( $this->errors );
	}

	/**
	 * Is a specific field invalid, or is any field invalid?
	 *
	 * @param string|null $field data field name, or null to check all fields
	 * @return bool true if a validation error exists for the given field
	 */
	public function hasValidationError( $field = null ) {
		foreach ( $this->errors as $error ) {
			if ( $error instanceof ValidationError ) {
				if ( $field === null || $error->getField() === $field ) {
					return true;
				}
			}
		}
		return false;
	}
}
