<?php

/**
 * FIXME: Jenkins still not installing DonationInterface composer deps
 * use PayWithAmazon\ResponseInterface as PwaResponseInterface;
 */

/**
 * Stubs out the functionality of the ResponseParser from the Login and Pay with
 * Amazon SDK.  TODO: replace with PHPUnit method return mocks when Jenkins
 * is running new enough PHPUnit.  We only use toArray.
 */
class MockAmazonResponse { // FIXME: implements PwaResponseInterface {

	protected $response;

	public function __construct( $response = array() ) {
		$this->response = $response;
	}

	public function getBillingAgreementDetailsStatus( $response ) {

	}

	public function toArray() {
		return $this->response;
	}

	public function toJson() {

	}

	public function toXml() {

	}
}
