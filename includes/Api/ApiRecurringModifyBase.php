<?php

namespace MediaWiki\Extension\DonationInterface\Api;

use DonorPortal;
use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiUsageException;
use MediaWiki\Extension\DonationInterface\DonorPortal\ActivityTrackingTrait;
use Wikimedia\ParamValidator\ParamValidator;

abstract class ApiRecurringModifyBase extends ApiBase {

	use ActivityTrackingTrait;

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
		$params = $this->extractRequestParams();
		$donorSummary = $this->getRequest()->getSessionData( DonorPortal::SESSION_KEY );
		if ( !$donorSummary ) {
			throw ApiUsageException::newWithMessage( $this, 'apierror-donorportal-no-session' );
		}
		if ( $params['contact_id'] !== $donorSummary['id'] ) {
			throw ApiUsageException::newWithMessage( $this, 'apierror-donorportal-bad-contact-id' );
		}
		$foundRecurID = false;
		foreach ( $donorSummary['recurringContributions'] as $recurring ) {
			if ( $params['contribution_recur_id'] === $recurring['id'] ) {
				$foundRecurID = true;
				break;
			}
		}
		if ( !$foundRecurID ) {
			throw ApiUsageException::newWithMessage( $this, 'apierror-donorportal-bad-contribution-recur-id' );
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
