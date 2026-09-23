<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\DonationInterface\Configuration\GatewayConfigurationFactory;
use MediaWiki\MediaWikiServices;

return [
	'DonationInterface.GatewayConfigurationFactory' => static function ( MediaWikiServices $services ): GatewayConfigurationFactory {
		$config = $services->getMainConfig();

		$options = new ServiceOptions(
			GatewayConfigurationFactory::CONSTRUCTOR_OPTIONS,
			$config
		);

		return new GatewayConfigurationFactory( $options );
	}
];
