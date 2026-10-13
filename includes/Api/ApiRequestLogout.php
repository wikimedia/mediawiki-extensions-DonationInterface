<?php

namespace MediaWiki\Extension\DonationInterface\Api;

use CiviproxyConnect;
use DonationLoggerFactory;
use DonorPortal;
use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiUsageException;
use Wikimedia\ParamValidator\ParamValidator;

class ApiRequestLogout extends ApiBase {

	private const LOGGER_IDENTIFIER = 'ApiRequestLogout';
	private const LOGGER_USE_SYSLOG = true;
	private const LOGGER_DEBUG_VERBOSE_LEVEL = true;
	private const LOGGER_PREFIX = null;
	private const LOGGER_SUFFIX = '';

	/** @inheritDoc */
	public function isReadMode() {
		return false;
	}

	/** @inheritDoc */
	public function mustBePosted() {
		return true;
	}

	public function execute() {
		if ( $this->getUser()->pingLimiter( 'requestLogout' ) ) {
			return;
		}
		$logger = DonationLoggerFactory::getLoggerFromParams(
			self::LOGGER_IDENTIFIER,
			self::LOGGER_USE_SYSLOG,
			self::LOGGER_DEBUG_VERBOSE_LEVEL,
			self::LOGGER_SUFFIX,
			self::LOGGER_PREFIX );

		$session = $this->getRequest()->getSession();
		if ( !DonorPortal::matchToken( $session, $this->getParameter( 'donorportal_token' ) ) ) {
			throw ApiUsageException::newWithMessage( $this, 'apierror-donorportal-bad-token', 'bad-token' );
		}
		$session->remove( DonorPortal::SESSION_KEY );
		$contact_id = $this->getRequest()->getVal( 'contact_id' );
		$checksum = $this->getRequest()->getVal( 'checksum' );
		$logger->info( "Received logout request for contact with id: " . $contact_id );
		$result = CiviproxyConnect::invalidateChecksum( $checksum, $contact_id );
		$logger->info( "Checksum invalidated for contact with id: " . $contact_id );
		$this->getResult()->addValue( null, 'result', $result );
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'contact_id' => [
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'checksum' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'donorportal_token' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

}
