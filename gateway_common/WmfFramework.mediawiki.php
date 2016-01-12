<?php

class WmfFramework_Mediawiki {
	static function debugLog( $identifier, $msg, $level = 'DEBUG' ) {
		// TODO: call different wf*Log functions depending on $level
		wfDebugLog( $identifier, $msg );
	}

	static function getIP() {
		$request = RequestContext::getMain()->getRequest();
		return $request->getIP();
	}

	static function getHostname() {
		return wfHostname();
	}

	static function formatMessage( $message_identifier /*, ... */ ) {
		return call_user_func_array( 'wfMessage', func_get_args() )->text();
	}

	static function runHooks( $func, $args ) {
		return Hooks::run( $func, $args );
	}

	static function getLanguageCode() {
		global $wgLang;
		return $wgLang->getCode();
	}

	static function isUseSquid() {
		global $wgUseSquid;
		return $wgUseSquid;
	}

	static function setupSession( $sessionId = false ) {
		wfSetupSession();
	}

	static function validateIP( $ip ) {
		return IP::isValid( $ip );
	}

	static function isValidBuiltInLanguageCode( $code ) {
		return Language::isValidBuiltInCode( $code );
	}

	static function validateEmail( $email ) {
		return Sanitizer::validateEmail( $email );
	}

	/**
	 * wmfMessageExists returns true if a translatable message has been defined
	 * for the string and language that have been passed in, false if none is
	 * present.
	 * @param string $msg_key The message string to look up.
	 * @param string $language A valid mediawiki language code.
	 * @return boolean - true if message exists, otherwise false.
	 */
	public static function messageExists( $msg_key, $language ) {
		return wfMessage( $msg_key )->inLanguage( $language )->exists();
	}

	static function getUserAgent() {
		return Http::userAgent();
	}

	static function isPosted() {
		$request = RequestContext::getMain()->getRequest();
		return $request->wasPosted();
	}

	static function sanitize( $text ) {
		return wfEscapeWikiText( $text );
	}
}
