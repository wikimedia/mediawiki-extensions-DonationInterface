<?php

namespace MediaWiki\Extension\DonationInterface\FraudFilters;

use MediaWiki\Config\Config;
use MediaWiki\Session\Session;
use Psr\Log\LoggerInterface;
use Wikimedia\ObjectCache\BagOStuff;

class VelocityFilterRunner implements PreAuthorizeFilter {

	protected BagOStuff $cache;
	protected Session $session;
	protected Config $config;
	protected LoggerInterface $fraudLogger;

	public function __construct(
		BagOStuff $cache,
		Session $session,
		Config $config,
		LoggerInterface $fraudLogger
	) {
		$this->cache = $cache;
		$this->session = $session;
		$this->config = $config;
		$this->fraudLogger = $fraudLogger;
	}

	public function onPreAuthorize( array &$riskScores, array $transactionValues ): void {
		$filterConfig = $this->config->get( 'DonationInterfaceVelocityFilters' );
		if ( !$filterConfig || empty( $filterConfig['PreAuthorize'] ) ) {
			$this->fraudLogger->debug(
				'No "PreAuthorize" key found under $wgDonationInterfaceVelocityFilters'
			);
			return;
		}
		$this->runFilters( $filterConfig['PreAuthorize'], $riskScores, $transactionValues );
	}

	protected function runFilters( array $config, array &$riskScores, array $transactionValues ): void {
		foreach ( $config as $propertyName => $settings ) {
			$filter = new GenericVelocityFilter(
				$propertyName,
				$settings['threshold'],
				$settings['timeout'],
				$settings['failScore']
			);
			$this->fraudLogger->debug(
				"Running velocity filter for '$propertyName'"
			);
			$filter->run( $riskScores, $transactionValues, $this->cache, $this->session, $this->fraudLogger );
		}
	}
}
