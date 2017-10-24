<?php
/**
 * Client-side error logging API
 */
class ClientErrorApi extends ApiBase {
	public function execute() {
		$sessionData = WmfFramework::getSessionValue( 'Donor' );

		if ( empty( $sessionData ) || empty( $sessionData['gateway'] ) ) {
			// Only log errors from ppl with a legitimate donation attempt
			return;
		}
		$gatewayName = $sessionData['gateway'];
		$gatewayClass = DonationInterface::getAdapterClassForGateway( $gatewayName );
		$gateway = new $gatewayClass();

		$errorData = $this->extractRequestParams();
		$errorData['donationData'] = $gateway->getData_Unstaged_Escaped();
		$logger = DonationLoggerFactory::getLogger( $gateway );
		$logger->error( 'Client side error: ' . print_r( $errorData, true ) );
	}

	public function getAllowedParams() {
		return array(
			'message' => array( ApiBase::PARAM_TYPE => 'string', ApiBase::PARAM_REQUIRED => true ),
			'file' => array( ApiBase::PARAM_TYPE => 'string', ApiBase::PARAM_REQUIRED => true ),
			'line' => array( ApiBase::PARAM_TYPE => 'string', ApiBase::PARAM_REQUIRED => true ),
			'col' => array( ApiBase::PARAM_TYPE => 'string', ApiBase::PARAM_REQUIRED => true ),
			'userAgent' => array( ApiBase::PARAM_TYPE => 'string', ApiBase::PARAM_REQUIRED => true ),
			'stack' => array( ApiBase::PARAM_TYPE => 'string', ApiBase::PARAM_REQUIRED => false ),
		);
	}

	/**
	 * Don't require API read rights
	 */
	public function isReadMode() {
		return false;
	}
}
