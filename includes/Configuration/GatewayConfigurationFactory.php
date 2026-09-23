<?php

namespace MediaWiki\Extension\DonationInterface\Configuration;

use MediaWiki\Config\ServiceOptions;

class GatewayConfigurationFactory {

	protected array $configurations;

	/**
	 * @var array
	 */
	public const CONSTRUCTOR_OPTIONS = [
		'ExtensionDirectory', // Core MW config
		'DonationInterfaceGatewayAdapters',
		'DonationInterfaceLocalConfigurationDirectory',
		'DonationInterfaceVariantConfigurationDirectory',
		// FIXME would be nicer to just rely on DonationInterfaceGatewayAdapters
		'AmazonGatewayEnabled',
		'AdyenCheckoutGatewayEnabled',
		'BraintreeGatewayEnabled',
		'DlocalGatewayEnabled',
		'GravyGatewayEnabled',
		'IngenicoGatewayEnabled',
		'PaypalExpressGatewayEnabled',
	];

	public function __construct( private readonly ServiceOptions $options ) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	public function getConfigurationForGatewayAndVariant( string $gateway, ?string $variant = null ): array {
		$key = self::makeKey( $gateway, $variant );
		if ( !isset( $this->configurations[ $key ] ) ) {
			$reader = ConfigurationReader::createForGatewayAndVariant(
				$this->options->get( 'ExtensionDirectory' ) . DIRECTORY_SEPARATOR . 'DonationInterface',
				$this->options->get( 'DonationInterfaceLocalConfigurationDirectory' ),
				$this->options->get( 'DonationInterfaceVariantConfigurationDirectory' ),
				$gateway,
				$variant
			);
			$this->configurations[ $key ] = $reader->readConfiguration();
		}
		return $this->configurations[ $key ];
	}

	public function getAllEnabledConfigurationsForVariant( ?string $variant = null ): array {
		$configs = [];
		foreach ( $this->getAllEnabledGateways() as $gateway ) {
			$configs[$gateway] = $this->getConfigurationForGatewayAndVariant( $gateway, $variant );
		}
		return $configs;
	}

	/**
	 * Gets a list of all gateways defined in DonationInterfaceGatewayAdapters and enabled
	 *
	 * @return string[]
	 */
	public function getAllEnabledGateways(): array {
		$enabledGateways = [];
		foreach ( $this->options->get( 'DonationInterfaceGatewayAdapters' ) as $gateway => $className ) {
			$configPrefix = str_replace( 'Adapter', 'Gateway', $className );
			$enabledKey = $configPrefix . 'Enabled';
			if ( $this->options->get( $enabledKey ) ) {
				$enabledGateways[] = $gateway;
			}
		}
		return $enabledGateways;
	}

	protected static function makeKey( string $gateway, ?string $variant = null ): string {
		return $variant ? $gateway . '.' . $variant : $gateway;
	}
}
