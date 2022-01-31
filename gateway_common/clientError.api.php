<?php

use Wikimedia\ParamValidator\ParamValidator;

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
		if ( RequestContext::getMain()->getUser()->pingLimiter( 'clienterror' ) ) {
			// Allow rate limiting by setting e.g. $wgRateLimits['clienterror']['ip']
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
			'message' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => true ],
			'file' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => false ],
			'line' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => false ],
			'col' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => false ],
			'userAgent' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => true ],
			'stack' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => false ],
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
