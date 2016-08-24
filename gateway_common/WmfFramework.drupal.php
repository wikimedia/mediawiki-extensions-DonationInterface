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
	static function debugLog( $identifier, $msg, $level = 'DEBUG' ) {
		$severity = constant( "WATCHDOG_$level" ); // Yep, they all match!
		// Janky XML sanitization so we can see the tags
		// watchdog strips so aggressively that htmlspecialchars doesn't help
		$escaped = str_replace( array( '<', '>' ), array( '{', '}' ), $msg );
		watchdog( 'DonationInterface', "{$identifier}: {$escaped}", NULL, $severity );
	}

	static function getIP() {
		return '127.0.0.1';
	}

	static function getRequestValue( $key, $default ) {
		throw new BadMethodCallException( 'Unimplemented' );
	}

	static function getRequestHeader( $key ) {
		throw new BadMethodCallException( 'Unimplemented' );
	}

	static function getHostname() {
		return 'localhost';
	}

	static function formatMessage( $message_identifier ) {
		// TODO: Use the i18n logic in wmf_communication
		return $message_identifier;
	}

	/**
	 * Do not guess.
	 */
	static function getLanguageCode() {
		return null;
	}

	static function getLanguageFallbacks( $language ) {
		$fallbacks = array();
		if ( $language ) {
			$fallbacks[] = $language;
		}
		$fallbacks[] = 'en';
		return $fallbacks;
	}

	static function isUseSquid() {
		return false;
	}

	static function setupSession( $sessionId=false ) {
		if ( session_id() ) {
			return;
		}
		if ( $sessionId ) {
			session_id( $sessionId );
		}
		session_start();
	}

	static function getSessionValue( $key ) {
		throw new BadMethodCallException( 'Unimplemented' );
	}

	static function setSessionValue( $key, $value ) {
		throw new BadMethodCallException( 'Unimplemented' );
	}

	static function validateIP( $ip ) {
		return true;
	}

	static function isValidBuiltInLanguageCode( $code ) {
		return true;
		//Language::isValidBuiltInCode
	}

	static function validateEmail( $email ) {
		$isEmail = filter_var( $email, FILTER_VALIDATE_EMAIL );
		$isEmail = $isEmail && !DataValidator::cc_number_exists_in_str( $email );
		return $isEmail;
	}

	/**
	 * Returns true if a translatable message has been defined for the string
	 * that has been passed in, false if none is present.
	 * @param string $msg_key The message string to look up.
	 * @param string $language unused
	 * @return boolean - true if message exists, otherwise false.
	 */
	public static function messageExists( $msg_key, $language = null ) {
		return strlen( self::formatMessage( $msg_key ) ) > 0;
	}

	static function getUserAgent() {
		return "WMF DonationInterface";
	}

	static function isPosted() {
		return false;
	}

	static function sanitize( $text ) {
		return filter_xss( $text );
	}
}
