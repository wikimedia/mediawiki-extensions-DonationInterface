<?php

use SmashPig\PaymentProviders\PaymentProviderFactory;

class AdyenCheckoutAdapter extends GatewayAdapter {
	const GATEWAY_NAME = 'AdyenCheckout';
	const IDENTIFIER = 'adyen';
	const GLOBAL_PREFIX = 'wgAdyenCheckoutGateway';

	public function doPayment() {
		$this->ensureUniqueOrderID();
		$this->session_addDonorData();
		$provider = PaymentProviderFactory::getProviderForMethod(
			$this->getPaymentMethod()
		);
		// Log details of the payment in case we need to reconstruct it for
		// audit files. TODO: this says 'redirecting' but we're not actually
		// sending the donor off site. Log a different prefix here and update
		// the audit grepper to find that prefix.
		$this->logPaymentDetails();
		// TODO: define txn structure + use var_map
		$authorizeParams = [
			'encrypted_payment_data' => [
				// FIXME silly mapping back and forth on both sides of the mediawiki
				// donate API call. Maybe just send blob from front end?
				'encryptedCardNumber' => $this->getData_Staged( 'card_num' ),
				'encryptedExpiryMonth' => $this->getData_Staged( 'expiration' ),
				// HACK HACK HACK, using a totally dumb field here to not touch API yet
				'encryptedExpiryYear' => $this->getData_Staged( 'processor_form' ),
				'encryptedSecurityCode' => $this->getData_Staged( 'cvv' )
			],
			'description' => WmfFramework::formatMessage( 'donate_interface-donation-description' )
		];
		$paramsToCopy = [
			'amount',
			'city',
			'country',
			'currency',
			'email',
			'first_name',
			'last_name',
			'order_id',
			'postal_code',
			'state_province',
			'street_address',
			'user_ip'
		];
		foreach ( $paramsToCopy as $paramName ) {
			$authorizeParams[$paramName] = $this->getData_Staged( $paramName );
		}
		$authorizeResult = $provider->createPayment( $authorizeParams );
		if ( !$authorizeResult->isSuccessful() ) {
			return PaymentResult::newFailure();
		}
		if ( $authorizeResult->requiresApproval() ) {
			// TODO: run minfraud, check risk score from AVS / CVV
			$captureResult = $provider->approvePayment( [
				// Note that approvePayment takes the unstaged amount
				'amount' => $this->getData_Unstaged_Escaped( 'amount' ),
				'currency' => $this->getData_Staged( 'currency' ),
				'gateway_txn_id' => $authorizeResult->getGatewayTxnId(),
			] );
			// Note: this transaction ID is different from the authorizeResult's
			// transaction ID. For credit cards, this is the one we want to store
			// in CiviCRM.
			$this->addResponseData( [
				'gateway_txn_id' => $captureResult->getGatewayTxnId(),
			] );
		} else {
			$this->addResponseData( [
				'gateway_txn_id' => $authorizeResult->getGatewayTxnId(),
			] );
		}
		// TODO send messages to payments-init, donations, and payments-antifraud
		return PaymentResult::newSuccess();
	}

	public function getCommunicationType() {
		// TODO: Implement getCommunicationType() method.
	}

	protected function getBasedir() {
		return __DIR__;
	}

	protected function defineTransactions() {
		// TODO: Implement defineTransactions() method.
	}

	protected function defineAccountInfo() {
		// TODO: Implement defineAccountInfo() method.
	}

	protected function defineReturnValueMap() {
		// TODO: Implement defineReturnValueMap() method.
	}

	protected function defineOrderIDMeta() {
		$this->order_id_meta = [
			'alt_locations' => [ 'request' => 'merchantReference' ],
			'ct_id' => true,
			'generate' => true,
		];
	}

	public function getCheckoutConfiguration() {
		return [
			'clientKey' => $this->getAccountConfig( 'ClientKey' ),
			'locale' => str_replace( '_', '-', $this->getData_Staged( 'language' ) ),
			// TODO: either make a getAvailablePaymentMethods request for this
			// or return a paymentMethods array customized to the donor's
			// selected paymentMethod.
			'paymentMethodsResponse' => [
				'paymentMethods' => [ [
					'brands' => [ 'visa', 'mc', 'amex', 'discover', 'cup', 'maestro', 'diners', 'jcb' ],
					'details' => [
						[ 'key' => 'encryptedCardNumber', 'type' => 'cardToken' ],
						[ 'key' => 'encryptedSecurityCode', 'type' => 'cardToken' ],
						[ 'key' => 'encryptedExpiryMonth', 'type' => 'cardToken' ],
						[ 'key' => 'encryptedExpiryYear', 'type' => 'cardToken' ],
						[ 'key' => 'holderName', 'optional' => true, 'type' => 'text' ]
					],
					'type' => 'scheme'
				] ]
			],
			// TODO: maybe make this dynamic based on donor location
			'environment' => $this->getAccountConfig( 'Environment' ),
		];
	}
}
