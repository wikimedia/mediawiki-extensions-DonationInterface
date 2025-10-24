<?php

namespace MediaWiki\Extension\DonationInterface\FraudFilters;

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
	 * @return void
	 */
	public function run( array &$riskScores, array $transactionValues ) {
		foreach ( $this->values as $propertyName => $value ) {
			// Type coercion is OK here so we just use !=. This lets us write
			// 2.75 in the filter to match the string "2.75" from $transactionValues
			if ( ( $transactionValues[$propertyName] ?? null ) != $value ) {
				return;
			}
		}

		$riskScores[$this->makeFilterName()] = $this->failScore;
	}

	/**
	 * Filter name to store in fraud db
	 * @return string
	 */
	protected function makeFilterName() {
		return 'PatternFilter_' . $this->patternName;
	}

}
