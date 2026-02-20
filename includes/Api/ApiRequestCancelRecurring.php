<?php

namespace MediaWiki\Extension\DonationInterface\Api;

use MediaWiki\Context\RequestContext;
use SmashPig\Core\DataStores\QueueWrapper;
use Wikimedia\ParamValidator\ParamValidator;

class ApiRequestCancelRecurring extends ApiRecurringModifyBase {

	/** @inheritDoc */
	protected function performRecurringModification(): void {
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
		] + $this->getTrackingParametersWithoutPrefix();

		QueueWrapper::push( 'recurring-modify', $queueMessage );
		$this->getResult()->addValue( null, 'result', [
			'message' => 'Success',
		] );
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return $this->getBaseParameters() + [
			'reason' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => true ],
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
