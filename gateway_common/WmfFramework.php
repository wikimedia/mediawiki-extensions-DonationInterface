<?php

use MediaWiki\Session\SessionManager;
use Wikimedia\IPUtils;
use Wikimedia\Message\MessageParam;
use Wikimedia\Message\MessageSpecifier;

class WmfFramework {

	public static function getIP(): string {
		$request = RequestContext::getMain()->getRequest();
		return $request->getIP();
	}

	public static function getRequestValue( string $key, ?string $default ): ?string {
		$request = RequestContext::getMain()->getRequest();
		// all strings is just fine.
		$ret = $request->getText( $key, $default ?? '' );
		// getText never returns null: It just casts do an empty string. Soooo...
		// check with getVal, which does return null for a missing key
		if ( $ret === '' && $request->getVal( $key ) === null ) {
			$ret = $default; // not really there, so stop pretending.
		}
		return $ret;
	}

	public static function getQueryValues(): array {
		return RequestContext::getMain()->getRequest()->getQueryValues();
	}

	public static function getRequestHeader( string $key ): string {
		return RequestContext::getMain()->getRequest()->getHeader( $key );
	}

	/**
	 * @param string|string[]|MessageSpecifier $key
	 * @param MessageParam|MessageSpecifier|string|int|float|list<MessageParam|MessageSpecifier|string|int|float> ...$params
	 */
	public static function formatMessage( $key, ...$params ): string {
		return wfMessage( $key, ...$params )->text();
	}

	public static function getLanguageCode(): string {
		$lang = RequestContext::getMain()->getLanguage();
		return $lang->getCode();
	}

	public static function getLanguageFallbacks( string $language ): array {
		return \MediaWiki\MediaWikiServices::getInstance()->getLanguageFallback()->getAll( $language );
	}

	public static function setupSession( bool $sessionId = false ) {
		SessionManager::getGlobalSession()->persist();
	}

	public static function getSessionValue( string $key ): mixed {
		return RequestContext::getMain()->getRequest()->getSessionData( $key );
	}

	public static function setSessionValue( string $key, mixed $value ) {
		RequestContext::getMain()->getRequest()->setSessionData( $key, $value );
	}

	public static function getSessionId(): string {
		return SessionManager::getGlobalSession()->getId();
	}

	public static function validateIP( string $ip ): bool {
		return IPUtils::isValid( $ip );
	}

	public static function isValidBuiltInLanguageCode( string $code ): bool {
		return \MediaWiki\MediaWikiServices::getInstance()->getLanguageNameUtils()->isValidBuiltInCode( $code );
	}

	public static function validateEmail( string $email ): bool {
		return Sanitizer::validateEmail( $email );
	}

	/**
	 * wmfMessageExists returns true if a translatable message has been defined
	 * for the string and language that have been passed in, false if none is
	 * present. If no language is passed in, defaults to self::getLanguageCode()
	 * @param string $msg_key The message string to look up.
	 * @param string|null $language A valid mediawiki language code, or null.
	 * @return bool - true if message exists, otherwise false.
	 */
	public static function messageExists( $msg_key, $language = null ) {
		if ( $language === null ) {
			$language = self::getLanguageCode();
		}
		return wfMessage( $msg_key )->inLanguage( $language )->exists();
	}

	public static function getUserAgent(): string {
		return \MediaWiki\MediaWikiServices::getInstance()->getHttpRequestFactory()->getUserAgent();
	}

	public static function isPosted(): bool {
		$request = RequestContext::getMain()->getRequest();
		return $request->wasPosted();
	}

	public static function sanitize( string $text ): string {
		return wfEscapeWikiText( $text );
	}

	public static function getConfig(): Config {
		return \MediaWiki\MediaWikiServices::getInstance()->getMainConfig();
	}
}
