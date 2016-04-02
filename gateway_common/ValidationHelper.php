<?php

interface ValidationHelper {
	/**
	 * Run validation on whatever normalized data we're responsible for,
	 * and set errors per field, or under the "general" key.
	 */
	function validate( $normalized, &$errors );
}
