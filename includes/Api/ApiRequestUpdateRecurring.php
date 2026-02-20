<?php

namespace MediaWiki\Extension\DonationInterface\Api;

use MediaWiki\Context\RequestContext;
use SmashPig\Core\DataStores\QueueWrapper;
use Wikimedia\ParamValidator\ParamValidator;

class ApiRequestUpdateRecurring extends ApiRecurringModifyBase {

	/** @inheritDoc */
	protected function performRecurringModification(): void {
		if ( RequestContext::getMain()->getUser()->pingLimiter( 'requestUpdateRecurring' ) ) {
			return;
		}
		$request = $this->getRequest();
		$amount = $request->getVal( 'amount' );
		$txn_type = $request->getVal( 'txn_type' );
		$contact_id = $request->getVal( 'contact_id' );
		$checksum = $request->getVal( 'checksum' );
		$contribution_recur_id = $request->getVal( 'contribution_recur_id' );

		$queueMessage = [
			'amount' => $amount,
			'contact_id' => $contact_id,
			'checksum' => $checksum,
			'contribution_recur_id' => $contribution_recur_id,
			'is_from_save_flow' => $this->getParameter( 'is_from_save_flow' ),
			'txn_type' => $txn_type,
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
			'txn_type' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => true ],
			'is_from_save_flow' => [
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => false,
			],
		];
	}
}
