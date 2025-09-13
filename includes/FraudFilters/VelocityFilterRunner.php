<?php

namespace MediaWiki\Extension\DonationInterface\FraudFilters;

use MediaWiki\Config\Config;
use MediaWiki\Session\Session;
use Wikimedia\ObjectCache\BagOStuff;

class VelocityFilterRunner implements PreAuthorizeFilter {

	protected BagOStuff $cache;
	protected Session $session;
	protected Config $config;

	public function __construct(
		BagOStuff $cache,
		Session $session,
		Config $config
	) {
		$this->cache = $cache;
		$this->session = $session;
		$this->config = $config;
	}

	public function onPreAuthorize( array &$riskScores, array $transactionValues ): void {
		$filterConfig = $this->config->get( 'DonationInterfaceVelocityFilters' );
		if ( !$filterConfig || empty( $filterConfig['PreAuthorize'] ) ) {
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
			$filter->run( $riskScores, $transactionValues, $this->cache, $this->session );
		}
	}
}
