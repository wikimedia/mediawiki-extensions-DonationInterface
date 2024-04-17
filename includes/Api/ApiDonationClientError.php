<?php

namespace MediaWiki\Extension\DonationInterface\Api;

use DonationInterface;
use DonationLoggerFactory;
use GatewayAdapter;
use Psr\Log\LoggerInterface;

class ApiDonationClientError extends ApiClientErrorBase {

	private ?GatewayAdapter $adapter = null;

	protected function validateSessionData( ?array $sessionData ): bool {
		return parent::validateSessionData( $sessionData ) && !empty( $sessionData['gateway'] );
	}

	protected function getLogger( array $sessionData ): LoggerInterface {
		return DonationLoggerFactory::getLogger( $this->getAdapterFromSessionData( $sessionData ) );
	}

	protected function addExtraData( array $sessionData, array &$errorData ): void {
		$adapter = $this->getAdapterFromSessionData( $sessionData );
		$errorData['donationData'] = $adapter->getData_Unstaged_Escaped();
	}

	private function getAdapterFromSessionData( array $sessionData ): GatewayAdapter {
		if ( $this->adapter === null ) {
			$gatewayName = $sessionData['gateway'];
			$gatewayClass = DonationInterface::getAdapterClassForGateway( $gatewayName );
			$this->adapter = new $gatewayClass();
		}
		return $this->adapter;
	}
}
