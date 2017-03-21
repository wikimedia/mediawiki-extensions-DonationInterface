<?php

interface ValidationHelper {
	/**
	 * Run validation on whatever normalized data we're responsible for,
	 * and append errors.
	 *
	 * @param GatewayType $adapter
	 * @param array $normalized Donation data in normalized form.
	 * @param ErrorState $errors Reference to error state
	 * @return void
	 */
	function validate( GatewayType $adapter, $normalized, &$errors );
}
