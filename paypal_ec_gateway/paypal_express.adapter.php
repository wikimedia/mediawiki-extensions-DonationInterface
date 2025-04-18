<?php

use SmashPig\PaymentData\ErrorCode;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\ValidationAction;
use SmashPig\PaymentProviders\IPaymentProvider;
use SmashPig\PaymentProviders\IRecurringPaymentProfileProvider;
use SmashPig\PaymentProviders\PaymentProviderFactory;
use SmashPig\PaymentProviders\PayPal\PaymentProvider;
use SmashPig\PaymentProviders\Responses\PaymentProviderExtendedResponse;

/**
 * PayPal Express Checkout name value pair integration
 *
 * https://developer.paypal.com/docs/classic/express-checkout/overview-ec/
 * https://developer.paypal.com/docs/classic/products/
 * https://developer.paypal.com/docs/classic/express-checkout/ht_ec-singleItemPayment-curl-etc/
 * https://developer.paypal.com/docs/classic/express-checkout/ht_ec-recurringPaymentProfile-curl-etc/
 * TODO: We would need reference transactions to do recurring in Germany or China.
 * https://developer.paypal.com/docs/classic/express-checkout/integration-guide/ECReferenceTxns/#id094UM0C03Y4
 * https://developer.paypal.com/docs/classic/api/gs_PayPalAPIs/
 * https://developer.paypal.com/docs/classic/express-checkout/integration-guide/ECCustomizing/
 */
class PaypalExpressAdapter extends GatewayAdapter {
	const GATEWAY_NAME = 'Paypal Express Checkout';
	const IDENTIFIER = 'paypal_ec';
	const GLOBAL_PREFIX = 'wgPaypalExpressGateway';

	protected function defineAccountInfo() {
		$this->accountInfo = [];
	}

	/**
	 * Use our own Order ID sequence.
	 */
	protected function defineOrderIDMeta() {
		$this->order_id_meta = [
			'generate' => true,
			'ct_id' => true,
		];
	}

	/** @inheritDoc */
	protected function setGatewayDefaults( $options = [] ) {
		if ( $this->getData_Unstaged_Escaped( 'payment_method' ) == null ) {
			$this->addRequestData(
				[ 'payment_method' => 'paypal' ]
			);
		}
	}

	protected function getDescriptionMessage(): string {
		if ( $this->getData_Unstaged_Escaped( 'recurring' ) == null ||
			$this->getData_Unstaged_Escaped( 'recurring' ) == 0 ||
			$this->getData_Unstaged_Escaped( 'recurring' ) == '' ) {
			return 'donate_interface-donation-description';
		}
		$recurring_message = 'donate_interface-monthly-donation-description';
		if ( $this->getRecurringFrequencyUnit() == 'year' ) {
			$recurring_message = 'donate_interface-annual-donation-description';
		}
		return $recurring_message;
	}

	protected function defineTransactions() {
		$this->transactions = [];

		// https://developer.paypal.com/docs/classic/api/merchant/SetExpressCheckout_API_Operation_NVP/
		$this->transactions['SetExpressCheckout'] = [
			'request' => [
				'return_url',
				'cancel_url',
				'language',
				'amount',
				'currency',
				'description',
				'order_id',
				'recurring',
			],
			'values' => [
				'cancel_url' => ResultPages::getCancelPage( $this ),
			],
		];

		// https://developer.paypal.com/docs/classic/api/merchant/DoExpressCheckoutPayment_API_Operation_NVP/
		$this->transactions['DoExpressCheckoutPayment'] = [
			'request' => [
				'amount',
				'currency',
				'gateway_session_id',
				'description',
				'order_id',
				'processor_contact_id',
			],
			'values' => [
				'description' => WmfFramework::formatMessage( 'donate_interface-donation-description' ),
			],
		];

		// https://developer.paypal.com/docs/classic/api/merchant/CreateRecurringPaymentsProfile_API_Operation_NVP/
		$this->transactions['CreateRecurringPaymentsProfile'] = [
			'request' => [
				'amount',
				'currency',
				'date',
				'description',
				'email',
				'frequency_unit',
				'gateway_session_id',
				'order_id',
			],
			'values' => [
				'date' => time(),
			],
		];
	}

	public function doPayment(): PaymentResult {
		$this->setValidationAction( ValidationAction::PROCESS, true );
		$this->logger->debug( 'Running onGatewayReady filters' );
		Gateway_Extras_CustomFilters::onGatewayReady( $this );
		if ( $this->getValidationAction() != ValidationAction::PROCESS ) {
			return PaymentResult::fromResults(
				$this->setFailedValidationTransactionResponse( 'SetExpressCheckout' ),
				FinalStatus::FAILED
			);
		}
		$this->config['transformers'][] = 'PaypalExpressReturnUrl';
		$this->data_transformers[] = new PaypalExpressReturnUrl();
		$this->stageData();
		$provider = PaymentProviderFactory::getProviderForMethod(
			$this->getPaymentMethod()
		);
		'@phan-var PaymentProvider $provider';
		$this->setCurrentTransaction( 'SetExpressCheckout' );
		$descriptionKey = $this->getDescriptionMessage();

		$this->transactions['SetExpressCheckout']['values']['description'] = WmfFramework::formatMessage( $descriptionKey );
		// Returns a token which and a redirect URL to send the donor to PayPal
		$paymentSessionResult = $provider->createPaymentSession( $this->buildRequestArray() );
		if ( $paymentSessionResult->isSuccessful() ) {
			$this->addResponseData( [ 'gateway_session_id' => $paymentSessionResult->getPaymentSession() ] );
			$this->session_addDonorData();
			return PaymentResult::newRedirect( $paymentSessionResult->getRedirectUrl() );
		}

		return PaymentResult::newFailure( $paymentSessionResult->getErrors() );
	}

	/**
	 * @return bool false, but we're kinda lying.
	 * We do need to DoExpressCheckoutPayment when donors return, but it's
	 * better to lose a few donations and show the thank you page than to
	 * risk duplicate donations and problems for donor services. We handle
	 * donors who return with no cookies by running a pending transaction
	 * resolver like we do with Ingenico.
	 */
	public function isReturnProcessingRequired() {
		return false;
	}

	/** @inheritDoc */
	public function getRequestProcessId( $requestValues ) {
		return $requestValues['token'];
	}

	/** @inheritDoc */
	public function processDonorReturn( $requestValues ) {
		if (
			empty( $requestValues['token'] )
		) {
			throw new ResponseProcessingException(
				'Missing required parameters in request',
				ErrorCode::MISSING_REQUIRED_DATA
			);
		}
		$this->setValidationAction( ValidationAction::PROCESS, true );
		$this->logger->debug( 'Running onGatewayReady filters' );
		Gateway_Extras_CustomFilters::onGatewayReady( $this );
		if ( $this->getValidationAction() != ValidationAction::PROCESS ) {
			return PaymentResult::fromResults(
				$this->setFailedValidationTransactionResponse( 'ProcessDonorReturn' ),
				FinalStatus::FAILED
			);
		}
		$requestData = [
			'gateway_session_id' => urldecode( $requestValues['token'] )
		];
		if (
			empty( $requestValues['PayerID'] )
		) {
			$this->logger->info( 'Notice missing PayerID in PaypalExpressAdapater::ProcessDonorReturn' );
		} else {
			$requestData['payer_id'] = $requestValues['PayerID'];
		}
		$this->addRequestData( $requestData );
		$provider = PaymentProviderFactory::getProviderForMethod(
			$this->getPaymentMethod()
		);
		'@phan-var PaymentProvider $provider';
		$detailsResult = $provider->getLatestPaymentStatus( [
			'gateway_session_id' => $requestData['gateway_session_id']
		] );

		if ( !$detailsResult->isSuccessful() ) {
			if ( $detailsResult->requiresRedirect() ) {
				return PaymentResult::newRedirect( $detailsResult->getRedirectUrl() );
			}
			$this->finalizeInternalStatus( $detailsResult->getStatus() );
			return PaymentResult::newFailure( $detailsResult->getErrors() );
		}
		$this->addDonorDetailsToSession( $detailsResult );
		if ( $detailsResult->getStatus() === FinalStatus::PENDING_POKE ) {
			$this->runAntifraudFilters();
			if ( $this->getValidationAction() !== ValidationAction::PROCESS ) {
				$this->finalizeInternalStatus( FinalStatus::FAILED );
				return PaymentResult::fromResults(
					$this->setFailedValidationTransactionResponse( 'SetExpressCheckout' ),
					FinalStatus::FAILED
				);
			}
			if ( $this->getData_Unstaged_Escaped( 'recurring' ) ) {
				return $this->createRecurringProfile( $provider );
			} else {
				return $this->captureOneTimePayment( $provider );
			}
		} else {
			$this->finalizeInternalStatus( $detailsResult->getStatus() );
		}
		return PaymentResult::fromResults(
			new PaymentTransactionResponse(),
			$this->getFinalStatus()
		);
	}

	protected function addDonorDetailsToSession( PaymentProviderExtendedResponse $detailResponse ): void {
		$donorDetails = $detailResponse->getDonorDetails();
		if ( $donorDetails !== null ) {
			$responseData = [
				'first_name' => $donorDetails->getFirstName(),
				'last_name' => $donorDetails->getLastName(),
				'email' => $donorDetails->getEmail(),
				'processor_contact_id' => $detailResponse->getProcessorContactID(),
			];
			$address = $donorDetails->getBillingAddress();
			if ( $address !== null ) {
				$responseData += [
					'city' => $address->getCity(),
					'street_address' => $address->getStreetAddress(),
					'postal_code' => $address->getPostalCode(),
					'state_province' => $address->getPostalCode()
				];
				if ( $address->getCountryCode() !== null ) {
					$responseData[ 'country' ] = $address->getCountryCode();
				}
			}
			$this->addResponseData( $responseData );
			$this->session_addDonorData();
		}
	}

	/**
	 * @param IRecurringPaymentProfileProvider $provider
	 * @return PaymentResult
	 */
	protected function createRecurringProfile( IRecurringPaymentProfileProvider $provider ): PaymentResult {
		$this->setCurrentTransaction( 'CreateRecurringPaymentsProfile' );
		$descriptionKey = $this->getDescriptionMessage();
		$this->transactions['CreateRecurringPaymentsProfile']['values']['description'] = WmfFramework::formatMessage( $descriptionKey );

		$profileParams = $this->buildRequestArray();
		$createProfileResponse = $provider->createRecurringPaymentsProfile( $profileParams );
		if ( $createProfileResponse->isSuccessful() ) {
			$this->addResponseData( [
				'subscr_id' => $createProfileResponse->getProfileId()
			] );

			// We've created a subscription, but we haven't got an initial
			// payment yet, so we leave the details in the pending queue.
			// The IPN listener will push the donation through to Civi when
			// it gets notifications from PayPal.
			// TODO: it would be nice to send the subscr_start message to
			// the recurring queue here.
			$this->finalizeInternalStatus( FinalStatus::PENDING );
			$this->postProcessDonation();
			return PaymentResult::newSuccess();
		} else {
			if ( $createProfileResponse->requiresRedirect() ) {
				return PaymentResult::newRedirect( $createProfileResponse->getRedirectUrl() );
			}
			$this->finalizeInternalStatus( FinalStatus::FAILED );
			return PaymentResult::newFailure( $createProfileResponse->getErrors() );
		}
	}

	/**
	 * @param IPaymentProvider $provider
	 * @return PaymentResult
	 */
	protected function captureOneTimePayment( IPaymentProvider $provider ): PaymentResult {
		// One-time payment, or initial payment in a subscription.
		$this->setCurrentTransaction( 'DoExpressCheckoutPayment' );
		$approvePaymentParams = $this->buildRequestArray();
		$approveResult = $provider->approvePayment( $approvePaymentParams );
		if ( $approveResult->isSuccessful() ) {
			$this->addResponseData(
				[ 'gateway_txn_id' => $approveResult->getGatewayTxnId() ]
			);
			$this->finalizeInternalStatus( FinalStatus::COMPLETE );
			$this->postProcessDonation();
			return PaymentResult::newSuccess();
		} else {
			$this->finalizeInternalStatus( FinalStatus::FAILED );
			return PaymentResult::newFailure( $approveResult->getErrors() );
		}
	}

	/**
	 * TODO: add test
	 * @return array
	 */
	public function createDonorReturnParams() {
		return [ 'token' => $this->getData_Staged( 'gateway_session_id' ) ];
	}

	/**
	 * @return string
	 */
	public function getRecurringFrequencyUnit() {
		return $this->getData_Staged( 'frequency_unit' );
	}
}
