<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\DonationInterface\Configuration\GatewayConfigurationFactory;
use MediaWiki\Extension\DonationInterface\Configuration\GatewayRouter;
use MediaWiki\MediaWikiServices;

return [
	'DonationInterface.GatewayConfigurationFactory' => static function ( MediaWikiServices $services ): GatewayConfigurationFactory {
		$config = $services->getMainConfig();

		$options = new ServiceOptions(
			GatewayConfigurationFactory::CONSTRUCTOR_OPTIONS,
			$config
		);

		return new GatewayConfigurationFactory( $options );
	},
	'DonationInterface.GatewayRouter' => static function ( MediaWikiServices $services ): GatewayRouter {
		$config = $services->getMainConfig();

		$options = new ServiceOptions(
			GatewayRouter::CONSTRUCTOR_OPTIONS,
			$config
		);

		return new GatewayRouter(
			$options, $services->getService( 'DonationInterface.GatewayConfigurationFactory' )
		);
	},
];
