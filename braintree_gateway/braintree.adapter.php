<?php

use MediaWiki\MediaWikiServices;
use Psr\Log\LogLevel;
use SmashPig\Core\PaymentError;
use SmashPig\Core\ValidationError;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\ValidationAction;
use SmashPig\PaymentProviders\Braintree\PaypalPaymentProvider;
use SmashPig\PaymentProviders\Braintree\TransactionType;
use SmashPig\PaymentProviders\PaymentProviderFactory;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;

class BraintreeAdapter extends GatewayAdapter implements RecurringConversion {
	use RecurringConversionTrait;

	const GATEWAY_NAME = 'Braintree';
	const IDENTIFIER = 'braintree';
	const GLOBAL_PREFIX = 'wgBraintreeGateway';

	protected function defineOrderIDMeta() {
		// TODO: Implement defineOrderIDMeta() method.
		$this->order_id_meta = [
			'alt_locations' => [ 'request' => 'merchantReference' ],
			'ct_id' => true,
			'generate' => true,
		];
	}

	protected function defineReturnValueMap() {
		// TODO: Implement defineReturnValueMap() method.
	}

	/**
	 *
	 * @param CreatePaymentResponse $createPaymentResult
	 * @param PaypalPaymentProvider $provider
	 * @return PaymentResult
	 */
	protected function handleCreatedPayment(
		CreatePaymentResponse $createPaymentResult, PaypalPaymentProvider $provider
	): PaymentResult {
		$transactionStatus = $createPaymentResult->getStatus();
		$donorDetails = $createPaymentResult->getDonorDetails();
		// Pull in new data from result if available.
		$this->addResponseData( [
			'gateway_txn_id' => $createPaymentResult->getGatewayTxnId(), // this is always new
			'first_name' => $donorDetails->getFirstName() ?? $this->dataObj->getVal( 'first_name' ),
			'last_name' => $donorDetails->getLastName() ?? $this->dataObj->getVal( 'last_name' ),
			'email' => $donorDetails->getEmail() ?? $this->dataObj->getVal( 'email' ),
			'phone' => $donorDetails->getPhone() // we don't usually collect this
		] );

		$paymentResult = PaymentResult::newSuccess();
		if ( !$createPaymentResult->isSuccessful() ) {
			$paymentResult = PaymentResult::newFailure();
			$errorLogMessage = 'Unsuccessful createPayment response from gateway: ';
			$errorLogMessage .= $createPaymentResult->getStatus() . " : ";
			$errorLogMessage .= json_encode( $createPaymentResult->getRawResponse() );
			$this->logger->info( $errorLogMessage );
		}

		// if its recurring, the response will have a token
		if ( $createPaymentResult->getRecurringPaymentToken() ) {
			$this->addResponseData( [
				'recurring_payment_token' => $createPaymentResult->getRecurringPaymentToken(),
			] );
		}

		switch ( $transactionStatus ) {
			case FinalStatus::PENDING:
			case FinalStatus::PENDING_POKE:
				$this->runAntifraudFilters();
				if ( $this->getValidationAction() !== ValidationAction::PROCESS ) {
					$this->finalizeInternalStatus( FinalStatus::FAILED );
					$paymentResult = PaymentResult::newFailure();
				} else {
					$this->setCurrentTransaction( TransactionType::CAPTURE );
					$capturePaymentParams = $this->buildRequestArray();
					$capturePaymentResponse = $provider->approvePayment( $capturePaymentParams );
					$this->finalizeInternalStatus( $capturePaymentResponse->getStatus() );
				}

				break;
			default:
				// Log and send the payments-init message, and clean out the session
				$this->finalizeInternalStatus( $transactionStatus );
		}

		// Run some post-donation filters and send donation queue message
		$this->postProcessDonation();
		return $paymentResult;
	}

	public function doPayment() {
		$this->ensureUniqueOrderID();
		$this->session_addDonorData();
		$this->setCurrentTransaction( TransactionType::AUTHORIZE );
		Gateway_Extras_CustomFilters::onGatewayReady( $this );
		$this->runSessionVelocityFilter();
		if ( $this->getValidationAction() !== ValidationAction::PROCESS ) {
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
		$this->logger->info( "Calling createPayment for Braintree payment" );
		$createPaymentResult = $provider->createPayment( $createPaymentParams );
		$validationErrors = $createPaymentResult->getValidationErrors();
		// If there are validation errors, present them for correction with a
		// 'refresh' type PaymentResult
		if ( count( $validationErrors ) > 0 ) {
			return $this->getLocalizedValidationErrorResult( $validationErrors );
		}
		$this->logger->info( "Returned PSP Reference {$createPaymentResult->getGatewayTxnId()}" );

		// If we DON'T need to redirect, handle the fraud checks and any
		// necessary payment capture step here and now.
		return $this->handleCreatedPayment( $createPaymentResult, $provider );
	}

	public function getCommunicationType() {
		return 'array';
	}

	protected function defineTransactions() {
		$this->transactions = [
			TransactionType::AUTHORIZE => [
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
					'device_data',
					'user_name',
					'customer_id',
				],
				'values' => [
					'description' => WmfFramework::formatMessage( 'donate_interface-donation-description' ),
				]
			],
			TransactionType::CAPTURE => [
				'request' => [
					'gateway_txn_id'
				]
			]
		];
	}

	protected function defineAccountInfo() {
		$this->accountInfo = [];
	}

	public function getClientToken(): string {
		$provider = PaymentProviderFactory::getProviderForMethod(
			$this->getPaymentMethod()
		);
		$createPaymentSessionResponse = $provider->createPaymentSession();
		return $createPaymentSessionResponse->getPaymentSession();
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
			if ( in_array( $field, [ 'payment_method', 'order_id', 'payment_token' ] ) ) {
				// This means the generated token was invalid.
				$urlParameterKeys = [ 'payment_method',
					'recurring',
					'uselang',
					'language',
					'currency',
					'amount',
					'country',
					'utm_source',
					'utm_medium',
					'utm_campaign' ];
				$urlParameters = [];
				foreach ( $urlParameterKeys as $key ) {
					if ( isset( $this->unstaged_data[$key] ) ) {
						$urlParameters[$key] = $this->unstaged_data[$key];
					}
				}
				$messageKey = 'donate_interface-donate-error-try-again-html';
				$messageParams = [
					GatewayChooser::buildGatewayPageUrl( 'braintree', $urlParameters, MediaWikiServices::getInstance()->getMainConfig() ),
					$this->localizeGlobal( 'OtherWaysURL' ),
					$this->getGlobal( 'ProblemsEmail' )
				];
			} else {
				if ( $field === 'currency' ) {
					$messageKey = 'donate_interface-error-msg-invalid-currency';
				} elseif ( $field === 'device_data' ) {
					$messageKey = 'donate_interface-error-msg-invalid-device-data';
				} else {
					$messageKey = 'donate_interface-error-msg-' . $field;
				}
				$messageParams = [];
			}
			$localizedErrors[] = new ValidationError(
				$field, $messageKey, $messageParams
			);
			$this->logger->info(
				'createPayment call came back with validation error in ' . $field . ( $debugMessage ? ' with message: ' . $debugMessage : '' )
			);
		}
		return PaymentResult::newFailure( $localizedErrors );
	}

	public function getPaymentMethodsSupportingRecurringConversion(): array {
		return [ 'paypal' ];
	}
}
