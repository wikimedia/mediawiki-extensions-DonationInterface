<?php

use MediaWiki\MediaWikiServices;
use Psr\Log\LogLevel;
use SmashPig\Core\PaymentError;
use SmashPig\Core\ValidationError;
use SmashPig\PaymentData\ErrorCode;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\ValidationAction;
use SmashPig\PaymentProviders\IPaymentProvider;
use SmashPig\PaymentProviders\PaymentProviderFactory;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;
use SmashPig\PaymentProviders\Responses\PaymentDetailResponse;

class DlocalAdapter extends GatewayAdapter {
	use RecurringConversionTrait;

	/**
	 * @var string
	 */
	const GATEWAY_NAME = 'Dlocal';

	/**
	 * @var string
	 */
	const IDENTIFIER = 'dlocal';

	/**
	 * @var string
	 */
	const GLOBAL_PREFIX = 'wgDlocalGateway';

	public function doPayment() {
		$this->ensureUniqueOrderID();
		$this->session_addDonorData();
		$this->setCurrentTransaction( 'authorize' );
		$this->runDoPaymentFilters();
		if ( !$this->filterActionIsProcess() ) {
			// Ensure IPVelocity filter session value is reset on error
			WmfFramework::setSessionValue( Gateway_Extras_CustomFilters_IP_Velocity::RAN_INITIAL, false );
			return $this->newFailureWithError( 'internal-0000', 'Failed pre-process checks for payment.' );
		}

		$paymentProvider = PaymentProviderFactory::getProviderForMethod( $this->getPaymentMethod() );
		$createPaymentResponse = $this->callCreatePayment( $paymentProvider );

		if ( count( $createPaymentResponse->getValidationErrors() ) > 0 ) {
			return $this->getLocalizedValidationErrorResult( $createPaymentResponse->getValidationErrors() );
		}

		if ( $createPaymentResponse->requiresRedirect() ) {
			$this->addResponseData( [
				'gateway_txn_id' => $createPaymentResponse->getGatewayTxnId(),
			] );
			$redirectUrl = $createPaymentResponse->getRedirectUrl();
			$this->logger->info( "Redirecting to {$redirectUrl}" );
			return PaymentResult::newRedirect( $redirectUrl );
		}

		// Handle fraud checks and any necessary payment capture step here and now.
		return $this->handleCreatedPayment( $createPaymentResponse, $paymentProvider );
	}

	/**
	 * Process the request values sent over with the donor redirect from the dlocal's server.
	 *
	 * We receive a bunch of params in $requestValues. However,
	 * we only care about the payment_id. We use payment_id to look up
	 * the latest status of the payment the donor just made and decide
	 * the next steps based on the status. dLocal advises us to do this.
	 *
	 * @param array $requestValues
	 * @return PaymentResult
	 */
	public function processDonorReturn( $requestValues ): PaymentResult {
		if ( !isset( $requestValues['payment_id'] ) ) {
			$this->logger->error( "Missing required parameters in request" );
			return $this->newFailureWithError( ErrorCode::MISSING_REQUIRED_DATA, 'Missing required parameters in request' );
		}

		// check the status of the payment the donor just made and processed the result
		$paymentProvider = PaymentProviderFactory::getProviderForMethod( $this->getPaymentMethod() );
		$paymentStatusParams = [ 'gateway_txn_id' => $requestValues['payment_id'] ];
		$paymentStatusResult = $paymentProvider->getLatestPaymentStatus( $paymentStatusParams );
		return $this->handleCreatedPayment( $paymentStatusResult, $paymentProvider );
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
					'payment_method_id',
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
	 * @param PaymentDetailResponse $paymentDetailResponse
	 * @param IPaymentProvider $paymentProvider
	 * @return PaymentResult
	 */
	protected function handleCreatedPayment(
		PaymentDetailResponse $paymentDetailResponse,
		IPaymentProvider $paymentProvider
	): PaymentResult {
		$transactionStatus = $paymentDetailResponse->getStatus();
		$this->addResponseData( [ 'gateway_txn_id' => $paymentDetailResponse->getGatewayTxnId(), ] );
		$paymentResult = PaymentResult::newSuccess();

		if ( !$paymentDetailResponse->isSuccessful() ) {
			$paymentResult = PaymentResult::newFailure();
			$this->logPaymentDetailFailure( $paymentDetailResponse );
			$this->finalizeInternalStatus( $transactionStatus );
		} elseif ( $paymentDetailResponse->requiresApproval() ) {
			$this->runAntifraudFilters();
			if ( !$this->filterActionIsProcess() ) {
				$this->finalizeInternalStatus( FinalStatus::FAILED );
				$paymentResult = PaymentResult::newFailure();
			} else {
				$this->setCurrentTransaction( 'capture' );
				$capturePaymentParams = $this->buildRequestArray();
				$this->logger->info(
					"Calling approvePayment with gateway_txn_id: " . $paymentDetailResponse->getGatewayTxnId()
				);
				$approvePaymentResponse = $paymentProvider->approvePayment( $capturePaymentParams );
				$this->finalizeInternalStatus( $approvePaymentResponse->getStatus() );
			}
		} else {
			$this->finalizeInternalStatus( $transactionStatus );
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
					self::getGlobal( 'ProblemsEmail' )
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

	/**
	 * @param IPaymentProvider $paymentProvider
	 * @return CreatePaymentResponse
	 */
	protected function callCreatePayment( IPaymentProvider $paymentProvider ): CreatePaymentResponse {
		$createPaymentParams = $this->buildRequestArray();
		$this->logger->info( "Calling createPayment for Dlocal payment" );
		$createPaymentResponse = $paymentProvider->createPayment( $createPaymentParams );
		if ( !empty( $createPaymentResponse->getGatewayTxnId() ) ) {
			$this->logger->info( "Returned Authorization ID {$createPaymentResponse->getGatewayTxnId()}" );
		}

		return $createPaymentResponse;
	}

	/**
	 * @return void
	 */
	protected function runDoPaymentFilters(): void {
		Gateway_Extras_CustomFilters::onGatewayReady( $this );
		$this->runSessionVelocityFilter();
	}

	/**
	 * @return bool
	 */
	protected function filterActionIsProcess(): bool {
		return $this->getValidationAction() === ValidationAction::PROCESS;
	}

	/**
	 * @param string $errorCode
	 * @param string $debugMessage
	 * @return PaymentResult
	 */
	protected function newFailureWithError( $errorCode, $debugMessage ): PaymentResult {
		$paymentError = new PaymentError(
			$errorCode,
			$debugMessage,
			LogLevel::INFO
		);
		return PaymentResult::newFailure( [ $paymentError ] );
	}

	/**
	 * @param PaymentDetailResponse $paymentDetailResponse
	 * @return void
	 */
	protected function logPaymentDetailFailure( PaymentDetailResponse $paymentDetailResponse ): void {
		$errorLogMessage = 'Unsuccessful createPayment response from gateway: ';
		$errorLogMessage .= $paymentDetailResponse->getStatus() . " : ";
		$rawResponse = $paymentDetailResponse->getRawResponse();
		if ( isset( $rawResponse['card'] ) ) {
			unset( $rawResponse['card'] );
		}
		$errorLogMessage .= json_encode( $rawResponse );
		$this->logger->info( $errorLogMessage );
	}
}
