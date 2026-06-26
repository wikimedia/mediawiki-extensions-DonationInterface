<?php

namespace MediaWiki\Extension\DonationInterface\Tests;

use DonationLoggerFactory;
use MediaWiki\Context\RequestContext;
use SmashPig\Core\Context;
use SmashPig\Core\GlobalConfiguration;
use SmashPig\Tests\TestingContext;
use SmashPig\Tests\TestingDatabase;
use SmashPig\Tests\TestingGlobalConfiguration;
use SmashPig\Tests\TestingProviderConfiguration;
use Wikimedia\TestingAccessWrapper;

trait SmashPigEnvironmentTrait {

	protected GlobalConfiguration $smashPigGlobalConfig;

	/**
	 * Gets the service container to be used with integration tests.
	 */
	abstract protected function getServiceContainer();

	/**
	 * Sets up a testing SmashPig context
	 * @return void
	 */
	protected function setUpSmashPigContext(): void {
		TestingDatabase::clearStatics();
		// Replace real SmashPig context with test version that lets us
		// override provider configurations that may be set in code
		$this->smashPigGlobalConfig = TestingGlobalConfiguration::create();
		TestingContext::init( $this->smashPigGlobalConfig );
		Context::get()->setSourceType( 'payments' );
		Context::get()->setSourceName( 'DonationInterface' );
	}

	/**
	 * @param string $provider
	 * @return TestingProviderConfiguration
	 */
	protected function setSmashPigProvider( string $provider ): TestingProviderConfiguration {
		$providerConfig = TestingProviderConfiguration::createForProvider(
			$provider,
			$this->smashPigGlobalConfig
		);
		TestingContext::get()->providerConfigurationOverride = $providerConfig;
		return $providerConfig;
	}

	/**
	 * Clear everything out so as not to contaminate the next test run
	 * @return void
	 */
	protected function resetEnvironment(): void {
		RequestContext::resetMain();

		// Wipe out the $instance of these classes to make sure they're
		// re-created with fresh gateway instances for the next test
		$singleton_classes = [
			'Gateway_Extras_ConversionLog',
			'Gateway_Extras_CustomFilters',
			'Gateway_Extras_CustomFilters_Functions',
			'Gateway_Extras_CustomFilters_IP_Velocity',
			'Gateway_Extras_CustomFilters_MinFraud',
			'Gateway_Extras_CustomFilters_Referrer',
			'Gateway_Extras_CustomFilters_Source',
			'Gateway_Extras_SessionVelocityFilter',
		];
		foreach ( $singleton_classes as $singleton_class ) {
			$unwrapped = TestingAccessWrapper::newFromClass( $singleton_class );
			$unwrapped->instance = null;
		}
		// Reset SmashPig context
		Context::set();
		self::setUpSmashPigContext();
		$this->getServiceContainer()->getObjectCacheFactory()
			->getLocalClusterInstance()->clear();
		DonationLoggerFactory::$overrideLogger = null;
	}
}
