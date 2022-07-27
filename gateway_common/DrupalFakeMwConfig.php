<?php

class DrupalFakeMwConfig implements Config {

	public function __construct() {
	}

	public function get( $name ) {
		return '';
	}

	public function has( $name ) {
		throw new BadMethodCallException( 'Not implemented' );
	}
}
