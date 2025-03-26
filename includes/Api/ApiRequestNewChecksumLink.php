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
		$email = $this->getRequest()->getVal( 'email' );
		$page = $this->getRequest()->getVal( 'page' );
		$subpage = $this->getRequest()->getVal( 'subpage' );

		$this->validateEmail( $email );
		$this->validateAlphanumeric( $page );
		$this->validateAlphanumeric( $subpage );

		$queueMessage = [
			'email' => $email,
			'page' => $page,
		];

		if ( $subpage ) {
			$queueMessage['subpage'] = $subpage;
		}
		QueueWrapper::push( 'new-checksum-link', $queueMessage );
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'email' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => true ],
			'page' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => true ],
			'subpage' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => false ],
		];
	}

	protected function validateAlphanumeric( ?string $input ): void {
		if ( $input && !preg_match( '/^[a-zA-Z0-9_-]*$/', $input ) ) {
			throw new \InvalidArgumentException( "Bad parameter '$input' - should be alphanumeric." );
		}
	}

	protected function validateEmail( string $email ): void {
		if ( !filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			throw new \InvalidArgumentException( "Bad parameter '$email' - should be an email address." );
		}
	}

	public function isReadMode(): bool {
		return false;
	}
}
