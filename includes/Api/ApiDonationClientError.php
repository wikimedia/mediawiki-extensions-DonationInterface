<?php

namespace MediaWiki\Extension\DonationInterface\Api;

use DonationInterface;
use DonationLoggerFactory;
use Psr\Log\LoggerInterface;

class ApiDonationClientError extends ApiClientErrorBase {

	protected function validateSessionData( ?array $sessionData ): bool {
		return parent::validateSessionData( $sessionData ) && !empty( $sessionData['gateway'] );
	}

	protected function getLogger( array $sessionData ): LoggerInterface {
		$logPrefix = ( $sessionData['contribution_tracking_id'] ?? '' ) . ':' .
			( $sessionData['order_id'] ?? '' ) . ' ';
		$gatewayName = $sessionData['gateway'];
		$gatewayClass = DonationInterface::getAdapterClassForGateway( $gatewayName );
		return DonationLoggerFactory::getLoggerForType(
			$gatewayClass, $logPrefix
		);
	}

	protected function addExtraData( array $sessionData, array &$errorData ): void {
		$errorData['donationData'] = $sessionData;
	}
}
