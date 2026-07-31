<?php

namespace MediaWiki\Extension\DonationInterface\ComboWiki\Data;

class DonationDetails {
	/**
	 * Array mapping key names to their found values.
	 *
	 * Example:
	 * [
	 * 	'amount' => '100',
	 * 	'wmf_medium' => 'endowment',
	 * ]
	 *
	 * @var array<string, mixed> $data
	 */
	protected array $data = [];

	// TODO: Right now sources used only as feature flag 'Normalize' or 'Do Not Normalize'
	// TODO: consider simplifying this and remove granularity of post/get/session/null values
	// TODO: put clarify on if we should change the source everytime we update/change a value outside of DataIntegrator ( data fetcher )
	/**
	 * Array mapping key names to their sources (aka where they were found).
	 *
	 * Example:
	 * [
	 * 	'amount' => 'post',
	 * 	'wmf_medium' => 'session',
	 * ]
	 *
	 * @var array<string, string|null> $dataSources
	 */
	protected array $dataSources = [];

	public function getSource( string $key ): ?string {
		return $this->dataSources[$key] ?? null;
	}

	public function setSource( string $key, ?string $source ): void {
		$this->dataSources[$key] = $source;
	}

	public function getValue( string $key, string $default = '' ): string {
		if ( !isset( $this->data[$key] ) || $this->data[$key] == '' ) {
			return $default;
		}
		return $this->data[$key];
	}

	public function setValue( string $key, mixed $value ): void {
		$this->data[$key] = $value;
	}

	/**
	 * @param string $key
	 * @return bool true if the $key value is found with value not in [ null, '' ].
	 */
	public function isValueSet( string $key ): bool {
		return isset( $this->data[$key] ) && $this->data[$key] !== '';
	}

	public function remove( string $key ): void {
		unset( $this->data[$key] );
		unset( $this->dataSources[$key] );
	}

	public function getData(): array {
		return $this->data;
	}

	public function setData( array $data ): void {
		$this->data = $data;
	}

	public function getDataSources(): array {
		return $this->dataSources;
	}

	public function setDataSources( array $dataSources ): void {
		$this->dataSources = $dataSources;
	}
}
