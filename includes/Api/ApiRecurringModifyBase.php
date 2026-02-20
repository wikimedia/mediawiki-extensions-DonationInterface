<?php

namespace MediaWiki\Extension\DonationInterface\Api;

use MediaWiki\Api\ApiBase;
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
