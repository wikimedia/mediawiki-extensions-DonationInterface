<?php

namespace MediaWiki\Extension\DonationInterface\FraudFilters;

use Psr\Log\LoggerInterface;

class GenericPatternFilter {

	protected string $patternName;
	protected int $failScore;
	protected array $values;

	private const NUMERIC_COMPARISON_OPERATORS = [ '<', '>', '<=', '>=' ];

	/**
	 * @param string $patternName
	 * @param int $failScore
	 * @param array $values
	 */
	public function __construct( string $patternName, int $failScore, array $values ) {
		$this->patternName = $patternName;
		$this->failScore = $failScore;
		$this->values = $values;
	}

	/**
	 * Run the filter and if everything matches, set the failure score
	 * @param array &$riskScores
	 * @param array $transactionValues
	 * @param LoggerInterface $logger
	 * @return void
	 */
	public function run( array &$riskScores, array $transactionValues, LoggerInterface $logger ) {
		foreach ( $this->values as $rulePropertyName => $rulePropertyValue ) {
			$actualValue = $transactionValues[$rulePropertyName] ?? null;

			// Check if the pattern contains a regex value or wildcard
			if ( is_string( $rulePropertyValue ) && $this->isRegex( $rulePropertyValue ) ) {
				if ( !preg_match( $rulePropertyValue, $actualValue ) ) {
					return;
				}
			} elseif ( is_string( $rulePropertyValue ) && str_contains( $rulePropertyValue, '*' ) ) {
				if ( !$this->matchesWildcard( $actualValue, $rulePropertyValue ) ) {
					return;
				}
			} elseif ( is_array( $rulePropertyValue ) && $this->isNumericComparison( $rulePropertyValue ) ) {
				if ( !$this->matchesNumericComparison( $actualValue, $rulePropertyValue ) ) {
					return;
				}
			} else {
				// Type coercion is OK here so we just use !=. This lets us write
				// 2.75 in the filter to match the string "2.75" from $transactionValues
				if ( $actualValue != $rulePropertyValue ) {
					return;
				}
			}
		}

		$logger->debug(
			"Matched transaction values for pattern $this->patternName: " . json_encode( $this->values )
		);
		$riskScores[$this->makeFilterName()] = $this->failScore;
	}

	/**
	 * Checks if the value is a regex or a string. It returns false for any string that is not in regex form.
	 *
	 * @param string $value
	 * @return bool
	 */
	public function isRegex( string $value ) {
		$firstMatch = strpos( $value, '/' );
		$lastMatch = strrpos( $value, '/' );
		$stringLength = strlen( $value );
		// Check for presence of regex delimeters '/' in the string
		// For example /[A-Z]/, /[A-Z]/i, /[A-Z]/gi
		return $firstMatch === 0 && $lastMatch !== $firstMatch && in_array( $lastMatch, [ $stringLength - 1, $stringLength - 2, $stringLength - 3 ] );
	}

	/**
	 * Checks if the given value matches the pattern with wildcards.
	 * Wildcards in the pattern are denoted by asterisks (`*`) and can match any sequence of characters.
	 *
	 * @param mixed $value The value to check against the pattern. If null, the match will automatically return false.
	 * @param string $pattern The wildcard pattern to match the value against. Asterisks (`*`) can be used as wildcards.
	 *
	 * @return bool Returns true if the value matches the pattern, otherwise false.
	 */
	protected function matchesWildcard( mixed $value, string $pattern ): bool {
		if ( $value === null ) {
			return false;
		}

		// split the pattern by asterisks to get literal parts
		$patternParts = explode( '*', $pattern );

		// escape each literal part for safe use in regex
		$escapedParts = array_map( static function ( $part ) {
			return preg_quote( $part, '/' );
		}, $patternParts );

		// join escaped parts with '.*' (match any characters) to create the regex pattern
		$regexPattern = '/^' . implode( '.*', $escapedParts ) . '$/';

		// match against the string value
		return (bool)preg_match( $regexPattern, (string)$value );
	}

	/**
	 * Checks if the value is a numeric comparison array.
	 *
	 * Expected format: ['<', 3] or ['>=', 10.5]
	 *   - First element: comparison operator (<, >, <=, >=)
	 *   - Second element: numeric threshold
	 *
	 * @param array $value
	 * @return bool
	 */
	protected function isNumericComparison( array $value ): bool {
		// Ensure it's a list with exactly 2 elements at indexes 0 and 1
		// (not an associative array)
		return array_is_list( $value )
			&& count( $value ) === 2
			&& in_array( $value[0], self::NUMERIC_COMPARISON_OPERATORS, true )
			&& is_numeric( $value[1] );
	}

	/**
	 * Checks if the given value satisfies the numeric comparison.
	 *
	 * @param mixed $value The actual value to compare
	 * @param array $comparison The comparison array (e.g., ['<', 3], ['>=', 10])
	 * @return bool
	 */
	protected function matchesNumericComparison( mixed $value, array $comparison ): bool {
		if ( !is_numeric( $value ) ) {
			return false;
		}

		$operator = $comparison[0];
		$threshold = (float)$comparison[1];
		$actualNumeric = (float)$value;

		return match ( $operator ) {
			'<' => $actualNumeric < $threshold,
			'>' => $actualNumeric > $threshold,
			'<=' => $actualNumeric <= $threshold,
			'>=' => $actualNumeric >= $threshold,
			default => false
		};
	}

	/**
	 * Filter name to store in fraud db
	 * @return string
	 */
	protected function makeFilterName() {
		return 'PatternFilter_' . $this->patternName;
	}

}
