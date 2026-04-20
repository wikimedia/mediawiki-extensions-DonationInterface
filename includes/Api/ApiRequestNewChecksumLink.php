<?php

namespace MediaWiki\Extension\DonationInterface\Api;

use DonationLoggerFactory;
use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiUsageException;
use MediaWiki\Context\RequestContext;
use SmashPig\Core\DataStores\QueueWrapper;
use Wikimedia\ParamValidator\ParamValidator;

class ApiRequestNewChecksumLink extends ApiBase {
	private const LOGGER_IDENTIFIER = 'ApiRequestNewChecksumLink';
	private const LOGGER_USE_SYSLOG = true;
	private const LOGGER_DEBUG_VERBOSE_LEVEL = true;
	private const LOGGER_PREFIX = null;
	private const LOGGER_SUFFIX = '';
	private const ERROR_INVALID_EMAIL = 'invalid_email';

	public function execute() {
		if ( RequestContext::getMain()->getUser()->pingLimiter( 'requestNewChecksumLink' ) ) {
			// Allow rate limiting by setting e.g. $wgRateLimits['requestNewChecksumLink']['ip']
			return;
		}
		$logger = DonationLoggerFactory::getLoggerFromParams(
			self::LOGGER_IDENTIFIER,
			self::LOGGER_USE_SYSLOG,
			self::LOGGER_DEBUG_VERBOSE_LEVEL,
			self::LOGGER_SUFFIX,
			self::LOGGER_PREFIX );

		$email = trim( $this->getRequest()->getVal( 'email' ) );
		$page = $this->getRequest()->getVal( 'page' );
		$subpage = $this->getRequest()->getVal( 'subpage' );

		$this->validateEmail( $email );
		$this->validateAlphanumeric( $page );
		$this->validateAlphanumeric( $subpage );

		$maskedEmail = $this->maskEmail( $email );
		$logger->info( "Received new checksum link request for contact with email: " . $maskedEmail );

		$queueMessage = [
			'email' => $email,
			'page' => $page,
		];

		if ( $subpage ) {
			$queueMessage['subpage'] = $subpage;
		}
		$logger->info( "Pushing new checksum link message to queue for: " . $maskedEmail );
		QueueWrapper::push( 'new-checksum-link', $queueMessage );
		$logger->info( "New checksum link message queued for contact with email: " . $maskedEmail );
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

	/**
	 * Masks an email address for safe logging by showing only the first two characters
	 * of the local part and the TLD, e.g. "jo***@***.com".
	 * This helps in limiting the exposure of donor PII in the logs.
	 * @param string $email
	 * @return string
	 */
	protected function maskEmail( string $email ): string {
		[ $local, $domain ] = explode( '@', $email, 2 );
		$maskedLocal = substr( $local, 0, 2 ) . str_repeat( '*', max( 0, \strlen( $local ) - 2 ) );
		$dotPos = strrpos( $domain, '.' );
		$maskedDomain = str_repeat( '*', $dotPos ) . substr( $domain, $dotPos );
		return "{$maskedLocal}@{$maskedDomain}";
	}

	protected function validateEmail( string $email ): void {
		if ( !filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			throw ApiUsageException::newWithMessage( $this, 'apierror-donorportal-invalid-email', self::ERROR_INVALID_EMAIL );
		}
	}

	public function isReadMode(): bool {
		return false;
	}
}
