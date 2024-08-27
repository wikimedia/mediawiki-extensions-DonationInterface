<?php

class EmployerFieldValidation implements ValidationHelper, ClientSideValidationHelper {

	/** @var string */
	protected static $key = 'employer';

	/**
	 * Rudimentary tests of Employer field to ensure right format
	 *
	 * @param GatewayType $unused
	 * @param array $normalized Normalized donation data
	 * @param ErrorState &$errors Results of validation to this point
	 */
	public function validate( GatewayType $unused, $normalized, &$errors ) {
		if (
			!isset( $normalized[self::$key] )
		) {
			// Nothing to validate or no way to tell what rules to use
			return;
		}

		$value = $normalized[self::$key];

		if ( !preg_match( '/[^-\s0-9]/', $value ) || DataValidator::cc_number_exists_in_str( $value ) ) {
			$error = DataValidator::getError(
				self::$key,
				'calculated'
			);
			$errors->addError( $error );
		}
	}

	public function getClientSideValidation( $normalized, &$clientRules ) {
		$fiscalRules = [ [
			'pattern' => '[^-\s0-9]',
			'messageKey' => 'donate_interface-error-msg-invalid-employer'
		] ];

		$clientRules[self::$key] = $fiscalRules;
	}
}
