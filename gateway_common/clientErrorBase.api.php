<?php

use Wikimedia\ParamValidator\ParamValidator;

/**
 * Client-side error logging API
 */
abstract class ClientErrorBaseApi extends ApiBase {

	public function execute() {
		$sessionData = WmfFramework::getSessionValue( 'Donor' );

		if ( !$this->validateSessionData( $sessionData ) ) {
			// Only log errors from ppl with a legitimate donation attempt
			return;
		}
		if ( RequestContext::getMain()->getUser()->pingLimiter( 'clienterror' ) ) {
			// Allow rate limiting by setting e.g. $wgRateLimits['clienterror']['ip']
			return;
		}
		$errorData = $this->extractRequestParams();
		$this->addExtraData( $sessionData, $errorData );
		$logger = $this->getLogger( $sessionData );
		$logger->error( 'Client side error: ' . print_r( $errorData, true ) );
	}

	protected function validateSessionData( ?array $sessionData ): bool {
		return !empty( $sessionData );
	}

	abstract protected function getLogger( array $sessionData ): \Monolog\Logger;

	protected function addExtraData( array $sessionData, array &$errorData ): void {
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
