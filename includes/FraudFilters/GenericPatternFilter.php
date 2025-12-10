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
	 * Check if a value matches a wildcard pattern
	 *
	 * @param mixed $value The actual value to check
	 * @param string $pattern The pattern with wildcards (e.g., '*@aol.com')
	 * @return bool
	 */
	protected function matchesWildcard( mixed $value, string $pattern ): bool {
		if ( $value === null ) {
			return false;
		}

		// Strip the wildcard and check if the value contains the remaining part
		$substring = str_replace( '*', '', $pattern );

		return str_contains( (string)$value, $substring );
	}

	/**
	 * Filter name to store in fraud db
	 * @return string
	 */
	protected function makeFilterName() {
		return 'PatternFilter_' . $this->patternName;
	}

}
