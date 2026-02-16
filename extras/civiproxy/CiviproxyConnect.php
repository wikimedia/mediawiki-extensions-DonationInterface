<?php

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

class CiviproxyConnect {

	// These refer to the names (keys in an array) of available actual site and api keys
	// that CiviProxy may use when it connects to Civi.
	// Note: coordinate with config.php for Civiproxy.
	const SITE_KEY_KEY = 'SITE_KEY';
	const API_KEY_KEY = 'API_KEY';

	public static function getEmailPreferences( string $checksum, string $contact_id ): array {
		$logger = DonationLoggerFactory::getLoggerFromParams(
			'CiviproxyConnector', true, false, '', null );
		try {
			$decodedResponse = self::makeApi4Request(
				$checksum, $contact_id, 'WMFContact', 'getCommunicationsPreferences',
			);

			if ( isset( $decodedResponse['error_code'] ) ) {
				// mwGetRequest sends a 500 error code when CiviCRM is unreachable and 0 error code when Civi Proxy is.
				return [
					'is_error' => true,
					'error_code' => $decodedResponse['error_code'],
					'error_message' => $decodedResponse['error_message'],
				];
			}

			$preferences = $decodedResponse['values'][0];

			return [
				'country' => $preferences['country'] ?? null,
				'sendEmail' => $preferences['is_opt_in'] ?? null,
				'email' => $preferences['email'] ?? null,
				'has_paypal' => $preferences['has_paypal'] ?? null,
				'first_name' => $preferences['first_name'] ?? null,
				'preferred_language' => $preferences['preferred_language'] ?? null,
				'is_error' => ( ( $preferences['is_error'] ?? 0 ) === 1 ),
				'error_message' => $preferences['error_message'] ?? null,
				'snooze_date' => $preferences['snooze_date']
			];

		} catch ( Exception $e ) {
			$logger->error( "contact id: $contact_id, " . $e->getMessage() );
			return [
				'is_error' => true,
				'error_message' => $e->getMessage()
			];
		}
	}

	/**
	 * Perform an HTTP request
	 * Rework of includes/http/HttpRequestFactory.php::request to ensure string response is returned even in error
	 *
	 * @param mixed $method
	 * @param mixed $url
	 * @param array $options
	 * @param mixed $caller
	 * @return string
	 */
	protected static function mwGetRequest( $method, $url, array $options = [], $caller = __METHOD__ ): string {
		$requestFactory = MediaWikiServices::getInstance()->getHttpRequestFactory();
		$logger = LoggerFactory::getInstance( 'http' );
		$logger->debug( "$method: $url" );

		$options['method'] = strtoupper( $method );

		$req = $requestFactory->create( $url, $options, $caller );
		$status = $req->execute();

		if ( $status->isOK() ) {
			return $req->getContent();
		} else {
			$errors = array_map( static fn ( $msg ) => $msg->getKey(), $status->getMessages( 'error' ) );
			$logger->warning( Status::wrap( $status )->getWikiText( false, false, 'en' ),
				[ 'error' => $errors, 'caller' => $caller, 'content' => $req->getContent() ] );
			$errorCode = "Failed";
			$errorMessage = "Failed to fetch";
			switch ( $status->getStatusValue()->getValue() ) {
				case 500:
					$errorCode = "Unreachable";
					$errorMessage = "CiviCRM is currently unreachable";
					break;
				case 0:
					$errorCode = "ServerError";
					$errorMessage = "CiviProxy is currently unreachable";
					break;
				default:
					// Default code and message already set
					break;
			}
			return json_encode( [
				'error' => true,
				'error_code' => $errorCode,
				'error_message' => $errorMessage
			] );
		}
	}

	/**
	 * Helper function to prepare the data for a CiviProxy API4 request
	 * @param string $checksum
	 * @param string $contact_id
	 * @param string $entity
	 * @param string $action
	 * @param array $additional_parameters
	 * @return array
	 */
	protected static function makeApi4Request( string $checksum, string $contact_id, string $entity, string $action, array $additional_parameters = [] ): array {
		global $wgDonationInterfaceCiviproxyURLBase;

		$params = array_merge( [
			'checksum' => $checksum,
			'contact_id' => $contact_id
		], $additional_parameters );

		$serializedParams = json_encode( $params );
		$response = self::mwGetRequest(
			'GET',
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
		$decodedResponse = $response ? json_decode( $response, true ) : null;
		if ( $decodedResponse === null ) {
			$logger = DonationLoggerFactory::getLoggerFromParams(
			'CiviproxyConnector', true, false, '', null );
			$logger->error( "CiviProxy request returned an invalid response for contact id: $contact_id, response:" . $response );
			return [
				'is_error' => true,
				'error_code' => 'InvalidResponse',
				'error_message' => "Invalid JSON from CiviProxy for id $contact_id"
			];
		}
		return $decodedResponse;
	}

	public static function getRecurDetails( string $checksum, string $contact_id ): array {
		$logger = DonationLoggerFactory::getLoggerFromParams(
			'CiviproxyConnector', true, false, '', null );
		try {
			$decodedResponse = self::makeApi4Request(
				$checksum, $contact_id, 'ContributionRecur', 'getUpgradableRecur'
			);

			if ( isset( $decodedResponse['error_code'] ) ) {
				// mwGetRequest sends a 500 error code when CiviCRM is unreachable and 0 error code when Civi Proxy is.
				return [
					'is_error' => true,
					'error_code' => $decodedResponse['error_code'],
					'error_message' => $decodedResponse['error_message']
				];
			}

			$contributionRecurDetails = $decodedResponse['values'][0];

			if ( count( $contributionRecurDetails ) === 0 ) {
				return [
					'is_error' => true,
					'error_message' => "No result found"
				];
			}

			return $contributionRecurDetails;

		} catch ( Exception $e ) {
			$logger->error( "contact id: $contact_id, " . $e->getMessage() );
			return [
				'is_error' => true,
				'error_message' => $e->getMessage()
			];
		}
	}

	public static function pingCivi(): array {
		try {
			$decodedResponse = self::makeApi4Request( '', '', 'System', 'getCiviCRMStatus' );

			if ( isset( $decodedResponse['error_code'] ) ) {
				// mwGetRequest sends a 500 error code when CiviCRM is unreachable and 0 error code when Civi Proxy is.
				return [
					'is_error' => true,
					'error_code' => $decodedResponse['error_code'],
					'error_message' => $decodedResponse['error_message'],
				];
			}

			$result = $decodedResponse['values'][0] ?? null;

			if ( $result === null || isset( $result['error'] ) ) {
				return [
					'is_error' => true,
					'error_code' => $result['error_code'] ?? "Unreachable",
					'error_message' => $result['message'] ?? "CiviCRM is currently unreachable"
				];
			}

			return $result;

		} catch ( Exception $e ) {
			$logger = DonationLoggerFactory::getLoggerFromParams(
				'CiviproxyConnector', true, false, '', null );
			$logger->error( "Unable to reach Civi at this time" );
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

			if ( isset( $decodedResponse['error_code'] ) ) {
				// mwGetRequest sends a 500 error code when CiviCRM is unreachable and 0 error code when Civi Proxy is.
				return [
					'is_error' => true,
					'error_code' => $decodedResponse['error_code'],
					'error_message' => $decodedResponse['error_message'],
				];
			}

			$donorResult = $decodedResponse['values'][0];

			if ( isset( $donorResult['error'] ) ) {
				return [
					'is_error' => true,
					'error_code' => $donorResult['error_code'] ?? null,
					'error_message' => $donorResult['message'] ?? ""
				];
			}

			$donorSummary = $donorResult;

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

	public static function sendDoubleOptIn(
		string $checksum, string $contact_id, string $email, array $tracking = []
	): ?array {
		global $wgDonationInterfaceCiviproxyURLBase;

		$params = array_merge( $tracking, [
			'checksum' => $checksum,
			'contact_id' => $contact_id,
			'email' => $email,
		] );
		$serializedParams = json_encode( $params );
		$response = MediaWikiServices::getInstance()->getHttpRequestFactory()->post(
			"$wgDonationInterfaceCiviproxyURLBase/rest4.php?" . http_build_query( [
				'entity' => 'WMFContact',
				'action' => 'doubleOptIn',
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
		return $response ? json_decode( $response, true ) : [ 'is_error' => true ];
	}

	public static function invalidateChecksum( string $checksum, string $contact_id ): array {
		global $wgDonationInterfaceCiviproxyURLBase;

		try {
			$params = [
				'checksum' => $checksum,
				'contactId' => $contact_id,
			];
			$serializedParams = json_encode( $params );
			$response = MediaWikiServices::getInstance()->getHttpRequestFactory()->post(
				"$wgDonationInterfaceCiviproxyURLBase/rest4.php?" . http_build_query( [
					'entity' => 'Contact',
					'action' => 'InvalidateChecksum',
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
			$decodedResponse = $response ? json_decode( $response, true ) : null;
			if ( $decodedResponse === null ) {
				return [
					'is_error' => true,
					'error_code' => null,
					'error_message' => "Invalid JSON from CiviProxy for id $contact_id"
				];
			}

			if ( isset( $decodedResponse['error_code'] ) ) {
				// mwGetRequest sends a 500 error code when CiviCRM is unreachable and 0 error code when Civi Proxy is.
				return [
					'is_error' => true,
					'error_code' => $decodedResponse['error_code'],
					'error_message' => $decodedResponse['error_message'],
				];
			}

			return $decodedResponse;
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
