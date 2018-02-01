<?php

/**
 * Convenience methods for translating and localizing interface messages
 */
class MessageUtils {

	/**
	 * languageSpecificFallback - returns the text of the first existing message
	 * in the requested language. If no messages are found in that language, the
	 * function returns the first existing fallback message.
	 *
	 * @param string $language the code of the requested language
	 * @param array $msg_keys
	 * @param array $params extra message parameters
	 * @throws InvalidArgumentException
	 * @return String the text of the first existing message
	 */
	public static function languageSpecificFallback(
		$language = 'en', $msg_keys = array(), $params = array()
	) {
		if ( count( $msg_keys ) < 1 ) {
			throw new InvalidArgumentException( __FUNCTION__ . " BAD PROGRAMMER. No message keys given." );
		}

		# look for the first message that exists
		foreach ( $msg_keys as $m ) {
			if ( WmfFramework::messageExists( $m, $language ) ) {
				return WmfFramework::formatMessage( $m, $params );
			}
		}

		# we found nothing in the requested language, return the first fallback message that exists
		foreach ( $msg_keys as $m ) {
			if ( WmfFramework::messageExists( $m, $language ) ) {
				return WmfFramework::formatMessage( $m, $params );
			}
		}

		# somehow we still don't have a message, return a default error message
		return WmfFramework::formatMessage( $msg_keys[0], $params );
	}

	/**
	 * Retrieves and translates a country-specific message, or the default if
	 * no country-specific version exists.
	 * @param string $key
	 * @param string $country
	 * @param string $language
	 * @param array $params extra message parameters
	 * @return string Localized message
	 */
	public static function getCountrySpecificMessage( $key, $country, $language, $params = array() ) {
		return self::languageSpecificFallback(
			$language, array( $key . '-' . strtolower( $country ), $key ), $params
		);
	}

	/**
	 * This function limits the possible characters passed as template keys and
	 * values to letters, numbers, hyphens and underscores. The function also
	 * performs standard escaping of the passed values.
	 *
	 * @param string $string The unsafe string to escape and check for invalid characters
	 * @return string Sanitized version of input
	 */
	public static function makeSafe( $string ) {
		$stripped = preg_replace( '/[^-_\w]/', '', $string );

		// theoretically this is overkill, but better safe than sorry
		return WmfFramework::sanitize( htmlspecialchars( $stripped ) );
	}
}
