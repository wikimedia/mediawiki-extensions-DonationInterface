<?php

namespace MediaWiki\Extension\DonationInterface\FraudFilters;

use MediaWiki\Config\Config;

class PatternFilterRunner implements PreAuthorizeFilter {

	protected Config $config;

	public function __construct(
		Config $config
	) {
		$this->config = $config;
	}

	public function onPreAuthorize( array &$riskScores, array $transactionValues ): void {
		$filterConfig = $this->config->get( 'DonationInterfacePatternFilters' );
		if ( !$filterConfig || empty( $filterConfig['PreAuthorize'] ) ) {
			return;
		}
		$this->runFilters( $filterConfig['PreAuthorize'], $riskScores, $transactionValues );
	}

	/**
	 * @param array $config
	 * [
	 * 		'annoyingFraudster' => [
	 * 			'first_name' => 'Cookie',
	 * 			'last_name' => 'Monster'
	 * 		]
	 * ],
	 * @param array &$riskScores
	 * @param array $transactionValues
	 * @return void
	 */
	protected function runFilters( array $config, array &$riskScores, array $transactionValues ): void {
		foreach ( $config as $patternName => $settings ) {
			$failScore = $settings['failScore'] ?? 100;
			unset( $settings['failScore'] );
			$filter = new GenericPatternFilter(
				$patternName,
				$failScore,
				$settings
			);
			$filter->run( $riskScores, $transactionValues );
		}
	}
}
