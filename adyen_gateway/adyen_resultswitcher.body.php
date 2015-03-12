<?php

class AdyenGatewayResult extends GatewayPage {

	/**
	 * Defines the action to take on a Adyen transaction.
	 *
	 * Possible values include 'process', 'challenge',
	 * 'review', 'reject'.  These values can be set during
	 * data processing validation, for instance.
	 *
	 * Hooks are exposed to handle the different actions.
	 *
	 * FIXME: sketchy to have a default value.
	 * Defaults to 'process'.
	 * @var string
	 */
	public $action = 'process';

	/**
	 * An array of form errors
	 * @var array
	 */
	public $errors = array( );

	public function __construct() {
		$this->adapter = new AdyenAdapter();
		parent::__construct();
	}

    protected function handleRequest() {
        $this->handleResultRequest();
    }
}
