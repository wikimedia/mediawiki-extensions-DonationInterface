<?php

namespace MediaWiki\Extension\DonationInterface\Api;

use DonationLoggerFactory;
use DonorPortal;
use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiUsageException;
use MediaWiki\Extension\DonationInterface\DonorPortal\ActivityTrackingTrait;
use Wikimedia\ParamValidator\ParamValidator;

abstract class ApiRecurringModifyBase extends ApiBase {

	use ActivityTrackingTrait;

	private const LOGGER_IDENTIFIER = 'ApiRecurringModifyBase';
	private const LOGGER_USE_SYSLOG = true;
	private const LOGGER_DEBUG_VERBOSE_LEVEL = true;
	private const LOGGER_PREFIX = null;
	private const LOGGER_SUFFIX = '';
	private const ERROR_NO_SESSION = 'no-session';
	private const ERROR_CONTACT_ID = 'bad-contact-id';
	private const ERROR_CONTRIBUTION_RECUR_ID = 'bad-contribution-recur-id';

	/** @inheritDoc */
	public function isReadMode() {
		return false;
	}

	/** @inheritDoc */
	public function mustBePosted() {
		return true;
	}

	/**
	 * After IDs have been validated, perform any additional validation
	 * and send the recurring-modify queue message.
	 *
	 * @return void
	 */
	abstract protected function performRecurringModification(): void;

	public function execute() {
		$logger = DonationLoggerFactory::getLoggerFromParams(
			self::LOGGER_IDENTIFIER,
			self::LOGGER_USE_SYSLOG,
			self::LOGGER_DEBUG_VERBOSE_LEVEL,
			self::LOGGER_SUFFIX,
			self::LOGGER_PREFIX );

		$params = $this->extractRequestParams();
		$donorSummary = $this->getRequest()->getSessionData( DonorPortal::SESSION_KEY );
		if ( !$donorSummary ) {
			$logger->error( "No donorportal session for this request with params: " . json_encode( $params ) );
			throw ApiUsageException::newWithMessage( $this, 'apierror-donorportal-no-session', self::ERROR_NO_SESSION );
		}
		if ( $params['contact_id'] !== $donorSummary['id'] ) {
			$logger->error( "Contact ID mismatch for request with params: " . json_encode( $params ) );
			throw ApiUsageException::newWithMessage( $this, 'apierror-donorportal-bad-contact-id', self::ERROR_CONTACT_ID );
		}
		$foundRecurID = false;
		foreach ( $donorSummary['recurringContributions'] as $recurring ) {
			if ( $params['contribution_recur_id'] === $recurring['id'] ) {
				$foundRecurID = true;
				break;
			}
		}
		if ( !$foundRecurID ) {
			$logger->error( "Contribution recur ID not found for request with params: " . json_encode( $params ) );
			throw ApiUsageException::newWithMessage( $this, 'apierror-donorportal-bad-contribution-recur-id', self::ERROR_CONTRIBUTION_RECUR_ID );
		}
		$this->performRecurringModification();
	}

	/**
	 * Returns an array of common parameters for subclasses to use in getAllowedParams()
	 *
	 * @return array[]
	 */
	protected function getBaseParameters() {
		return [
			'contact_id' => [ ParamValidator::PARAM_TYPE => 'integer', ParamValidator::PARAM_REQUIRED => true ],
			'checksum' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => true ],
			'contribution_recur_id' => [ ParamValidator::PARAM_TYPE => 'integer', ParamValidator::PARAM_REQUIRED => true ],
			'wmf_campaign' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => false ],
			'wmf_medium' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => false ],
			'wmf_source' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => false ],
		];
	}
}
