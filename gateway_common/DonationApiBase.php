<?php

use SmashPig\Core\PaymentError;
use SmashPig\Core\ValidationError;

abstract class DonationApiBase extends ApiBase {

	/**
	 * @var array
	 */
	public $donationData;

	/**
	 * @var string
	 */
	public $gateway;

	/**
	 * @return GatewayAdapter
	 */
	protected function getGatewayObject() {
		$className = DonationInterface::getAdapterClassForGateway( $this->gateway );
		$variant = $this->getRequest()->getVal( 'variant' );
		return new $className( [ 'variant' => $variant ] );
	}

	public static function serializeErrors( $errors, GatewayAdapter $adapter ) {
		$serializedErrors = [];
		foreach ( $errors as $error ) {
			if ( $error instanceof ValidationError ) {
				$message = WmfFramework::formatMessage(
					$error->getMessageKey(),
					$error->getMessageParams()
				);
				$serializedErrors[$error->getField()] = $message;
			} elseif ( $error instanceof PaymentError ) {
				$message = $adapter->getErrorMapByCodeAndTranslate( $error->getErrorCode() );
				$serializedErrors['general'][] = $message;
			} else {
				$logger = DonationLoggerFactory::getLogger( $adapter );
				$logger->error( 'API trying to serialize unknown error type: ' . get_class( $error ) );
			}
		}
		return $serializedErrors;
	}

	public function isReadMode() {
		return false;
	}
}
