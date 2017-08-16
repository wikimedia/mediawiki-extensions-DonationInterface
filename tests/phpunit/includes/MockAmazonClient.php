<?php

/**
 * FIXME: Jenkins mwext-testextension-hhvm still not installing DonationInterface composer deps
 * use PayWithAmazon\PaymentsClientInterface;
 */

/**
 * Stubs out the functionality of the Client class from the Login and Pay with
 * Amazon SDK.  TODO: replace with PHPUnit method return mocks when Jenkins
 * is running new enough PHPUnit.  Only mocking the stuff we use.
 */
class MockAmazonClient { // FIXME: implements PaymentsClientInterface {

	// Each key is a method name whose value is an array of times it's been
	// called, recording all argument values.
	public $calls = array();

	// Keys are method names, values are arrays of error codes such as InvalidPaymentMethod
	// When a code is not found, the operation will return a successful result
	public $returns = array();

	// Similar to $returns, but any entries here are thrown as exceptions
	public $exceptions = array();

	public function __construct( $config = null ) {
	}

	public function __get( $name ) {
	}

	protected function fakeCall( $functionName, $arguments ) {
		$this->calls[$functionName][] = $arguments;
		$status = null;
		$returnIndex = count( $this->calls[$functionName] ) - 1;
		if ( isset( $this->returns[$functionName] ) && isset( $this->returns[$functionName][$returnIndex] ) ) {
			$status = $this->returns[$functionName][$returnIndex];
		}
		if ( isset( $this->exceptions[$functionName] ) && isset( $this->exceptions[$functionName][$returnIndex] ) ) {
			throw $this->exceptions[$functionName][$returnIndex];
		}
		return new MockAmazonResponse( $functionName, $status );
	}

	public function authorize( $requestParameters = array() ) {
		return $this->fakeCall( 'authorize', $requestParameters );
	}

	public function authorizeOnBillingAgreement( $requestParameters = array() ) {
		return $this->fakeCall( 'authorizeOnBillingAgreement', $requestParameters );
	}

	public function cancelOrderReference( $requestParameters = array() ) {
	}

	public function capture( $requestParameters = array() ) {
	}

	public function charge( $requestParameters = array() ) {
	}

	public function closeAuthorization( $requestParameters = array() ) {
	}

	public function closeBillingAgreement( $requestParameters = array() ) {
	}

	public function closeOrderReference( $requestParameters = array() ) {
		return $this->fakeCall( 'closeOrderReference', $requestParameters );
	}

	public function confirmBillingAgreement( $requestParameters = array() ) {
		return $this->fakeCall( 'confirmBillingAgreement', $requestParameters );
	}

	public function confirmOrderReference( $requestParameters = array() ) {
		return $this->fakeCall( 'confirmOrderReference', $requestParameters );
	}

	public function createOrderReferenceForId( $requestParameters = array() ) {
	}

	public function getAuthorizationDetails( $requestParameters = array() ) {
	}

	public function getBillingAgreementDetails( $requestParameters = array() ) {
		return $this->fakeCall( 'getBillingAgreementDetails', $requestParameters );
	}

	public function getCaptureDetails( $requestParameters = array() ) {
		return $this->fakeCall( 'getCaptureDetails', $requestParameters );
	}

	public function getOrderReferenceDetails( $requestParameters = array() ) {
		return $this->fakeCall( 'getOrderReferenceDetails', $requestParameters );
	}

	public function getParameters() {
	}

	public function getProviderCreditDetails( $requestParameters = array() ) {
	}

	public function getProviderCreditReversalDetails( $requestParameters = array() ) {
	}

	public function getRefundDetails( $requestParameters = array() ) {
	}

	public function getServiceStatus( $requestParameters = array() ) {
	}

	public function getUserInfo( $access_token ) {
	}

	public function refund( $requestParameters = array() ) {
	}

	public function reverseProviderCredit( $requestParameters = array() ) {
	}

	public function setBillingAgreementDetails( $requestParameters = array() ) {
		return $this->fakeCall( 'setBillingAgreementDetails', $requestParameters );
	}

	public function setClientId( $value ) {
	}

	public function setMwsServiceUrl( $url ) {
	}

	public function setOrderReferenceDetails( $requestParameters = array() ) {
		return $this->fakeCall( 'setOrderReferenceDetails', $requestParameters );
	}

	public function setProxy( $proxy ) {
	}

	public function setSandbox( $value ) {
	}

	public function validateBillingAgreement( $requestParameters = array() ) {
	}

}
