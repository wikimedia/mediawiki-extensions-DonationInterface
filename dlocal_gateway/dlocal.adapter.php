<?php

use MediaWiki\MediaWikiServices;
use Psr\Log\LogLevel;
use SmashPig\Core\PaymentError;
use SmashPig\Core\ValidationError;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\ValidationAction;
use SmashPig\PaymentProviders\IPaymentProvider;
use SmashPig\PaymentProviders\PaymentProviderFactory;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;

class DlocalAdapter extends GatewayAdapter {
	use RecurringConversionTrait;

	const GATEWAY_NAME = 'Dlocal';
	const IDENTIFIER = 'dlocal';
	const GLOBAL_PREFIX = 'wgDlocalGateway';

	public function doPayment() {
		$this->ensureUniqueOrderID();
		$this->session_addDonorData();
		$this->setCurrentTransaction( 'authorize' );
		Gateway_Extras_CustomFilters::onGatewayReady( $this );
		$this->runSessionVelocityFilter();
		if ( $this->getValidationAction() !== ValidationAction::PROCESS ) {
			// Ensure IPVelocity filter session value is reset on error
			WmfFramework::setSessionValue( Gateway_Extras_CustomFilters_IP_Velocity::RAN_INITIAL, false );
			return PaymentResult::newFailure( [ new PaymentError(
				'internal-0000',
				"Failed pre-process checks for payment.",
				LogLevel::INFO
			) ] );
		}
		$provider = PaymentProviderFactory::getProviderForMethod(
			$this->getPaymentMethod()
		);
		$createPaymentParams = $this->buildRequestArray();
		$this->logger->info( "Calling createPayment for Dlocal payment" );
		$createPaymentResult = $provider->createPayment( $createPaymentParams );
		$authorizationID = $createPaymentResult->getGatewayTxnId();
		if ( !empty( $authorizationID ) ) {
			$this->logger->info( "Returned Authorization ID {$authorizationID}" );
		}
		// for redirect payment method
		if ( $createPaymentResult->requiresRedirect() ) {
			$this->addResponseData( [
				'gateway_txn_id' => $authorizationID,
			] );
			$redirectUrl = $createPaymentResult->getRedirectUrl();
			$this->logger->info( "Dlocal redirect payment flow url $redirectUrl" );
			return PaymentResult::newRedirect( $redirectUrl );
		}
		$validationErrors = $createPaymentResult->getValidationErrors();

		// If there are validation errors, present them for correction with a
		// 'refresh' type PaymentResult.
		if ( count( $validationErrors ) > 0 ) {
			return $this->getLocalizedValidationErrorResult( $validationErrors );
		}

		// Handle fraud checks and any necessary payment capture step here and now.
		return $this->handleCreatedPayment( $createPaymentResult, $provider );
	}

	public function getCommunicationType() {
		// TODO: Implement getCommunicationType() method.
	}

	protected function getBasedir() {
		return __DIR__;
	}

	protected function defineTransactions() {
		$this->transactions = [
			'authorize' => [
				'request' => [
					'amount',
					'city',
					'country',
					'currency',
					'description',
					'email',
					'first_name',
					'last_name',
					'order_id',
					'postal_code',
					'state_province',
					'street_address',
					'user_ip',
					'recurring',
					'payment_token',
					'street_address',
					'street_number',
					'fiscal_number'
				],
				'values' => [
					'description' => WmfFramework::formatMessage( 'donate_interface-donation-description' )
				]
			],
			'capture' => [
				'request' => [
					'amount',
					'gateway_txn_id',
					'currency',
					'order_id'
				]
			]
		];
	}

	/**
	 *
	 * @param CreatePaymentResponse $createPaymentResult
	 * @param IPaymentProvider $provider
	 * @return PaymentResult
	 */
	protected function handleCreatedPayment(
		CreatePaymentResponse $createPaymentResult,
		IPaymentProvider $provider
	): PaymentResult {
		$transactionStatus = $createPaymentResult->getStatus();

		$this->addResponseData( [
			'gateway_txn_id' => $createPaymentResult->getGatewayTxnId(),
		] );

		$paymentResult = PaymentResult::newSuccess();
		if ( !$createPaymentResult->isSuccessful() ) {
			$paymentResult = PaymentResult::newFailure();
			$errorLogMessage = 'Unsuccessful createPayment response from gateway: ';
			$errorLogMessage .= $createPaymentResult->getStatus() . " : ";
			$rawResponse = $createPaymentResult->getRawResponse();
			unset( $rawResponse['card'] );
			$errorLogMessage .= json_encode( $rawResponse );
			$this->logger->info( $errorLogMessage );
		} else {
			if ( $createPaymentResult->requiresApproval() ) {
				$this->runAntifraudFilters();
				if ( $this->getValidationAction() !== ValidationAction::PROCESS ) {
					$this->finalizeInternalStatus( FinalStatus::FAILED );
					$paymentResult = PaymentResult::newFailure();
				} else {
					$this->setCurrentTransaction( 'capture' );
					$capturePaymentParams = $this->buildRequestArray();
					$this->logger->info(
						"Calling approvePayment with gateway_txn_id: " . $createPaymentResult->getGatewayTxnId()
					);
					$capturePaymentResponse = $provider->approvePayment( $capturePaymentParams );
					$this->finalizeInternalStatus( $capturePaymentResponse->getStatus() );
				}
			} else {
				$this->finalizeInternalStatus( $transactionStatus );
			}
		}

		// Run some post-donation filters and send donation queue message
		$this->postProcessDonation();
		return $paymentResult;
	}

	/**
	 * @param ValidationError[] $validationErrors
	 * @return PaymentResult
	 */
	protected function getLocalizedValidationErrorResult( array $validationErrors ): PaymentResult {
		$localizedErrors = [];
		foreach ( $validationErrors as $error ) {
			$field = $error->getField();
			$debugMessage = $error->getDebugMessage();
			if ( $field === 'payment_token' ) {
				// This means the generated token was invalid.
				$urlParameterKeys = [
					'payment_method',
					'recurring',
					'uselang',
					'language',
					'currency',
					'amount',
					'country',
					'utm_source',
					'utm_medium',
					'utm_campaign'
				];
				$urlParameters = [];
				foreach ( $urlParameterKeys as $key ) {
					if ( isset( $this->unstaged_data[$key] ) ) {
						$urlParameters[$key] = $this->unstaged_data[$key];
					}
				}
				$messageKey = 'donate_interface-donate-error-try-again-html';
				$messageParams = [
					GatewayChooser::buildGatewayPageUrl(
						'dlocal',
						$urlParameters,
						MediaWikiServices::getInstance()->getMainConfig()
					),
					$this->localizeGlobal( 'OtherWaysURL' ),
					$this->getGlobal( 'ProblemsEmail' )
				];
			} else {
				if ( $field === 'currency' ) {
					$messageKey = 'donate_interface-error-msg-invalid-currency';
				} else {
					$messageKey = 'donate_interface-error-msg-' . $field;
				}
				$messageParams = [];
			}
			$localizedErrors[] = new ValidationError(
				$field, $messageKey, $messageParams
			);
			$this->logger->info(
				'createPayment call came back with validation error in ' . $field . ( $debugMessage
					? ' with message: ' . $debugMessage : '' )
			);
		}
		return PaymentResult::newRefresh( $localizedErrors );
	}

	protected function defineOrderIDMeta() {
		$this->order_id_meta = [
			'ct_id' => true,
			'generate' => true,
		];
	}

	protected function defineAccountInfo() {
		$this->accountInfo = $this->account_config;
	}

	protected function defineReturnValueMap() {
		// TODO: Implement defineReturnValueMap() method.
	}
}
