<?php

interface ValidationHelper {
	/**
	 * Run validation on whatever normalized data we're responsible for,
	 * and set errors per field, or under the "general" key.
	 *
	 * @param GatewayType $adapter
	 * @param array $normalized Donation data in normalized form.
	 * @param array $errors Reference to error array
	 * @return void
	 */
	function validate( GatewayType $adapter, $normalized, &$errors );
}
