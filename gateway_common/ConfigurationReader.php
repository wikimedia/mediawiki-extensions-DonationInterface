<?php

use Symfony\Component\Yaml\Parser;

/**
 * Read in yaml-based config files.
 * TODO Knowledge about configuration layout should be encapsulated.
 * See https://phabricator.wikimedia.org/T291699
 */
class ConfigurationReader {

	/**
	 * @var array
	 */
	protected $configDirectories = [];

	/**
	 * @param string $gateway short name, e.g. 'adyen' or 'ingenico'
	 * @param string|null $variant querystring parameter used to change form appearance
	 * @param Config $mwConfig object to access MediaWiki core configuration
	 *
	 * @return static
	 */
	public static function createForGateway( $gateway, $variant, Config $mwConfig ) {
		$extensionBaseDir = $mwConfig->get( 'ExtensionDirectory' ) . DIRECTORY_SEPARATOR
			. 'DonationInterface';
		/** The following conditional can be deleted when we get rid of WmfFramework */
		if ( !is_dir( $extensionBaseDir ) ) {
			$extensionBaseDir = __DIR__ . DIRECTORY_SEPARATOR . '..';
		}
		$configurationReader = new ConfigurationReader();

		// Register general config dir (shipped defaults)
		$generalBaseConfigDir = $extensionBaseDir . DIRECTORY_SEPARATOR . 'config';
		$configurationReader->registerConfigDirectory( $generalBaseConfigDir );

		// Register gateway base config dir (gateway-specific shipped defaults)
		$gatewayBaseConfigDir = $extensionBaseDir . DIRECTORY_SEPARATOR . $gateway . '_gateway'
			. DIRECTORY_SEPARATOR . 'config';
		$configurationReader->registerConfigDirectory( $gatewayBaseConfigDir );

		// Register local config dir if set as well as gateway-specific subdirectory
		$localConfigDir = $mwConfig->get( 'DonationInterfaceLocalConfigurationDirectory' );
		if ( $localConfigDir ) {
			$configurationReader->registerConfigDirectory( $localConfigDir );
			$gatewaySpecificSuffix = DIRECTORY_SEPARATOR . $gateway;
			$configurationReader->registerConfigDirectory( $localConfigDir . $gatewaySpecificSuffix );
		}

		// Register variant config dir if set (to vary behavior by a querystring param)
		// Note that we are currently only setting a gateway-specific dir here, but could
		// easily support gateway-agnostic variant overrides by adding another.
		$variantConfigDir = $mwConfig->get( 'DonationInterfaceVariantConfigurationDirectory' );
		if ( $variant !== null
			&& $variantConfigDir
			&& preg_match( '/^[a-zA-Z0-9_]+$/', $variant )
		) {
			$variantConfigDirSuffix = DIRECTORY_SEPARATOR . $variant . DIRECTORY_SEPARATOR . $gateway;
			$configurationReader->registerConfigDirectory( $variantConfigDir . $variantConfigDirSuffix );
		}

		return $configurationReader;
	}

	/**
	 * @param string $path New configuration directory to register. Files in this
	 *  directory will override files with the same name in previously registered
	 *  directories.
	 *
	 * @return bool true if directory was not yet registered
	 */
	public function registerConfigDirectory( string $path ): bool {
		if ( !array_search( $path, $this->configDirectories ) ) {
			array_push( $this->configDirectories, $path );
			return true;
		} else {
			return false;
		}
	}

	/**
	 * @param string $path
	 *
	 * @return bool
	 */
	public function unregisterConfigDirectory( string $path ): bool {
		if ( ( $key = array_search( $path, $this->configDirectories ) ) !== false ) {
			unset( $this->configDirectories[$key] );
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Reads configuration in from all registered directories, with directories
	 * registered later taking precedence over those registered earlier.
	 *
	 * @return array With a top-level entry for each unique filename across
	 *  all configuration directories.
	 */
	public function readConfiguration(): array {
		if ( count( $this->configDirectories ) > 0 ) {
			$config = [];
			foreach ( $this->configDirectories as $configDirectory ) {
				$config = $this->setConfigurationFromDirectory( $configDirectory, $config );
			}
			return $config;
		} else {
			throw new UnexpectedValueException( 'Trying to read config directories but no directories registered!' );
		}
	}

	protected function setConfigurationFromDirectory( $directory, $config = [] ) {
		$yaml = new Parser();
		$globPattern = $directory . DIRECTORY_SEPARATOR . '*.yaml';
		foreach ( glob( $globPattern ) as $path ) {
			$pieces = explode( DIRECTORY_SEPARATOR, $path );
			$key = substr( array_pop( $pieces ), 0, -5 );
			$config[$key] = $yaml->parse( file_get_contents( $path ) );
		}
		return $config;
	}
}
