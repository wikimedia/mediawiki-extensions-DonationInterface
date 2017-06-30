<?php

/**
 * Formatting and validation for various countries' tax ID numbers.
 * Some rules can be deduced from the documentation of this Django app:
 * http://django-localflavor.readthedocs.org/en/latest/
 */
class FiscalNumber implements StagingHelper, ValidationHelper, ClientSideValidationHelper {

	protected static $key = 'fiscal_number';

	protected static $countryRules = array(
		// Argentina's DNI numbers have 7-10 digits and CUIT numbers have 11
		'AR' => array(
			'numeric' => true,
			'min' => 7,
			'max' => 11,
		),
		'BR' => array(
			'numeric' => true,
			'min' => 11,
			'max' => 14,
		),
		'CO' => array(
			'numeric' => true,
			'min' => 6,
			'max' => 10,
		),
		'CL' => array(
			'min' => 8,
			'max' => 9,
		),
		'UY' => array(
			'numeric' => true,
			'min' => 6,
			'max' => 8,
		),
	);

	/**
	 * Strip any punctuation before submitting
	 *
	 * @param GatewayType $adapter
	 * @param array $normalized Normalized donation data
	 * @param array $stagedData Data to send to payment processor
	 */
	public function stage( GatewayType $adapter, $normalized, &$stagedData ) {
		if ( !empty( $normalized[self::$key] ) ) {
			$stagedData[self::$key] = preg_replace(
				'/[^a-zA-Z0-9]/',
				'',
				$normalized[self::$key]
			);
		}
	}

	/**
	 * Rudimentary tests of fiscal number format for different countries
	 *
	 * @param GatewayType $unused
	 * @param array $normalized Normalized donation data
	 * @param ErrorState $errors Results of validation to this point
	 */
	public function validate( GatewayType $unused, $normalized, &$errors ) {
		if (
			!isset( $normalized['country'] ) ||
			!isset( $normalized[self::$key] )
		) {
			// Nothing to validate or no way to tell what rules to use
			return;
		}

		$value = $normalized[self::$key];
		$country = $normalized['country'];
		$hasError = false;

		if ( !isset( self::$countryRules[$country] ) ) {
			return;
		}

		$rules = self::$countryRules[$country];
		$unpunctuated = preg_replace( '/[^A-Za-z0-9]/', '', $value );

		if ( !empty( $rules['numeric'] ) ) {
			if ( !is_numeric( $unpunctuated ) ) {
				$hasError = true;
			}
		}

		$length = strlen( $unpunctuated );
		if ( $length < $rules['min'] || $length > $rules['max'] ) {
			$hasError = true;
		}

		if ( $hasError ) {
			// This looks bad.
			if ( $length === 0 ) {
				$errorType = 'not_empty';
			} else {
				$errorType = 'calculated';
			}
			$errors->addError( self::getError( $errorType ) );
		}
	}

	protected static function getError( $type ) {
		return DataValidator::getError(
			self::$key,
			$type
		);
	}

	public function getClientSideValidation( $normalized, &$clientRules ) {
		if (
			!isset( $normalized['country'] ) ||
			empty( self::$countryRules[$normalized['country']] )
		) {
			return null;
		}

		$rule = self::$countryRules[$normalized['country']];
		if ( empty( $rule['numeric'] ) ) {
			$pattern = '^[^0-9a-zA-Z]*([0-9a-zA-Z][^0-9a-zA-Z]*)';
		} else {
			$pattern = '^[^0-9]*([0-9][^0-9]*)';
		}
		$pattern .= '{' . $rule['min'] . ',' . $rule['max'] . '}$';

		$fiscalRules = array( array(
			'pattern' => $pattern,
			'messageKey' => 'donate_interface-error-msg-invalid-fiscal_number'
		) );

		$clientRules[self::$key] = $fiscalRules;
	}
}
