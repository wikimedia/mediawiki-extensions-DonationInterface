<?php

use SmashPig\Core\ValidationError;
use SmashPig\PaymentData\ReferenceData\CurrencyRates;

class Amount implements ValidationHelper {

	public function validate( GatewayType $adapter, $normalized, &$errors ) {
		if (
			!isset( $normalized['amount'] ) ||
			!isset( $normalized['currency'] )
		) {
			// Not enough info to validate
			return;
		}
		if ( $errors->hasValidationError( 'currency' ) ) {
			// Already displaying an error
			return;
		}
		$value = $normalized['amount'];

		if ( self::isZeroIsh( $value ) ) {
			$errors->addError(
				DataValidator::getError( 'amount', 'not_empty' )
			);
			return;
		}
		$donationCurrency = $normalized['currency'];
		$rules = $adapter->getDonationRules();
		$rates = CurrencyRates::getCurrencyRates();
		$ruleCurrency = $rules['currency'];
		if ( $ruleCurrency === $donationCurrency ) {
			// Avoid converting if we're already looking at the right currency
			$min = $rules['min'];
			$max = $rules['max'];
			$maxUsd = self::convert( $max, 'USD', $ruleCurrency );
		} else {
			// Get the min and max in USD, then convert to donation currency
			$minUsd = self::convert( $rules['min'], 'USD', $ruleCurrency );
			$maxUsd = self::convert( $rules['max'], 'USD', $ruleCurrency );
			$min = self::convert( $minUsd, $donationCurrency );
			$max = self::convert( $maxUsd, $donationCurrency );
		}
		if (
			!is_numeric( $value ) ||
			$value < 0
		) {
			$errors->addError( new ValidationError(
				'amount',
				'donate_interface-error-msg-invalid-amount'
			) );
		} elseif ( $value > $max ) {
			// FIXME: should format the currency values in this message
			$errors->addError( new ValidationError(
				'amount',
				'donate_interface-bigamount-error',
				[
					$max,
					$donationCurrency,
					$adapter->getGlobal( 'MajorGiftsEmail' ),
					$maxUsd
				]
			) );
		} elseif ( $value < $min ) {
			$locale = $normalized['language'] . '_' . $normalized['country'];
			$formattedMin = self::format( $min, $donationCurrency, $locale );
			$errors->addError( new ValidationError(
				'amount',
				'donate_interface-smallamount-error',
				[ $formattedMin ]
			) );
		}
	}

	/**
	 * Checks if the $value is missing or equivalent to zero.
	 *
	 * @param string $value The value to check for zero-ness
	 * @return bool True if the $value is missing or zero, otherwise false
	 */
	protected static function isZeroIsh( $value ) {
		if (
			$value === null ||
			trim( $value ) === '' ||
			( is_numeric( $value ) && abs( $value ) < 0.01 )
		) {
			return true;
		}

		return false;
	}

	/**
	 * Convert an amount in USD to a particular currency
	 *
	 * This is grossly rudimentary and likely wildly inaccurate.
	 * This mimics the hard-coded values used by the WMF to convert currencies
	 * for validation on the front-end on the first step landing pages of their
	 * donation process - the idea being that we can get a close approximation
	 * of converted currencies to ensure that contributors are not going above
	 * or below the price ceiling/floor, even if they are using a non-US currency.
	 *
	 * In reality, this probably ought to use some sort of webservice to get real-time
	 * conversion rates.
	 *
	 * @param float $amount
	 * @param string $toCurrency
	 * @param string $fromCurrency
	 * @return float
	 * @throws UnexpectedValueException
	 */
	public static function convert( $amount, $toCurrency, $fromCurrency = 'USD' ) {
		$rates = CurrencyRates::getCurrencyRates();
		$code = strtoupper( $toCurrency );
		if ( array_key_exists( $code, $rates ) ) {
			return $amount / $rates[$fromCurrency] * $rates[$code];
		}
		throw new UnexpectedValueException(
			'Bad programmer!  Bad currency made it too far through the portcullis'
		);
	}

	/**
	 * Some currencies, like JPY, don't exist in fractional amounts.
	 * This rounds an amount to the appropriate number of decimal places.
	 * Use the results of this for internal use, and use @see Amount::format
	 * for values displayed to donors.
	 *
	 * @param float $amount
	 * @param string $currencyCode
	 * @return string rounded amount
	 */
	public static function round( $amount, $currencyCode ) {
		$amount = floatval( $amount );
		if ( self::is_fractional_currency( $currencyCode ) ) {
			$precision = 2;
			if ( self::is_exponent3_currency( $currencyCode ) ) {
				$precision = 3;
			}
			return number_format( $amount, $precision, '.', '' );
		} else {
			return (string)floor( $amount );
		}
	}

	/**
	 * If an amount is ever expressed for the fractional currencies defined in
	 * this function, they should not have an associated fractional amount
	 * (so: full integers only).
	 *
	 * @param string $currency_code The three-digit currency code.
	 * @return bool
	 */
	public static function is_fractional_currency( $currency_code ) {
		// these currencies cannot have cents.
		$non_fractional_currencies = [
			'CLP', 'DJF', 'IDR', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'VND', 'XAF', 'XOF', 'XPF'
		];

		if ( in_array( strtoupper( $currency_code ), $non_fractional_currencies ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Checks if ISO 4217 defines the currency's minor units as being expressed using
	 * exponent 3 (three decimal places).
	 * @param string $currency_code The three-character currency code.
	 * @return bool
	 */
	public static function is_exponent3_currency( $currency_code ) {
		$exponent3_currencies = [ 'BHD', 'CLF', 'IQD', 'KWD', 'LYD', 'MGA', 'MRO', 'OMR', 'TND' ];

		if ( in_array( strtoupper( $currency_code ), $exponent3_currencies ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Format an amount and currency for display to users.
	 *
	 * @param float $amount
	 * @param string $currencyCode
	 * @param string $locale e.g. en_US
	 * @return string
	 */
	public static function format( $amount, $currencyCode, $locale ) {
		$amount = self::round( $amount, $currencyCode );
		if ( class_exists( NumberFormatter::class ) ) {
			$formatter = new NumberFormatter( $locale, NumberFormatter::CURRENCY );

			if ( $formatter instanceof NumberFormatter ) {
				return $formatter->formatCurrency(
					floatval( $amount ),
					$currencyCode
				);
			} else {
				// This logger won't mark output to associate with the donor,
				// but at least it'll feed into the general error log.
				$logger = DonationLoggerFactory::getLoggerForType( 'GatewayAdapter' );
				$logger->error(
					"Could not create NumberFormatter for locale '$locale' " .
					"and currency '$currencyCode'."
				);
			}
		}
		return "$amount $currencyCode";
	}
}
