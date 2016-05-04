<?php

/**
 * Formatting and validation for various countries' tax ID numbers.
 * Some rules can be deduced from the documentation of this Django app:
 * http://django-localflavor.readthedocs.org/en/latest/
 */
class FiscalNumber implements StagingHelper, ValidationHelper {

	protected static $countryRules = array(
		'AR' => array(
			'numeric' => true,
			'min' => 7,
			'max' => 10,
		),
		'BR' => array(
			'numeric' => true,
			'min' => 11,
			'max' => 14,
		),
		'CO' => array(
			'numeric' => true,
			'min' => 11,
			'max' => 14,
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
		if ( !empty( $normalized['fiscal_number'] ) ) {
			$stagedData['fiscal_number'] = preg_replace( '/[^a-zA-Z0-9]/', '', $normalized['fiscal_number'] );
		}
	}

	/**
	 * Rudimentary tests of fiscal number format for different countries
	 *
	 * @param array $normalized Normalized donation data
	 * @param array $errors Results of validation to this point
	 */
	public function validate( $normalized, &$errors ) {
		if (
			!isset( $normalized['country'] ) ||
			!isset( $normalized['fiscal_number'] )
		) {
			// Nothing to validate or no way to tell what rules to use
			return;
		}

		$value = $normalized['fiscal_number'];
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
			$errors['fiscal_number'] = DataValidator::getErrorMessage(
				'fiscal_number',
				$errorType,
				$normalized['language'],
				$country
			);
		}
	}
}
