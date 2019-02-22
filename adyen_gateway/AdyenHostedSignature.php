<?php

class AdyenHostedSignature implements StagingHelper {
	/**
	 * Sign the Adyen API request
	 * @inheritDoc
	 */
	public function stage( GatewayType $adapter, $normalized, &$stagedData ) {
		$params = $adapter->buildRequestParams();
		if ( $params ) {
			$stagedData['hpp_signature'] = self::calculateSignature(
				$adapter, $params
			);
		}
	}

	/**
	 * Calculate a base 64 encoded SHA256 HMAC according to the rules at
	 * https://docs.adyen.com/developers/hpp-manual#hmacpaymentsetupsha256
	 *
	 * @param GatewayType $adapter
	 * @param array $values
	 * @return string
	 */
	public static function calculateSignature( GatewayType $adapter, $values ) {
		$ignoredKeys = [
			'sig',
			'merchantSig',
			'title',
			'liberated',
			'debug',
		];

		foreach ( array_keys( $values ) as $key ) {
			if (
				substr( $key, 0, 7 ) === 'ignore.' ||
				in_array( $key, $ignoredKeys )
			) {
				unset( $values[$key] );
			} else {
				// escape colons and backslashes
				$values[$key] = str_replace( '\\', '\\\\', $values[$key] );
				$values[$key] = str_replace( ':', '\\:', $values[$key] );
			}
		}

		ksort( $values, SORT_STRING );
		$merged = array_merge( array_keys( $values ), array_values( $values ) );
		$joined = implode( ':', $merged );
		$skinCode = $values['skinCode'];
		if ( array_key_exists( $skinCode, $adapter->getAccountConfig( 'Skins' ) ) ) {
			$secret = $adapter->getAccountConfig( 'Skins' )[$skinCode]['SharedSecret'];
			return base64_encode(
				hash_hmac( 'sha256', $joined, pack( "H*", $secret ), true )
			);
		} else {
			throw new RuntimeException( "Skin code $skinCode not configured" );
		}
	}
}
