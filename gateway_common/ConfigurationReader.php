<?php

use Symfony\Component\Yaml\Parser;

/**
 * This lets us read variant configurations, but it's a step away from being
 * able to move all the gateway config files into SmashPig. TODO: reconcile
 */
class ConfigurationReader {

	/**
	 * @var string $baseDirectory
	 */
	protected $baseDirectory;

	/**
	 * @var string $localSettingDirectory
	 */
	protected $localSettingDirectory;

	/**
	 * @var string
	 */
	protected $variantBaseDirectory;

	/**
	 * @var string
	 */
	protected $gatewayIdentifier;

	public function __construct( $baseDirectory, $gatewayIdentifier, $localSettingDirectory = null, $variantBaseDirectory = null ) {
		$this->baseDirectory = $baseDirectory;
		$this->variantBaseDirectory = $variantBaseDirectory;
		$this->localSettingDirectory = $localSettingDirectory;
		$this->gatewayIdentifier = $gatewayIdentifier;
	}

	public function readConfiguration( $variant = null ) {
		$config = $this->setConfigurationFromDirectory(
			$this->baseDirectory . DIRECTORY_SEPARATOR . 'config'
		);
		if ( $this->localSettingDirectory ) {
			$localSettings = implode(
				DIRECTORY_SEPARATOR,
				[
					$this->localSettingDirectory,
					$this->gatewayIdentifier
				]
			);
			if ( is_dir( $localSettings ) ) {
				$config = $this->setConfigurationFromDirectory(
					$localSettings, $config
				);
			}
		}
		$config = $this->addVariant( $config, $variant );
		return $config;
	}

	protected function addVariant( $config, $variant ) {
		if (
			$variant &&
			$this->variantBaseDirectory &&
			preg_match( '/^[a-zA-Z0-9_]+$/', $variant )
		) {
			$variantDirectory = implode( DIRECTORY_SEPARATOR, [
					$this->variantBaseDirectory,
					$variant,
					$this->gatewayIdentifier
				] );
			if ( is_dir( $variantDirectory ) ) {
				 return $this->setConfigurationFromDirectory( $variantDirectory, $config );
			}
		}
		return $config;
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
