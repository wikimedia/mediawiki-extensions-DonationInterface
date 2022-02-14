<?php

class CiviproxyConnect {

	// These refer to the names (keys in an array) of available actual site and api keys
	// that CiviProxy may use when it connects to Civi.
	// Note: coordinate with config.php for Civiproxy.
	const SITE_KEY_KEY = 'SITE_KEY';
	const API_KEY_KEY = 'API_KEY';

	public static function getEmailPreferences( $contact_hash, $contact_id ) {
		global $wgDonationInterfaceCiviproxyURLBase;

		$client = new GuzzleHttp\Client();

		try {
			$resp = $client->get(
				"$wgDonationInterfaceCiviproxyURLBase/rest.php",
				[ 'query' => [
						'entity' => 'civiproxy',
						'action' => 'getpreferences',
						'key' => self::SITE_KEY_KEY,
						'api_key' => self::API_KEY_KEY,
						'version' => '3',
						'json' => '1',
						'hash' => $contact_hash,
						'contact_id' => $contact_id
					],
					'verify' => false
				]
			);

			$rawResp = $resp->getBody()->getContents();
			$resp = json_decode( $rawResp, true );

			if ( !$resp ) {
				throw new RuntimeException( "Invalid JSON from CiviProxy for id $contact_id" );
			}

			$fullLang = strtolower( $resp[ 'preferred_language' ] ?? null );
			if ( $fullLang ) {
				$fullLang = str_replace( '_', '-', $fullLang );
				$shortLang = substr( $fullLang, 0, 2 );
			} else {
				$shortLang = null;
			}

			return [
				'country' => $resp[ 'country' ] ?? null,
				'sendEmail' => $resp[ 'is_opt_in' ] ?? null,
				'fullLang' => $fullLang,
				'shortLang' => $shortLang,
				'email' => $resp[ 'email' ],
				'first_name' => $resp[ 'first_name' ],
				'is_error' => ( $resp[ 'is_error' ] === 1 ),
				'error_message' => $resp[ 'error_message' ] ?? null
			];

		} catch ( Exception $e ) {
			$logger = DonationLoggerFactory::getLoggerFromParams(
				'CiviproxyConnector', true, false, '', null );

			$logger->error( "contact id: $contact_id, " . $e->getMessage() );
			return [
				'is_error' => true,
				'error_message' => $e->getMessage()
			];
		}
	}
}
