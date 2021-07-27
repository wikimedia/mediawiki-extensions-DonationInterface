<?php

/**
 * Generic Donation API
 * This API should be able to accept donation submissions for any gateway or payment type
 * Call with api.php?action=donate
 */
class DonationApi extends DonationApiBase {
	public function execute() {
		$isValid = $this->setAdapterAndValidate();
		if ( !$isValid ) {
			return;
		}

		$paymentResult = $this->adapter->doPayment();

		$outputResult = [
			'iframe' => $paymentResult->getIframe(),
			'redirect' => $paymentResult->getRedirect(),
			'formData' => $paymentResult->getFormData(),
			'isFailed' => $paymentResult->isFailed(),
			// META_BC_BOOLS is a metadata key to tell ApiResult which keys
			// should be preserved in the output even if their value is false
			ApiResult::META_BC_BOOLS => [ 'isFailed' ]
		];

		$errors = $paymentResult->getErrors();

		// FIXME: don't need this if we've gotten a payment all the way
		// done at this point. Stop double logging for adyen checkout
		$sendingDonorToProcessor = empty( $errors ) &&
			( !empty( $outputResult['iframe'] ) || !empty( $outputResult['redirect'] ) );

		if ( $sendingDonorToProcessor ) {
			$this->adapter->logPending();
			$this->markLiberatedOnRedirect( $paymentResult );
		}

		if ( !empty( $errors ) ) {
			$outputResult['errors'] = $this->serializeErrors( $errors );
			$this->getResult()->setIndexedTagName( $outputResult['errors'], 'error' );
		}

		$this->getResult()->addValue( null, 'result', $outputResult );
	}

	public function getAllowedParams() {
		return [
			'gateway' => $this->defineParam( true ),
			'contact_id' => $this->defineParam( false ),
			'contact_hash' => $this->defineParam( false ),
			'amount' => $this->defineParam( false ),
			'currency' => $this->defineParam( false ),
			'first_name' => $this->defineParam( false ),
			'last_name' => $this->defineParam( false ),
			'street_address' => $this->defineParam( false ),
			'supplemental_address_1' => $this->defineParam( false ),
			'city' => $this->defineParam( false ),
			'state_province' => $this->defineParam( false ),
			'postal_code' => $this->defineParam( false ),
			'email' => $this->defineParam( false ),
			'country' => $this->defineParam( false ),
			'card_num' => $this->defineParam( false ),
			'card_type' => $this->defineParam( false ),
			'expiration' => $this->defineParam( false ),
			'cvv' => $this->defineParam( false ),
			'payment_method' => $this->defineParam( false ),
			'payment_submethod' => $this->defineParam( false ),
			'processor_form' => $this->defineParam( false ),
			'language' => $this->defineParam( false ),
			'order_id' => $this->defineParam( false ),
			'wmf_token' => $this->defineParam( true ),
			'utm_source' => $this->defineParam( false ),
			'utm_campaign' => $this->defineParam( false ),
			'utm_medium' => $this->defineParam( false ),
			'referrer' => $this->defineParam( false ),
			'recurring' => $this->defineParam( false ),
			'variant' => $this->defineParam( false ),
			'opt_in' => $this->defineParam( false ),
			'employer' => $this->defineParam( false ),
			'employer_id' => $this->defineParam( false ),
		];
	}

	protected function defineParam( $required = false, $type = 'string' ) {
		if ( $required ) {
			$param = [ ApiBase::PARAM_TYPE => $type, ApiBase::PARAM_REQUIRED => true ];
		} else {
			$param = [ ApiBase::PARAM_TYPE => $type ];
		}
		return $param;
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 * @return array
	 */
	protected function getExamplesMessages() {
		return [
			'action=donate&gateway=globalcollect&amount=2.00&currency=USD'
				=> 'apihelp-donate-example-1',
		];
	}

	/**
	 * If we are sending the donor to a payment processor with a full redirect
	 * rather than inside an iframe, mark the order ID as 'liberated' so when
	 * they come back, we don't waste time trying to pop them out of a frame.
	 *
	 * @param PaymentResult $paymentResult
	 */
	protected function markLiberatedOnRedirect( PaymentResult $paymentResult ) {
		if ( !$paymentResult->getRedirect() ) {
			return;
		}
		// Save a flag in session saying we don't need to pop out of an iframe
		// See related code in GatewayPage::handleResultRequest
		$oid = $this->adapter->getData_Unstaged_Escaped( 'order_id' );
		$sessionOrderStatus = $this->adapter->session_getData( 'order_status' );
		$sessionOrderStatus[$oid] = 'liberated';
		WmfFramework::setSessionValue( 'order_status', $sessionOrderStatus );
	}
}
