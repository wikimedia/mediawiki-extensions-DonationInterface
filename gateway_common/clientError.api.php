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
		$logger->error( 'Client side error', $errorData );
	}
}
