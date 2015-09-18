<?php

/**
 * TestingAmazonAdapter
 */
class TestingAmazonAdapter extends AmazonAdapter {

	public static $fakeGlobals = array();
	public static $mockClient;

	public $queue_messages = array();

	public static function getGlobal( $name ) {
		if ( array_key_exists( $name, TestingAmazonAdapter::$fakeGlobals ) ) {
			return TestingAmazonAdapter::$fakeGlobals[$name];
		}
		return parent::getGlobal( $name );
	}

	protected function getPwaClient() {
		return self::$mockClient;
	}

	protected function pushMessage( $queue ) {
		$this->queue_messages[$queue][] = $this->getStompTransaction();
	}
}
