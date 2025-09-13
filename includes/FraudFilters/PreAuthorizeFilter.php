<?php

namespace MediaWiki\Extension\DonationInterface\FraudFilters;

interface PreAuthorizeFilter {

	/**
	 * Given the submitted $transactionValues, calculate a risk score and add it
	 * to the $riskScores array
	 * @param array &$riskScores
	 * @param array $transactionValues
	 * @return mixed
	 */
	public function onPreAuthorize( array &$riskScores, array $transactionValues );
}
