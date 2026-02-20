<?php

namespace MediaWiki\Extension\DonationInterface\Api;

use MediaWiki\Context\RequestContext;
use SmashPig\Core\DataStores\QueueWrapper;
use Wikimedia\ParamValidator\ParamValidator;

class ApiRequestAnnualConversion extends ApiRecurringModifyBase {
	public function execute() {
		if ( RequestContext::getMain()->getUser()->pingLimiter( 'requestAnnualConversion' ) ) {
			// Allow rate limiting by setting e.g. $wgRateLimits['requestAnnualConversion']['ip']
			return;
		}
		$request = $this->getRequest();
		$amount = $request->getVal( 'amount' );
		$next_sched_contribution_date = $request->getVal( 'next_sched_contribution_date' );
		$contact_id = $request->getVal( 'contact_id' );
		$checksum = $request->getVal( 'checksum' );
		$contribution_recur_id = $request->getVal( 'contribution_recur_id' );

		$queueMessage = [
			'amount' => $amount,
			'next_sched_contribution_date' => $next_sched_contribution_date,
			'contact_id' => $contact_id,
			'checksum' => $checksum,
			'contribution_recur_id' => $contribution_recur_id,
			'is_from_save_flow' => $this->getParameter( 'is_from_save_flow' ),
			'txn_type' => 'recurring_annual_conversion'
		] + $this->getTrackingParametersWithoutPrefix();

		QueueWrapper::push( 'recurring-modify', $queueMessage );
		$this->getResult()->addValue( null, 'result', [
			'message' => 'Success',
		] );
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return $this->getBaseParameters() + [
			'amount' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => true ],
			'next_sched_contribution_date' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => true ],
			'is_from_save_flow' => [
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => false,
			],
		];
	}
}
