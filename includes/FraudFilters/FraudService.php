<?php

namespace MediaWiki\Extension\DonationInterface\FraudFilters;

use MediaWiki\Config\Config;
use MediaWiki\Http\HttpRequestFactory;
use MWHttpRequest;

class FraudService {

	public const OUTCOME_AUTH_DECLINE = 1;
	public const OUTCOME_PROCESSOR_FLAGGED_FRAUD = 2;
	public const OUTCOME_BLOCKED_BY_FILTER = 4;

	protected string $serviceBaseURL;

	public function __construct( Config $config, protected HttpRequestFactory $requestFactory ) {
		$this->serviceBaseURL = $config->get( 'DonationInterfaceFraudServiceURL' );
	}

	public function getScores( array $data ): array {
		if ( !$this->serviceBaseURL ) {
			return [ 'success' => true ];
		}
		$url = $this->serviceBaseURL . '/v1/score';
		try {
			$request = $this->createRequest( $url, $data );

			$status = $request->execute();
			$rawResponse = $request->getContent();
			if ( $status->isOK() && $rawResponse ) {
				$decoded = json_decode( $rawResponse, true );
				if ( isset( $decoded['predictions'] ) ) {
					$response = [
						'success' => true,
						'scores' => $decoded['predictions'],
					];
				} else {
					$response = [
						'success' => false,
						'error' => 'Missing "predictions" key in raw response: ' . $rawResponse,
					];
				}
				return $response;
			}
			return [ 'success' => true, 'error' => $rawResponse ];
		} catch ( \Throwable $e ) {
			return [ 'success' => true, 'error' => $e->getMessage() ];
		}
	}

	public function markOutcome( string $orderID, int $flags = 0 ): array {
		if ( !$this->serviceBaseURL ) {
			return [ 'success' => true ];
		}
		$payload = [
			'order_id' => $orderID,
		];
		if ( $flags & self::OUTCOME_AUTH_DECLINE ) {
			$payload['auth_decline'] = 1;
		}
		if ( $flags & self::OUTCOME_PROCESSOR_FLAGGED_FRAUD ) {
			$payload['fraud_flagged_by_processor'] = 1;
		}
		if ( $flags & self::OUTCOME_BLOCKED_BY_FILTER ) {
			$payload['blocked_by_filter'] = 1;
		}
		$url = $this->serviceBaseURL . '/v1/outcome';
		try {
			$request = $this->createRequest( $url, $payload );

			$status = $request->execute();
			$rawResponse = $request->getContent();
			if ( $status->isOK() ) {
				return [ 'success' => true ];
			}
			return [ 'success' => false, 'error' => $rawResponse ];
		} catch ( \Throwable $e ) {
			return [ 'success' => false, 'error' => $e->getMessage() ];
		}
	}

	private function getHeaders(): array {
		return [
			'Content-Type' => 'application/json'
		];
	}

	protected function createRequest( string $url, array $data ): MWHttpRequest {
		$payload = json_encode( $data );
		$request = $this->requestFactory->create(
			$url, [ 'method' => 'POST', 'postData' => $payload ], __METHOD__
		);
		$request->setHeader( 'Content-Type', 'application/json' );
		return $request;
	}
}
