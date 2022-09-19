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
		$this->ensureState();

		DonationInterface::setSmashPigProvider( $this->gateway );

		if ( isset( $this->donationData['language'] ) ) {
			// setLanguage will sanitize the code, replacing it with the base wiki
			// language in case it's invalid.
			RequestContext::getMain()->setLanguage( $this->donationData['language'] );
		}

		// This should have been set above in ensureState().
		if ( !$this->adapter ) {
			// Legacy comment, maybe outdated: already failed with a dieUsage call
			return false;
		}

		// FIXME: SmashPig should just use Monolog.
		Logger::getContext()->enterContext( $this->adapter->getLogMessagePrefix() );

		$errors = [];
		if ( !$this->adapter->checkTokens() ) {
			$errors['wmf_token'] = WmfFramework::formatMessage( 'donate_interface-token-mismatch' );
		} else {
			$validated_ok = $this->adapter->validatedOK();
			if ( !$validated_ok ) {
				$errors = $this->serializeErrors(
					$this->adapter->getErrorState()->getErrors()
				);
			}
		}
		if ( !empty( $errors ) ) {
			$outputResult = [ 'errors' => $errors ];
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

	/**
	 * Ensures the following are set, if possible:
	 *  - $this->donationData
	 *  - $this->gateway
	 *  - $this->adapter
	 */
	protected function ensureState() {
		if ( !$this->donationData ) {
			$this->donationData = $this->extractRequestParams();
		}

		if ( !$this->gateway && $this->donationData ) {
			$this->gateway = $this->donationData[ 'gateway' ];
		}

		if ( !$this->adapter && $this->gateway ) {
			$className = DonationInterface::getAdapterClassForGateway( $this->gateway );

			if ( $className::getGlobal( 'Enabled' ) === true ) {

				// FIXME: Some subclasses don't get variant the noraml way
				$variant = $this->donationData[ 'variant' ] ??
					$this->getRequest()->getVal( 'variant' );

				$this->adapter = new $className( [ 'variant' => $variant ] );
			}
		}
	}

	/**
	 * Provides an appropriate logger object.
	 *
	 * @return \Psr\Log\LoggerInterface
	 */
	protected function getLogger(): \Psr\Log\LoggerInterface {
		$this->ensureState();
		return $this->adapter
			? DonationLoggerFactory::getLogger( $this->adapter )
			: DonationLoggerFactory::getLoggerForType(
				DonationInterface::getAdapterClassForGateway( $this->gateway )
			);
	}
}
