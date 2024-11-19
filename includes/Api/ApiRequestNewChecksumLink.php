<?php

namespace MediaWiki\Extension\DonationInterface\Api;

use RequestContext;
use SmashPig\Core\DataStores\QueueWrapper;
use Wikimedia\ParamValidator\ParamValidator;

class ApiRequestNewChecksumLink extends \ApiBase {

	public function execute() {
		if ( RequestContext::getMain()->getUser()->pingLimiter( 'requestNewChecksumLink' ) ) {
			// Allow rate limiting by setting e.g. $wgRateLimits['requestNewChecksumLink']['ip']
			return;
		}
		$contactID = $this->getRequest()->getVal( 'contactID' );
		$page = $this->getRequest()->getVal( 'page' );
		$subpage = $this->getRequest()->getVal( 'subpage' );
		$this->validateAlphanumeric( $page );
		$this->validateAlphanumeric( $subpage );

		$queueMessage = [
			'contactID' => $contactID,
			'page' => $page,
		];

		if ( $subpage ) {
			$queueMessage['subpage'] = $subpage;
		}
		QueueWrapper::push( 'new-checksum-link', $queueMessage );
	}

	public function getAllowedParams() {
		return [
			'contactID' => [ ParamValidator::PARAM_TYPE => 'integer', ParamValidator::PARAM_REQUIRED => true ],
			'page' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => true ],
			'subpage' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => false ],
		];
	}

	protected function validateAlphanumeric( ?string $input ): void {
		if ( $input && !preg_match( '/^[a-zA-Z0-9_-]*$/', $input ) ) {
			throw new \InvalidArgumentException( "Bad parameter '$input' - should be alphanumeric." );
		}
	}

	public function isReadMode(): bool {
		return false;
	}
}
