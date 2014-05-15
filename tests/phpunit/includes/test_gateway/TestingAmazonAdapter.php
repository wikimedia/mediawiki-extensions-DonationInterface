<?php

/**
 * TestingAmazonAdapter
 */
class TestingAmazonAdapter extends AmazonAdapter {
	use TTestingAdapter;

	public static $mockClient;

	protected function getPwaClient() {
		return self::$mockClient;
	}
}
