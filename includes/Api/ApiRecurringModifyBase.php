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
	private const ERROR_TOKEN = 'bad-token';
	private const ERROR_NOT_MODIFIABLE = 'not-modifiable';
	private const ERROR_AMOUNT = 'bad-amount';
	private const MODIFIABLE_STATUSES = [ 'In Progress', 'Pending', 'Failing', 'Processing', 'Overdue' ];

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
		if ( !DonorPortal::matchToken( $this->getRequest()->getSession(), $params['donorportal_token'] ) ) {
			$logger->error( "Bad donor portal token for request with params: " . json_encode( $params ) );
			throw ApiUsageException::newWithMessage( $this, 'apierror-donorportal-bad-token', self::ERROR_TOKEN );
		}
		if ( $params['contact_id'] !== $donorSummary['id'] ) {
			$logger->error( "Contact ID mismatch for request with params: " . json_encode( $params ) );
			throw ApiUsageException::newWithMessage( $this, 'apierror-donorportal-bad-contact-id', self::ERROR_CONTACT_ID );
		}
		$recurringRecord = null;
		foreach ( $donorSummary['recurringContributions'] as $recurring ) {
			if ( $params['contribution_recur_id'] === $recurring['id'] ) {
				$recurringRecord = $recurring;
				break;
			}
		}
		if ( !$recurringRecord ) {
			$logger->error( "Contribution recur ID not found for request with params: " . json_encode( $params ) );
			throw ApiUsageException::newWithMessage( $this, 'apierror-donorportal-bad-contribution-recur-id', self::ERROR_CONTRIBUTION_RECUR_ID );
		}
		if ( empty( $recurringRecord['can_modify'] ) ||
			!in_array( $recurringRecord['status'] ?? '', self::MODIFIABLE_STATUSES, true )
		) {
			$logger->error( "Recurring record cannot be modified for request with params: " . json_encode( $params ) );
			throw ApiUsageException::newWithMessage(
				$this, 'apierror-donorportal-not-modifiable', self::ERROR_NOT_MODIFIABLE
			);
		}
		$this->performRecurringModification();
	}

	/**
	 * Fail unless the amount is above zero and not over the maximum that the donor portal form allows.
	 *
	 * @param string $amount
	 * @throws ApiUsageException
	 */
	protected function validateAmount( string $amount ): void {
		$max = (float)$this->getConfig()->get( 'DonationInterfaceRecurringUpgradeMaxUSD' );
		if ( !is_numeric( $amount ) || (float)$amount <= 0 || (float)$amount > $max ) {
			throw ApiUsageException::newWithMessage( $this, 'apierror-donorportal-bad-amount', self::ERROR_AMOUNT );
		}
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
			'donorportal_token' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => true ],
			'contribution_recur_id' => [ ParamValidator::PARAM_TYPE => 'integer', ParamValidator::PARAM_REQUIRED => true ],
			'wmf_campaign' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => false ],
			'wmf_medium' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => false ],
			'wmf_source' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => false ],
		];
	}
}
