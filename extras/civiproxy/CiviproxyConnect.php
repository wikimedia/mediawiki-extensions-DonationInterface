<?php

use MediaWiki\MediaWikiServices;

class CiviproxyConnect {

	// These refer to the names (keys in an array) of available actual site and api keys
	// that CiviProxy may use when it connects to Civi.
	// Note: coordinate with config.php for Civiproxy.
	const SITE_KEY_KEY = 'SITE_KEY';
	const API_KEY_KEY = 'API_KEY';

	public static function getEmailPreferences( string $checksum, string $contact_id ): array {
		global $wgDonationInterfaceCiviproxyURLBase;

		$logger = DonationLoggerFactory::getLoggerFromParams(
			'CiviproxyConnector', true, false, '', null );
		try {
			$req = MediaWikiServices::getInstance()->getHttpRequestFactory()->create(
				"$wgDonationInterfaceCiviproxyURLBase/rest.php?" . http_build_query( [
					'entity' => 'civiproxy',
					'action' => 'getpreferences',
					'key' => self::SITE_KEY_KEY,
					'api_key' => self::API_KEY_KEY,
					'version' => '3',
					'json' => '1',
					'checksum' => $checksum,
					'contact_id' => $contact_id
				] ), [
					'sslVerifyCert' => false,
					'sslVerifyHost' => false
				],
				__METHOD__
			);
			$status = $req->execute();

			// check if proxy is down, then throw an exception
			if ( !$status->isOK() ) {
				$logger->error( 'Status Code (' . $status->getValue() . "): Unable to get the civi proxy connection" );
				return [
					'is_error' => true,
					'error_message' => 'CiviProxy is down'
				];
			}
			$rawResponse = $req->getContent();
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

	protected static function makeApi4Request( string $checksum, string $contact_id, string $entity, string $action ): ?array {
		global $wgDonationInterfaceCiviproxyURLBase;

		$params = [
			'checksum' => $checksum,
			'contact_id' => $contact_id
		];
		$serializedParams = json_encode( $params );
		$response = MediaWikiServices::getInstance()->getHttpRequestFactory()->get(
			"$wgDonationInterfaceCiviproxyURLBase/rest4.php?" . http_build_query( [
				'entity' => $entity,
				'action' => $action,
				'key' => self::SITE_KEY_KEY,
				'api_key' => self::API_KEY_KEY,
				'version' => '4',
				'json' => '1',
				'params' => $serializedParams,
			] ), [
				'sslVerifyCert' => false,
				'sslVerifyHost' => false
			],
			__METHOD__
		);

		return json_decode( $response, true );
	}

	public static function getRecurDetails( string $checksum, string $contact_id ): array {
		$logger = DonationLoggerFactory::getLoggerFromParams(
			'CiviproxyConnector', true, false, '', null );
		try {
			$decodedResponse = self::makeApi4Request(
				$checksum, $contact_id, 'ContributionRecur', 'getUpgradableRecur'
			);

			if ( $decodedResponse === null ) {
				return [
					'is_error' => true,
					'error_message' => "Invalid JSON from CiviProxy for id $contact_id"
				];
			}

			$contributionRecurDetails = $decodedResponse['values'][0];

			if ( count( $contributionRecurDetails ) === 0 ) {
				return [
					'is_error' => true,
					'error_message' => "No result found"
				];
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

	public static function getDonorSummary( string $checksum, string $contact_id ): array {
		try {
			$decodedResponse = self::makeApi4Request(
				$checksum, $contact_id, 'WMFContact', 'getDonorSummary'
			);

			if ( $decodedResponse === null ) {
				return [
					'is_error' => true,
					'error_message' => "Invalid JSON from CiviProxy for id $contact_id"
				];
			}

			$donorSummary = $decodedResponse['values'][0];

			if ( count( $donorSummary ) === 0 ) {
				return [
					'is_error' => true,
					'error_message' => 'No result found'
				];
			}

			return $donorSummary;

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
