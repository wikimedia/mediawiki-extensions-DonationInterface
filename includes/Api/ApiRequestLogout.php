<?php

namespace MediaWiki\Extension\DonationInterface\Api;

use CiviproxyConnect;
use MediaWiki\Api\ApiBase;
use MediaWiki\Context\RequestContext;
use Wikimedia\ParamValidator\ParamValidator;

class ApiRequestLogout extends ApiBase {

	/** @inheritDoc */
	public function isReadMode() {
		return false;
	}

	/** @inheritDoc */
	public function mustBePosted() {
		return true;
	}

	public function execute() {
		if ( RequestContext::getMain()->getUser()->pingLimiter( 'requestLogout' ) ) {
			return;
		}

		$contact_id = $this->getRequest()->getVal( 'contact_id' );
		$checksum = $this->getRequest()->getVal( 'checksum' );
		$result = CiviproxyConnect::invalidateChecksum( $checksum, $contact_id );
		$this->getResult()->addValue( null, 'result', $result );
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'contact_id' => [
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'checksum' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

}
