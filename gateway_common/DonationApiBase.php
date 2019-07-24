<?php

use SmashPig\Core\Logging\Logger;
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
	 * @var GatewayAdapter
	 */
	protected $adapter;

	/**
	 * @return GatewayAdapter
	 */
	protected function getGatewayObject() {
		$className = DonationInterface::getAdapterClassForGateway( $this->gateway );
		$variant = $this->getRequest()->getVal( 'variant' );
		return new $className( [ 'variant' => $variant ] );
	}

	protected function serializeErrors( $errors ) {
		$serializedErrors = [];
		foreach ( $errors as $error ) {
			if ( $error instanceof ValidationError ) {
				$message = WmfFramework::formatMessage(
					$error->getMessageKey(),
					$error->getMessageParams()
				);
				$serializedErrors[$error->getField()] = $message;
			} elseif ( $error instanceof PaymentError ) {
				$message = $this->adapter->getErrorMapByCodeAndTranslate( $error->getErrorCode() );
				$serializedErrors['general'][] = $message;
			} else {
				$logger = DonationLoggerFactory::getLogger( $this->adapter );
				$logger->error( 'API trying to serialize unknown error type: ' . get_class( $error ) );
			}
		}
		return $serializedErrors;
	}

	protected function setAdapterAndValidate() {
		$this->donationData = $this->extractRequestParams();

		$this->gateway = $this->donationData['gateway'];

		DonationInterface::setSmashPigProvider( $this->gateway );
		$this->adapter = $this->getGatewayObject();

		if ( !$this->adapter ) {
			return false; // already failed with a dieUsage call
		}

		// FIXME: SmashPig should just use Monolog.
		Logger::getContext()->enterContext( $this->adapter->getLogMessagePrefix() );

		$validated_ok = $this->adapter->validatedOK();
		if ( !$validated_ok ) {
			$errors = $this->adapter->getErrorState()->getErrors();
			$outputResult['errors'] = $this->serializeErrors( $errors );
			// FIXME: What is this junk?  Smaller API, like getResult()->addErrors
			$this->getResult()->setIndexedTagName( $outputResult['errors'], 'error' );
			$this->getResult()->addValue( null, 'result', $outputResult );
			return false;
		}
		return true;
	}

	public function isReadMode() {
		return false;
	}

	public function mustBePosted() {
		return true;
	}
}
