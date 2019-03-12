<?php

/**
 * Marks classes that can define rules to validate fields in JavaScript
 * before submitting any data to the server.
 */
interface ClientSideValidationHelper {

	/**
	 * Adds rules and error messages to $clientRules for use in client-side
	 * validation. Rule keys:
	 *      'required' when true, check that the field is not empty
	 *      'pattern' field value must match this regular expression
	 *      'message' error message to display if this rule fails
	 *
	 * @param array $normalized The gateway's normalized donation data
	 * @param array &$clientRules Associates field keys with lists of rules
	 */
	public function getClientSideValidation( $normalized, &$clientRules );

}
