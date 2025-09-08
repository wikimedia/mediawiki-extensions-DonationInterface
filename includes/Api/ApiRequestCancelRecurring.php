<?php

namespace MediaWiki\Extension\DonationInterface\Api;

use MediaWiki\Api\ApiBase;
use MediaWiki\Context\RequestContext;
use SmashPig\Core\DataStores\QueueWrapper;
use Wikimedia\ParamValidator\ParamValidator;

class ApiRequestCancelRecurring extends ApiBase {
	/** @inheritDoc */
	public function isReadMode() {
		return false;
	}

	/** @inheritDoc */
	public function mustBePosted() {
		return true;
	}

	public function execute() {
		if ( RequestContext::getMain()->getUser()->pingLimiter( 'requestCancelRecurring' ) ) {
			// Allow rate limiting by setting e.g. $wgRateLimits['requestCancelRecurring']['ip']
			return;
		}
		$request = $this->getRequest();
		$reason = $this->mapReason( $request->getVal( 'reason' ) );
		$contact_id = $request->getVal( 'contact_id' );
		$checksum = $request->getVal( 'checksum' );
		$contribution_recur_id = $request->getVal( 'contribution_recur_id' );

		$queueMessage = [
			'cancel_reason' => $reason,
			'contact_id' => $contact_id,
			'checksum' => $checksum,
			'contribution_recur_id' => $contribution_recur_id,
			'txn_type' => 'recurring_cancel'
		];

		QueueWrapper::push( 'recurring-modify', $queueMessage );
		$this->getResult()->addValue( null, 'result', [
			'message' => 'Success',
		] );
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'reason' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => true ],
			'contact_id' => [ ParamValidator::PARAM_TYPE => 'integer', ParamValidator::PARAM_REQUIRED => true ],
			'checksum' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => false ],
			'contribution_recur_id' => [ ParamValidator::PARAM_TYPE => 'integer', ParamValidator::PARAM_REQUIRED => false ],
		];
	}

	/**
	 * Ensure we only send one of the reasons from the drop-down list defined in Civi at
	 * ext/wmf-civicrm/Civi/WMFHook/QuickForm.php
	 * @param string|null $getVal
	 * @return string
	 */
	protected function mapReason( ?string $getVal ): string {
		switch ( $getVal ) {
			case 'Financial Reasons':
			case 'Frequency':
			case 'Other Organizations':
			case 'Unintended Recurring Donation':
				return $getVal;
			case 'Cancel Support':
				return 'Wikimedia Foundation related complaint';
			default:
				return 'Other and Unspecified';
		}
	}
}
