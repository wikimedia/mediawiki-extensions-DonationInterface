<?php

namespace MediaWiki\Extension\DonationInterface\Api;

use MediaWiki\Api\ApiBase;
use MediaWiki\Context\RequestContext;
use SmashPig\Core\DataStores\QueueWrapper;
use Wikimedia\ParamValidator\ParamValidator;

class ApiRequestPauseRecurring extends ApiBase {
	/** @inheritDoc */
	public function isReadMode() {
		return false;
	}

	/** @inheritDoc */
	public function mustBePosted() {
		return true;
	}

	public function execute() {
		if ( RequestContext::getMain()->getUser()->pingLimiter( 'requestPauseRecurring' ) ) {
			// Allow rate limiting by setting e.g. $wgRateLimits['requestPauseRecurring']['ip']
			return;
		}
		$request = $this->getRequest();
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
			'txn_type' => 'recurring_paused'
		];

		QueueWrapper::push( 'recurring-modify', $queueMessage );
		$this->getResult()->addValue( null, 'result', [
			'message' => 'Success',
			'next_sched_contribution_date' => $formatDate
		] );
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'duration' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => true ],
			'contact_id' => [ ParamValidator::PARAM_TYPE => 'integer', ParamValidator::PARAM_REQUIRED => true ],
			'checksum' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => false ],
			'contribution_recur_id' => [ ParamValidator::PARAM_TYPE => 'integer', ParamValidator::PARAM_REQUIRED => false ],
			'next_sched_contribution_date' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => true ]
		];
	}
}
