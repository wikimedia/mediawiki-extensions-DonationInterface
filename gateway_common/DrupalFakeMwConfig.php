<?php

class DrupalFakeMwConfig implements Config {

	public function __construct() {
	}

	public function get( $name ) {
		return '';
	}

	public function has( $name ) {
		// @phan-suppress-previous-line PhanPluginNeverReturnMethod
		throw new BadMethodCallException( 'Not implemented' );
	}
}
