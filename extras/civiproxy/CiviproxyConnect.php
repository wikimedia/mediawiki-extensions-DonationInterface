<?php

class CiviproxyConnect {

	// These refer to the names (keys in an array) of available actual site and api keys
	// that CiviProxy may use when it connects to Civi.
	// Note: coordinate with config.php for Civiproxy.
	const SITE_KEY_KEY = 'SITE_KEY';
	const API_KEY_KEY = 'API_KEY';

	public static function getEmailPreferences( $checksum, $contact_id ) {
		global $wgDonationInterfaceCiviproxyURLBase;

		$client = new GuzzleHttp\Client();
		$logger = DonationLoggerFactory::getLoggerFromParams(
			'CiviproxyConnector', true, false, '', null );
		try {
			$clientResponse = $client->get(
				"$wgDonationInterfaceCiviproxyURLBase/rest.php",
				[ 'query' => [
						'entity' => 'civiproxy',
						'action' => 'getpreferences',
						'key' => self::SITE_KEY_KEY,
						'api_key' => self::API_KEY_KEY,
						'version' => '3',
						'json' => '1',
						'checksum' => $checksum,
						'contact_id' => $contact_id
					],
					'verify' => false
				]
			);

			// check if proxy is down, then throw an exception
			if ( $clientResponse->getStatusCode() !== 200 ) {
				$logger->error( 'Status Code (' . $clientResponse->getStatusCode() . "): Unable to get the civi proxy connection" );
				return [
					'is_error' => true,
					'error_message' => 'CiviProxy is down'
				];
			}
			$rawResponse = $clientResponse->getBody()->getContents();
			$decodedResponse = json_decode( $rawResponse, true );

			if ( !$decodedResponse ) {
				throw new RuntimeException( "Invalid JSON from CiviProxy for id $contact_id" );
			}

			return [
				'country' => $decodedResponse[ 'country' ] ?? null,
				'sendEmail' => $decodedResponse[ 'is_opt_in' ] ?? null,
				'email' => $decodedResponse[ 'email' ] ?? null,
				'first_name' => $decodedResponse[ 'first_name' ] ?? null,
				'preferred_language' => $decodedResponse[ 'preferred_language' ] ?? null,
				'is_error' => ( $decodedResponse[ 'is_error' ] === 1 ),
				'error_message' => $decodedResponse[ 'error_message' ] ?? null,
				'snooze_date' => $decodedResponse['snooze_date'] ?? null
			];

		} catch ( Exception $e ) {
			$logger->error( "contact id: $contact_id, " . $e->getMessage() );
			return [
				'is_error' => true,
				'error_message' => $e->getMessage()
			];
		}
	}

	public static function getRecurDetails( $checksum, $contact_id ) {
		global $wgDonationInterfaceCiviproxyURLBase;

		$client = new GuzzleHttp\Client();
		$params = [
			'checksum' => $checksum,
			'contact_id' => $contact_id
		];
		$serializedParams = json_encode( $params );
		$logger = DonationLoggerFactory::getLoggerFromParams(
			'CiviproxyConnector', true, false, '', null );
		try {
			$resp = $client->get(
				"$wgDonationInterfaceCiviproxyURLBase/rest4.php",
				[ 'query' => [
						'entity' => 'ContributionRecur',
						'action' => 'getUpgradableRecur',
						'key' => self::SITE_KEY_KEY,
						'api_key' => self::API_KEY_KEY,
						'version' => '4',
						'json' => '1',
						'params' => $serializedParams,
				],
					'verify' => false
				]
			);
			$response = $resp->getBody()->getContents();

			$decodedResponse = json_decode( $response, true );

			if ( $decodedResponse === null ) {
				return [
					'is_error' => true,
					'error_message' => "Invalid JSON from CiviProxy for id $contact_id"
				];
			}

			$firstValue = $decodedResponse['values'][0];

			if ( count( $firstValue ) === 0 ) {
				return [
					'is_error' => true,
					'error_message' => "No result found"
				];
			}

			// Transitional code, handles old and new API response
			if ( isset( $firstValue['id'] ) ) {
				// new style, fields are directly under ['values'][0]
				$contributionRecurDetails = $firstValue;
			} else {
				// old style, fields are one level deeper under ['values'][0][0]
				$contributionRecurDetails = $firstValue[0];
			}

			return [
				'id' => $contributionRecurDetails['id'] ?? null,
				'country' => $contributionRecurDetails[ 'country' ] ?? null,
				'donor_name' => $contributionRecurDetails[ 'donor_name' ],
				'currency' => $contributionRecurDetails[ 'currency' ],
				'next_sched_contribution_date' => $contributionRecurDetails[ 'next_sched_contribution_date' ],
				'amount' => $contributionRecurDetails[ 'amount' ] ?? null
			];

		} catch ( Exception $e ) {
			$logger->error( "contact id: $contact_id, " . $e->getMessage() );
			return [
				'is_error' => true,
				'error_message' => $e->getMessage()
			];
		}
	}
}
