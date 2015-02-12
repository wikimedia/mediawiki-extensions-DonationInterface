<?php

class WmfFramework_Drupal {
	static function debugLog( $identifier, $msg ) {
		watchdog( 'DonationInterface', "{$identifier}: {$msg}", NULL, WATCHDOG_DEBUG );
	}

	static function getIP() {
		return '127.0.0.1';
	}

	static function getHostname() {
		return 'localhost';
	}

	static function formatMessage( $message_identifier ) {
		return "NO MSG FOUND for ".$message_identifier;
	}

	static function runHooks( $func, $args ) {
		return true;
	}

	static function getLanguageCode() {
		throw new Exception( "Not implemented" );
	}

	static function isUseSquid() {
		return false;
	}

	static function setupSession( $sessionId=false ) {
		if ( $sessionId ) {
			session_id( $session_id );
		}
		session_start();
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
	 * wmfMessageExists returns true if a translatable message has been defined
	 * for the string and language that have been passed in, false if none is
	 * present.
	 * @param string $msg_key The message string to look up.
	 * @param string $language A valid mediawiki language code.
	 * @return boolean - true if message exists, otherwise false.
	 */
	public static function messageExists( $msg_key, $language ) {
		return strlen( self::formatMessage( $msg_key ) ) > 0;
	}

	static function getUserAgent() {
		return "WMF DonationInterface";
	}

	static function isPosted() {
		return false;
	}
}
