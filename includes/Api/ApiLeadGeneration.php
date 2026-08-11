<?php

namespace MediaWiki\Extension\DonationInterface\Api;

use MediaWiki\Api\ApiBase;
use MediaWiki\Parser\Sanitizer;
use SmashPig\Core\DataStores\QueueWrapper;
use Wikimedia\ParamValidator\ParamValidator;

class ApiLeadGeneration extends ApiBase {

	/** @inheritDoc */
	public function isReadMode() {
		return false;
	}

	/** @inheritDoc */
	public function mustBePosted() {
		return true;
	}

	public function execute() {
		if ( $this->getUser()->pingLimiter( 'leadgen' ) ) {
			$this->dieWithError( 'Slow down, cowboy!' );
		}
		$params = $this->extractRequestParams();
		if ( !Sanitizer::validateEmail( $params['email'] ) ) {
			$this->dieWithError( $this->msg( 'apierror-donorportal-invalid-email' ) );
		}
		QueueWrapper::push( 'lead-generation', [
			'email' => $params['email'],
			'leadgen_source' => $params['leadgen_source']
		] );
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'email' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'leadgen_source' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

}
