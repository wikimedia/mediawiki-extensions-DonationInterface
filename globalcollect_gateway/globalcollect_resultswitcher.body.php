<?php

class GlobalCollectGatewayResult extends GatewayPage {
	/**
	 * Defines the action to take on a GlobalCollect transaction.
	 *
	 * Possible values include 'process', 'challenge',
	 * 'review', 'reject'.  These values can be set during
	 * data processing validation, for instance.
	 *
	 * Defaults to 'process'.
	 * @var string
	 */
	public $action = 'process';

	/**
	 * An array of form errors
	 * @var array
	 */
	public $errors = array( );

	protected $qs_oid = null;

	protected $gatewayIdentifier = GlobalCollectAdapter::IDENTIFIER;

	protected function handleRequest () {
		$this->handleResultRequest();
	}

	/**
	 * Overriding so the answer is correct in case we refactor handleRequest
	 * to use base class's handleResultRequest method.
	 */
	protected function isReturnFramed() {
		return true;
	}
}
