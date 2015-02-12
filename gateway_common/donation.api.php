<?php
/**
 * Generic Donation API
 * This API should be able to accept donation submissions for any gateway or payment type
 * Call with api.php?action=donate
 */
class DonationApi extends ApiBase {
	public $donationData, $gateway;
	public function execute() {
		global $wgDonationInterfaceTestMode;

		$this->donationData = $this->extractRequestParams();

		$this->gateway = $this->donationData['gateway'];

		$method = $this->donationData['payment_method'];
		// @todo FIXME: Unused local variable.
		$submethod = $this->donationData['payment_submethod'];

		$gateway_opts = array (
			'api_request' => 'true'
		);

		if ( $this->gateway == 'globalcollect' ) {
			if ( $wgDonationInterfaceTestMode === true ) {
				$gatewayObj = new TestingGlobalCollectAdapter( $gateway_opts );
			} else {
				$gatewayObj = new GlobalCollectAdapter( $gateway_opts );
			}
			switch ( $method ) {
				// TODO: add other payment methods
				case 'cc':
					$result = $gatewayObj->do_transaction( 'INSERT_ORDERWITHPAYMENT' );
					break;
				default:
					$result = $gatewayObj->do_transaction( 'TEST_CONNECTION' );
			}
		} elseif ( $this->gateway == 'adyen' ) {
			$gatewayObj = new AdyenAdapter( $gateway_opts );
			$result = $gatewayObj->do_transaction( 'donate' );
		} else {
			$this->dieUsage( "Invalid gateway <<<{$this->gateway}>>> passed to Donation API.", 'unknown_gateway' );
		}

		//$normalizedData = $gatewayObj->getData_Unstaged_Escaped();
		$outputResult = array();
		if ( array_key_exists( 'message', $result ) ) {
			$outputResult['message'] = $result['message'];
		}
		if ( array_key_exists( 'status', $result ) ) {
			$outputResult['status'] = $result['status'];
		}

		if ( array_key_exists( 'data', $result ) ) {
			if ( array_key_exists( 'PAYMENT', $result['data'] )
				&& array_key_exists( 'RETURNURL', $result['data']['PAYMENT'] ) )
			{
				$outputResult['returnurl'] = $result['data']['PAYMENT']['RETURNURL'];
			}
			if ( array_key_exists( 'FORMACTION', $result['data'] ) ) {
				$outputResult['formaction'] = $result['data']['FORMACTION'];
			}
			if ( $gatewayObj->getMerchantID() === 'test' ) {
				$outputResult['testform'] = true;
			}
			if ( array_key_exists( 'RESPMSG', $result['data'] ) ) {
				$outputResult['responsemsg'] = $result['data']['RESPMSG'];
			}
			if ( array_key_exists( 'ORDERID', $result['data'] ) ) {
				$outputResult['orderid'] = $result['data']['ORDERID'];
			}
		}
		if ( array_key_exists( 'errors', $result ) && $result['errors'] ) {
			$outputResult['errors'] = $result['errors'];
			$this->getResult()->setIndexedTagName( $outputResult['errors'], 'error' );
		}

		if ( $this->donationData ) {
			$this->getResult()->addValue( null, 'request', $this->donationData );
		}
		if ( array_key_exists( 'gateway_params', $result ) ) {
			$this->getResult()->addValue( null, 'gateway_params', $result['gateway_params'] );
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
			'emailAdd' => $this->defineParam( false ),
			'country' => $this->defineParam( false ),
			'card_num' => $this->defineParam( false  ),
			'card_type' => $this->defineParam( false  ),
			'expiration' => $this->defineParam( false  ),
			'cvv' => $this->defineParam( false  ),
			'payment_method' => $this->defineParam( false  ),
			'payment_submethod' => $this->defineParam( false  ),
			'language' => $this->defineParam( false  ),
			'order_id' => $this->defineParam( false  ),
			'contribution_tracking_id' => $this->defineParam( false  ),
			'utm_source' => $this->defineParam( false  ),
			'utm_campaign' => $this->defineParam( false  ),
			'utm_medium' => $this->defineParam( false  ),
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
			'emailAdd' => 'Email address',
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
}
