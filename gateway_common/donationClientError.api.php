<?php

class DonationClientErrorApi extends ClientErrorBaseApi {

	private ?GatewayAdapter $adapter = null;

	protected function validateSessionData( ?array $sessionData ): bool {
		return !empty( $sessionData ) && !empty( $sessionData['gateway'] );
	}

	protected function getLogger( array $sessionData ): \Monolog\Logger {
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
