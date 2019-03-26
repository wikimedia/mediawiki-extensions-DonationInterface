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
		return [
			'message' => [ ApiBase::PARAM_TYPE => 'string', ApiBase::PARAM_REQUIRED => true ],
			'file' => [ ApiBase::PARAM_TYPE => 'string', ApiBase::PARAM_REQUIRED => false ],
			'line' => [ ApiBase::PARAM_TYPE => 'string', ApiBase::PARAM_REQUIRED => false ],
			'col' => [ ApiBase::PARAM_TYPE => 'string', ApiBase::PARAM_REQUIRED => false ],
			'userAgent' => [ ApiBase::PARAM_TYPE => 'string', ApiBase::PARAM_REQUIRED => true ],
			'stack' => [ ApiBase::PARAM_TYPE => 'string', ApiBase::PARAM_REQUIRED => false ],
		];
	}

	/**
	 * Don't require API read rights
	 * @return bool
	 */
	public function isReadMode() {
		return false;
	}
}
