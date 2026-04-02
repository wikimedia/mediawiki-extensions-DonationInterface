<?php

namespace MediaWiki\Extension\DonationInterface\Api;

use DonationLoggerFactory;
use MediaWiki\Context\RequestContext;
use SmashPig\Core\DataStores\QueueWrapper;
use Wikimedia\ParamValidator\ParamValidator;

class ApiRequestCancelRecurring extends ApiRecurringModifyBase {

	private const LOGGER_IDENTIFIER = 'ApiRequestCancelRecurring';
	private const LOGGER_USE_SYSLOG = true;
	private const LOGGER_DEBUG_VERBOSE_LEVEL = true;
	private const LOGGER_PREFIX = null;
	private const LOGGER_SUFFIX = '';

	/** @inheritDoc */
	protected function performRecurringModification(): void {
		if ( RequestContext::getMain()->getUser()->pingLimiter( 'requestCancelRecurring' ) ) {
			// Allow rate limiting by setting e.g. $wgRateLimits['requestCancelRecurring']['ip']
			return;
		}
		$logger = DonationLoggerFactory::getLoggerFromParams(
			self::LOGGER_IDENTIFIER,
			self::LOGGER_USE_SYSLOG,
			self::LOGGER_DEBUG_VERBOSE_LEVEL,
			self::LOGGER_SUFFIX,
			self::LOGGER_PREFIX );

		$request = $this->getRequest();
		$logger->info( "Received recurring cancellation message for contact with id: " . $request->getVal( 'contact_id' ) );

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

		$logger->info( "Pushing recurring modification message to queue: " . json_encode( $queueMessage ) );
		QueueWrapper::push( 'recurring-modify', $queueMessage );
		$this->getResult()->addValue( null, 'result', [
			'message' => 'Success',
		] );
		$logger->info( "Recurring modification message queued for contact with id: " . $request->getVal( 'contact_id' ) );
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
