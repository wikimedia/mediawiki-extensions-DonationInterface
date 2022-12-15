<?php

use MediaWiki\Session\SessionManager;
use Wikimedia\IPUtils;

class WmfFramework_Mediawiki {
	public static function debugLog( $identifier, $msg, $level = 'DEBUG' ) {
		// TODO: call different wf*Log functions depending on $level
		wfDebugLog( $identifier, $msg );
	}

	public static function getIP() {
		$request = RequestContext::getMain()->getRequest();
		return $request->getIP();
	}

	public static function getRequestValue( $key, $default ) {
		$request = RequestContext::getMain()->getRequest();
		// all strings is just fine.
		$ret = $request->getText( $key, $default );
		// getText never returns null: It just casts do an empty string. Soooo...
		// check with getVal, which does return null for a missing key
		if ( $ret === '' && $request->getVal( $key ) === null ) {
			$ret = $default; // not really there, so stop pretending.
		}
		return $ret;
	}

	public static function getQueryValues() {
		return RequestContext::getMain()->getRequest()->getQueryValues();
	}

	public static function getRequestHeader( $key ) {
		return RequestContext::getMain()->getRequest()->getHeader( $key );
	}

	public static function formatMessage( $key, ...$params ) {
		return wfMessage( $key, ...$params )->text();
	}

	public static function getLanguageCode() {
		$lang = RequestContext::getMain()->getLanguage();
		return $lang->getCode();
	}

	public static function getLanguageFallbacks( $language ) {
		return Language::getFallbacksFor( $language );
	}

	public static function setupSession( $sessionId = false ) {
		SessionManager::getGlobalSession()->persist();
	}

	public static function getSessionValue( $key ) {
		return RequestContext::getMain()->getRequest()->getSessionData( $key );
	}

	public static function setSessionValue( $key, $value ) {
		RequestContext::getMain()->getRequest()->setSessionData( $key, $value );
	}

	public static function getSessionId() {
		return SessionManager::getGlobalSession()->getId();
	}

	public static function validateIP( $ip ) {
		return IPUtils::isValid( $ip );
	}

	public static function isValidBuiltInLanguageCode( $code ) {
		return Language::isValidBuiltInCode( $code );
	}

	public static function validateEmail( $email ) {
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
		return Language::getMessageFor( $msg_key, $language ) !== null;
	}

	public static function getUserAgent() {
		return \MediaWiki\MediaWikiServices::getInstance()->getHttpRequestFactory()->getUserAgent();
	}

	public static function isPosted() {
		$request = RequestContext::getMain()->getRequest();
		return $request->wasPosted();
	}

	public static function sanitize( $text ) {
		return wfEscapeWikiText( $text );
	}

	public static function getConfig() {
		return \MediaWiki\MediaWikiServices::getInstance()->getMainConfig();
	}
}
