<?php

/**
 * Incomplete shim library for processing payments from Drupal
 *
 * Please note that some functionality is still unsupported, because it
 * relies on unported MediaWiki dependencies, or stubs here such as
 * getLanguageCode.  The only code path in active use is to make recurring
 * charges through GlobalCollect.
 */
class WmfFramework_Drupal {
	public static function debugLog( $identifier, $msg, $level = 'DEBUG' ) {
		$severity = constant( "WATCHDOG_$level" ); // Yep, they all match!
		// Janky XML sanitization so we can see the tags
		// watchdog strips so aggressively that htmlspecialchars doesn't help
		$escaped = str_replace( array( '<', '>' ), array( '{', '}' ), $msg );
		watchdog( 'DonationInterface', "{$identifier}: {$escaped}", null, $severity );
	}

	public static function getIP() {
		return '127.0.0.1';
	}

	public static function getRequestValue( $key, $default ) {
		throw new BadMethodCallException( 'Unimplemented' );
	}

	public static function getQueryValues() {
		throw new BadMethodCallException( 'Unimplemented' );
	}

	public static function getRequestHeader( $key ) {
		throw new BadMethodCallException( 'Unimplemented' );
	}

	public static function formatMessage( $message_identifier ) {
		// TODO: Use the i18n logic in wmf_communication
		return $message_identifier;
	}

	/**
	 * Do not guess.
	 * @return string|null
	 */
	public static function getLanguageCode() {
		return null;
	}

	public static function getLanguageFallbacks( $language ) {
		$fallbacks = array();
		if ( $language ) {
			$fallbacks[] = $language;
		}
		$fallbacks[] = 'en';
		return $fallbacks;
	}

	public static function isUseSquid() {
		return false;
	}

	public static function setupSession( $sessionId=false ) {
		if ( session_id() ) {
			return;
		}
		if ( $sessionId ) {
			session_id( $sessionId );
		}
		session_start();
	}

	public static function getSessionValue( $key ) {
		throw new BadMethodCallException( 'Unimplemented' );
	}

	public static function setSessionValue( $key, $value ) {
		throw new BadMethodCallException( 'Unimplemented' );
	}

	public static function getSessionId() {
		throw new BadMethodCallException( 'Unimplemented' );
	}

	public static function validateIP( $ip ) {
		return true;
	}

	public static function isValidBuiltInLanguageCode( $code ) {
		return true;
		// Language::isValidBuiltInCode
	}

	public static function validateEmail( $email ) {
		$isEmail = filter_var( $email, FILTER_VALIDATE_EMAIL );
		$isEmail = $isEmail && !DataValidator::cc_number_exists_in_str( $email );
		return $isEmail;
	}

	/**
	 * Returns true if a translatable message has been defined for the string
	 * that has been passed in, false if none is present.
	 * @param string $msg_key The message string to look up.
	 * @param string|null $language unused
	 * @return bool - true if message exists, otherwise false.
	 */
	public static function messageExists( $msg_key, $language = null ) {
		return strlen( self::formatMessage( $msg_key ) ) > 0;
	}

	public static function getUserAgent() {
		return "WMF DonationInterface";
	}

	public static function isPosted() {
		return false;
	}

	public static function sanitize( $text ) {
		return filter_xss( $text );
	}
}
