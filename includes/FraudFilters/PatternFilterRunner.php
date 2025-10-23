<?php

namespace MediaWiki\Extension\DonationInterface\FraudFilters;

use MediaWiki\Config\Config;
use Psr\Log\LoggerInterface;

class PatternFilterRunner implements PreAuthorizeFilter {

	protected Config $config;
	protected LoggerInterface $fraudLogger;

	public function __construct(
		Config $config, LoggerInterface $fraudLogger
	) {
		$this->config = $config;
		$this->fraudLogger = $fraudLogger;
	}

	public function onPreAuthorize( array &$riskScores, array $transactionValues ): void {
		$filterConfig = $this->config->get( 'DonationInterfacePatternFilters' );
		if ( !$filterConfig || empty( $filterConfig['PreAuthorize'] ) ) {
			$this->fraudLogger->debug(
				'No "PreAuthorize" key found under $wgDonationInterfacePatternFilters'
			);
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
			$this->fraudLogger->debug(
				"Running pattern filter for '$patternName'"
			);
			$filter->run( $riskScores, $transactionValues, $this->fraudLogger );
		}
	}
}
