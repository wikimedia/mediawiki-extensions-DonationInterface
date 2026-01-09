<?php

namespace MediaWiki\Extension\DonationInterface\Api;

use MediaWiki\Api\ApiBase;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\DonationInterface\DonorPortal\ActivityTrackingTrait;
use SmashPig\Core\DataStores\QueueWrapper;
use Wikimedia\ParamValidator\ParamValidator;

class ApiRequestAnnualConversion extends ApiBase {

	use ActivityTrackingTrait;

	/** @inheritDoc */
	public function isReadMode() {
		return false;
	}

	/** @inheritDoc */
	public function mustBePosted() {
		return true;
	}

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
		return [
			'amount' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => true ],
			'next_sched_contribution_date' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => true ],
			'contact_id' => [ ParamValidator::PARAM_TYPE => 'integer', ParamValidator::PARAM_REQUIRED => true ],
			'checksum' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => false ],
			'contribution_recur_id' => [ ParamValidator::PARAM_TYPE => 'integer', ParamValidator::PARAM_REQUIRED => false ],
			'is_from_save_flow' => [
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => false,
			],
			'wmf_campaign' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => false ],
			'wmf_medium' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => false ],
			'wmf_source' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => false ],
		];
	}
}
