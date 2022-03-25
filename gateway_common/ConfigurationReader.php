<?php

use Symfony\Component\Yaml\Parser;

/**
 * Read in yaml-based config files.
 */
class ConfigurationReader {

	/**
	 * @var array
	 */
	protected $configDirectories = [];

	/**
	 * @param string $gateway
	 * @param string $variant
	 */
	public static function createForGateway( $gateway, $variant ) {
		// move gateway and variant wiring into here at the end.
	}

	/**
	 * @param string $path
	 *
	 * @return bool
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
