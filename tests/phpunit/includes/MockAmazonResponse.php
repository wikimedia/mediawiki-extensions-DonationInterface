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

	/**
	 * Creates the fake response from JSON in tests/includes/Responses/amazon
	 * @param string $operation The PwaClient function call we're faking
	 * @param string $status Set to fake responses with an error status
	 *		Reads from $operation_$status.json
	 */
	public function __construct( $operation, $status = null ) {
		$statusPart = $status ? '_' . $status : '';
		$filePath = __DIR__ . "/Responses/amazon/{$operation}{$statusPart}.json";
		$json = file_get_contents( $filePath );
		$this->response = json_decode( $json, true );
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
