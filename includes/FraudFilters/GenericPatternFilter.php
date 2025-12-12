<?php

namespace MediaWiki\Extension\DonationInterface\FraudFilters;

use Psr\Log\LoggerInterface;

class GenericPatternFilter {

	protected string $patternName;
	protected int $failScore;
	protected array $values;

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

			// Check if the pattern contains a wildcard
			if ( is_string( $rulePropertyValue ) && str_contains( $rulePropertyValue, '*' ) ) {
				if ( !$this->matchesWildcard( $actualValue, $rulePropertyValue ) ) {
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
	 * Filter name to store in fraud db
	 * @return string
	 */
	protected function makeFilterName() {
		return 'PatternFilter_' . $this->patternName;
	}

}
