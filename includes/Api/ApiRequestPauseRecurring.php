<?php

namespace MediaWiki\Extension\DonationInterface\Api;

use DonationLoggerFactory;
use MediaWiki\Context\RequestContext;
use SmashPig\Core\DataStores\QueueWrapper;
use Wikimedia\ParamValidator\ParamValidator;

class ApiRequestPauseRecurring extends ApiRecurringModifyBase {

	private const LOGGER_IDENTIFIER = 'ApiRequestPauseRecurring';
	private const LOGGER_USE_SYSLOG = true;
	private const LOGGER_DEBUG_VERBOSE_LEVEL = true;
	private const LOGGER_PREFIX = null;
	private const LOGGER_SUFFIX = '';

	/** @inheritDoc */
	protected function performRecurringModification(): void {
		if ( RequestContext::getMain()->getUser()->pingLimiter( 'requestPauseRecurring' ) ) {
			// Allow rate limiting by setting e.g. $wgRateLimits['requestPauseRecurring']['ip']
			return;
		}
		$logger = DonationLoggerFactory::getLoggerFromParams(
			self::LOGGER_IDENTIFIER,
			self::LOGGER_USE_SYSLOG,
			self::LOGGER_DEBUG_VERBOSE_LEVEL,
			self::LOGGER_SUFFIX,
			self::LOGGER_PREFIX );

		$request = $this->getRequest();
		$logger->info( "Received recurring pause message for contact with id: " . $request->getVal( 'contact_id' ) );

		$duration = $request->getVal( 'duration' );
		$contact_id = $request->getVal( 'contact_id' );
		$checksum = $request->getVal( 'checksum' );
		$contribution_recur_id = $request->getVal( 'contribution_recur_id' );
		$next_sched_contribution_date = $request->getVal( 'next_sched_contribution_date' );
		$new_date = date_add( date_create( $next_sched_contribution_date ), date_interval_create_from_date_string( $duration ) );
		$formatDate = date_format( $new_date, 'd F, o' );

		$queueMessage = [
			'duration' => $duration,
			'contact_id' => $contact_id,
			'checksum' => $checksum,
			'contribution_recur_id' => $contribution_recur_id,
			'is_from_save_flow' => $this->getParameter( 'is_from_save_flow' ),
			'txn_type' => 'recurring_paused'
		] + $this->getTrackingParametersWithoutPrefix();

		$logger->info( "Pushing recurring pause message to queue:" . json_encode( $queueMessage ) );
		QueueWrapper::push( 'recurring-modify', $queueMessage );
		$this->getResult()->addValue( null, 'result', [
			'message' => 'Success',
			'next_sched_contribution_date' => $formatDate
		] );
		$logger->info( "Recurring pause message queued for contact with id: " . $request->getVal( 'contact_id' ) );
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return $this->getBaseParameters() + [
			'duration' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => true ],
			'next_sched_contribution_date' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => true ],
			'is_from_save_flow' => [
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => false,
			],
		];
	}
}
