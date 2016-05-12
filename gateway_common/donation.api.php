<?php
/**
 * Generic Donation API
 * This API should be able to accept donation submissions for any gateway or payment type
 * Call with api.php?action=donate
 */
class DonationApi extends ApiBase {
	public $donationData, $gateway;
	public function execute() {
		$this->donationData = $this->extractRequestParams();

		$this->gateway = $this->donationData['gateway'];

		$method = $this->donationData['payment_method'];
		// @todo FIXME: Unused local variable.
		$submethod = $this->donationData['payment_submethod'];

		$gatewayObj = $this->getGatewayObject();

		if ( !$gatewayObj ) {
			return; // already failed with a dieUsage call
		}

		$gatewayObj->revalidate();
		if ( !$gatewayObj->validatedOK() ) {
			$outputResult['errors'] = $gatewayObj->getAllErrors();
			$this->getResult()->setIndexedTagName( $outputResult['errors'], 'error' );
			$this->getResult()->addValue( null, 'result', $outputResult );
			return;
		}

		if ( $this->gateway == 'globalcollect' ) {
			switch ( $method ) {
				// TODO: add other payment methods
				case 'cc':
					$result = $gatewayObj->do_transaction( 'INSERT_ORDERWITHPAYMENT' );
					break;
				default:
					$result = $gatewayObj->do_transaction( 'TEST_CONNECTION' );
			}
		} elseif ( $this->gateway == 'adyen' ) {
			$result = $gatewayObj->do_transaction( 'donate' );
		}

		// $normalizedData = $gatewayObj->getData_Unstaged_Escaped();
		$outputResult = array();
		if ( $result->getMessage() !== null ) {
			$outputResult['message'] = $result->getMessage();
		}
		if ( $result->getCommunicationStatus() !== null ) {
			$outputResult['status'] = $result->getCommunicationStatus();
		}

		$data = $result->getData();
		if ( !empty( $data ) ) {
			if ( array_key_exists( 'PAYMENT', $data )
				&& array_key_exists( 'RETURNURL', $data['PAYMENT'] )
			) {
				$outputResult['returnurl'] = $data['PAYMENT']['RETURNURL'];
			}
			if ( array_key_exists( 'FORMACTION', $data ) ) {
				$outputResult['formaction'] = $data['FORMACTION'];
			}
			if ( array_key_exists( 'gateway_params', $data ) ) {
				$outputResult['gateway_params'] = $data['gateway_params'];
			}
			if ( $gatewayObj->getMerchantID() === 'test' ) {
				$outputResult['testform'] = true;
			}
			if ( array_key_exists( 'RESPMSG', $data ) ) {
				$outputResult['responsemsg'] = $data['RESPMSG'];
			}
			if ( array_key_exists( 'ORDERID', $data ) ) {
				$outputResult['orderid'] = $data['ORDERID'];
			}
		}
		$errors = $result->getErrors();
		if ( !empty( $errors ) ) {
			$simplify = function( $error ) {
				return $error['message'];
			};
			// TODO:objectify errors, decide here whether to include debug info
			$outputResult['errors'] = array_map( $simplify, $errors );
			$this->getResult()->setIndexedTagName( $outputResult['errors'], 'error' );
		}

		if ( $this->donationData ) {
			$this->getResult()->addValue( null, 'request', $this->donationData );
		}
		$this->getResult()->addValue( null, 'result', $outputResult );
	}

	public function isReadMode() {
		return false;
	}

	public function getAllowedParams() {
		return array(
			'gateway' => $this->defineParam( true ),
			'amount' => $this->defineParam( false ),
			'currency_code' => $this->defineParam( false ),
			'fname' => $this->defineParam( false ),
			'lname' => $this->defineParam( false ),
			'street' => $this->defineParam( false ),
			'street_supplemental' => $this->defineParam( false ),
			'city' => $this->defineParam( false ),
			'state' => $this->defineParam( false ),
			'zip' => $this->defineParam( false ),
			'email' => $this->defineParam( false ),
			'country' => $this->defineParam( false ),
			'card_num' => $this->defineParam( false ),
			'card_type' => $this->defineParam( false ),
			'expiration' => $this->defineParam( false ),
			'cvv' => $this->defineParam( false ),
			'payment_method' => $this->defineParam( false ),
			'payment_submethod' => $this->defineParam( false ),
			'language' => $this->defineParam( false ),
			'order_id' => $this->defineParam( false ),
			'contribution_tracking_id' => $this->defineParam( false ),
			'utm_source' => $this->defineParam( false ),
			'utm_campaign' => $this->defineParam( false ),
			'utm_medium' => $this->defineParam( false ),
			'referrer' => $this->defineParam( false ),
			'recurring' => $this->defineParam( false ),
		);
	}

	private function defineParam( $required = false, $type = 'string' ) {
		if ( $required ) {
			$param = array( ApiBase::PARAM_TYPE => $type, ApiBase::PARAM_REQUIRED => true );
		} else {
			$param = array( ApiBase::PARAM_TYPE => $type );
		}
		return $param;
	}

	/**
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getParamDescription() {
		return array(
			'gateway' => 'Which payment gateway to use - adyen, globalcollect, etc.',
			'amount' => 'The amount donated',
			'currency_code' => 'Currency code',
			'fname' => 'First name',
			'lname' => 'Last name',
			'street' => 'First line of street address',
			'street_supplemental' => 'Second line of street address',
			'city' => 'City',
			'state' => 'State abbreviation',
			'zip' => 'Postal code',
			'email' => 'Email address',
			'country' => 'Country code',
			'card_num' => 'Credit card number',
			'card_type' => 'Credit card type',
			'expiration' => 'Expiration date',
			'cvv' => 'CVV security code',
			'payment_method' => 'Payment method to use',
			'payment_submethod' => 'Payment submethod to use',
			'language' => 'Language code',
			'order_id' => 'Order ID (if a donation has already been started)',
			'contribution_tracking_id' => 'ID for contribution tracking table',
			'utm_source' => 'Tracking variable',
			'utm_campaign' => 'Tracking variable',
			'utm_medium' => 'Tracking variable',
			'referrer' => 'Original referrer',
			'recurring' => 'Optional - indicates that the transaction is meant to be recurring.',
		);
	}

	/**
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getDescription() {
		return array(
			'This API allow you to submit a donation to the Wikimedia Foundation using a',
			'variety of payment processors.',
		);
	}

	/**
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getExamples() {
		return array(
			'api.php?action=donate&gateway=globalcollect&amount=2.00&currency_code=USD',
		);
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 */
	protected function getExamplesMessages() {
		return array(
			'action=donate&gateway=globalcollect&amount=2.00&currency_code=USD'
				=> 'apihelp-donate-example-1',
		);
	}

	private function getGatewayObject() {
		global $wgDonationInterfaceTestMode;

		$gateway_opts = array(
			'api_request' => 'true'
		);

		if ( $this->gateway == 'globalcollect' ) {
			// FIXME: no test code path in prod
			if ( $wgDonationInterfaceTestMode === true ) {
				return new TestingGlobalCollectAdapter( $gateway_opts );
			} else {
				return new GlobalCollectAdapter( $gateway_opts );
			}
		} elseif ( $this->gateway == 'adyen' ) {
			return new AdyenAdapter( $gateway_opts );
		} else {
			$this->dieUsage( "Invalid gateway <<<{$this->gateway}>>> passed to Donation API.", 'unknown_gateway' );
		}
		return null;
	}
}
