<?php
/**
 * Wikimedia Foundation
 *
 * LICENSE
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 */
use Psr\Log\LogLevel;
use SmashPig\Core\PaymentError;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\ValidationAction;
use SmashPig\PaymentProviders\PaymentProviderFactory;

/**
 * GlobalCollectAdapter
 *
 */
class GlobalCollectAdapter extends GatewayAdapter {
	const GATEWAY_NAME = 'Global Collect';
	const IDENTIFIER = 'globalcollect';
	const GLOBAL_PREFIX = 'wgGlobalCollectGateway';

	public function getCommunicationType() {
		return 'xml';
	}

	/**
	 * Add a key to the transaction INSERT_ORDERWITHPAYMENT.
	 *
	 * $this->transactions['INSERT_ORDERWITHPAYMENT']['request']['REQUEST']['PARAMS'][$section][] = $value
	 * @param string $value the default value to add to the structure
	 * @param string $section the key name
	 */
	protected function addKeyToTransaction( $value, $section = 'PAYMENT' ) {
		if ( !in_array( $value, $this->transactions['INSERT_ORDERWITHPAYMENT']['request']['REQUEST']['PARAMS'][$section] ) ) {
			$this->transactions['INSERT_ORDERWITHPAYMENT']['request']['REQUEST']['PARAMS'][$section][] = $value;
		}
	}

	/**
	 * Define accountInfo
	 */
	public function defineAccountInfo() {
		$this->accountInfo = [
			'MERCHANTID' => $this->account_config[ 'MerchantID' ] ?? '',
			// 'IPADDRESS' => '', //TODO: Not sure if this should be OUR ip, or the user's ip. Hurm.
			'VERSION' => "1.0",
		];
	}

	/**
	 * Setting some GC-specific defaults.
	 * @param array $options These get extracted in the parent.
	 */
	protected function setGatewayDefaults( $options = [] ) {
		if ( isset( $options['returnTo'] ) ) {
			$returnTo = $options['returnTo'];
		} else {
			if ( isset( $options['returnTitle'] ) ) {
				$returnTitle = $options['returnTitle'];
			} else {
				$returnTitle = Title::newFromText( 'Special:GlobalCollectGatewayResult' );
			}
			$returnTo = $returnTitle->getFullURL( false, false, PROTO_CURRENT );
		}

		$defaults = [
			'returnto' => $returnTo,
			'attempt_id' => '1',
			'effort_id' => '1',
		];

		$this->addRequestData( $defaults );
	}

	/**
	 * Define return_value_map
	 */
	public function defineReturnValueMap() {
		$this->return_value_map = [
			'OK' => true,
			'NOK' => false,
		];
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', FinalStatus::PENDING, 0, 10 );
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', FinalStatus::FAILED, 15 ); // Refund failed
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', FinalStatus::PENDING, 20, 70 );
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', FinalStatus::FAILED, 100, 180 );
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', FinalStatus::PENDING_POKE, 200 ); // The cardholder was successfully authenticated... but we have to DO_FINISHPAYMENT
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', FinalStatus::FAILED, 220, 280 );
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', FinalStatus::PENDING, 300 );
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', FinalStatus::FAILED, 310, 350 );
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', FinalStatus::REVISED, 400 );
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', FinalStatus::PENDING_POKE, 525 ); // "The payment was challenged by your Fraud Ruleset and is pending" - we never see this.
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', FinalStatus::PENDING, 550 );
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', FinalStatus::PENDING_POKE, 600 ); // Payments sit here until we SET_PAYMENT
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', FinalStatus::PENDING, 625, 650 );
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', FinalStatus::COMPLETE, 800, 975 ); // these are all post-authorized, but technically pre-settled...
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', FinalStatus::COMPLETE, 1000, 1050 );
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', FinalStatus::FAILED, 1100, 1520 );
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', FinalStatus::REFUNDED, 1800 );
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', FinalStatus::FAILED, 1810, 2220 );
		// FIXME: not sure what this comment is about:
		// 102020 - ACTION 130 IS NOT ALLOWED FOR MERCHANT NNN, IPADDRESS NNN.NNN.NNN.NNN
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', FinalStatus::CANCELLED, 99999 );

		$this->defineGoToThankYouOn();
	}

	/**
	 * Sets up the $order_id_meta array.
	 * Should contain the following keys/values:
	 * 'alt_locations' => [ $dataset_name, $dataset_key ] //ordered
	 * 'type' => numeric, or alphanumeric
	 * 'length' => $max_charlen
	 */
	public function defineOrderIDMeta() {
		$this->order_id_meta = [
			'alt_locations' => [ 'request' => 'order_id' ],
			'generate' => true, // freaking FINALLY.
			'disallow_decimals' => true, // hacky hack hack...
		];
	}

	/**
	 * Define goToThankYouOn
	 *
	 * The statuses defined in @see GatewayAdapter::$goToThankYouOn will
	 * allow a completed form to go to the Thank you page.
	 *
	 * Allowed:
	 * - complete
	 * - pending
	 * - pending-poke
	 * - revised
	 *
	 * Denied:
	 * - failed
	 * - Any thing else not defined @see FinalStatus
	 *
	 */
	public function defineGoToThankYouOn() {
		$this->goToThankYouOn = [
			FinalStatus::COMPLETE,
			FinalStatus::PENDING,
			FinalStatus::PENDING_POKE,
			FinalStatus::REVISED,
		];
	}

	/**
	 * Define transactions
	 *
	 * Please do not add more transactions to this array.
	 *
	 * @todo
	 * - Does  need IPADDRESS? What about the other transactions. Is this the user's IPA?
	 * - Does DO_BANKVALIDATION need HOSTEDINDICATOR?
	 *
	 * This method should define:
	 * - DO_BANKVALIDATION: used prior to INSERT_ORDERWITHPAYMENT for direct debit
	 * - INSERT_ORDERWITHPAYMENT: used for payments
	 * - TEST_CONNECTION: testing connections - is this still valid?
	 * - GET_ORDERSTATUS
	 */
	public function defineTransactions() {
		$this->transactions = [];

		$this->transactions['DO_BANKVALIDATION'] = [
			'request' => [
				'REQUEST' => [
					'ACTION',
					'META' => [
						'MERCHANTID',
						'IPADDRESS',
						'VERSION'
					],
					'PARAMS' => [
						'GENERAL' => [
							'ACCOUNTNAME',
							'ACCOUNTNUMBER',
							'AUTHORISATIONID',
							'BANKCHECKDIGIT',
							'BANKCODE',
							'BANKNAME',
							'BRANCHCODE',
							'COUNTRYCODEBANK',
							'DATECOLLECT', // YYYYMMDD
							'DIRECTDEBITTEXT',
							'IBAN',
							'MERCHANTREFERENCE',
							'TRANSACTIONTYPE',
						],
					]
				]
			],
			'values' => [
				'ACTION' => 'DO_BANKVALIDATION',
			],
		];

		$this->transactions['GET_DIRECTORY'] = [
			'request' => [
				'REQUEST' => [
					'ACTION',
					'META' => [
						'MERCHANTID',
						'IPADDRESS',
						'VERSION',
					],
					'PARAMS' => [
						'GENERAL' => [
							'PAYMENTPRODUCTID',
							'COUNTRYCODE',
							'CURRENCYCODE',
						],
					],
				],
			],
			'values' => [
				'ACTION' => 'GET_DIRECTORY',
			],
		];

		$this->transactions['INSERT_ORDERWITHPAYMENT'] = [
			'request' => [
				'REQUEST' => [
					'ACTION',
					'META' => [
						'MERCHANTID',
						'IPADDRESS',
						'VERSION'
					],
					'PARAMS' => [
						'ORDER' => [
							'ORDERID',
							'AMOUNT',
							'CURRENCYCODE',
							'LANGUAGECODE',
							'COUNTRYCODE',
							'MERCHANTREFERENCE',
							'IPADDRESSCUSTOMER',
							'EMAIL',
						],
						'PAYMENT' => [
							'PAYMENTPRODUCTID',
							'AMOUNT',
							'CURRENCYCODE',
							'LANGUAGECODE',
							'COUNTRYCODE',
							'HOSTEDINDICATOR',
							'RETURNURL',
// 'CVV',
// 'EXPIRYDATE',
// 'CREDITCARDNUMBER',
							'AUTHENTICATIONINDICATOR',
							'FIRSTNAME',
							'SURNAME',
							'STREET',
							'CITY',
							'STATE',
							'ZIP',
							'EMAIL',
						]
					]
				]
			],
			'values' => [
				'ACTION' => 'INSERT_ORDERWITHPAYMENT',
				'HOSTEDINDICATOR' => '1',
				'AUTHENTICATIONINDICATOR' => 0, // default to no 3DSecure ourselves
			],
			'check_required' => true,
		];

		$this->transactions['DO_REFUND'] = [
			'request' => [
				'REQUEST' => [
					'ACTION',
					'META' => [
						'MERCHANTID',
						'IPADDRESS',
						'VERSION'
					],
					'PARAMS' => [
						'PAYMENT' => [
							'PAYMENTPRODUCTID',
							'ORDERID',
							'EFFORTID',
							'MERCHANTREFERENCE',
							'AMOUNT',
							'CURRENCYCODE',
						]
					]
				]
			],
			'values' => [
				'ACTION' => 'DO_REFUND',
				'VERSION' => '1.0',
			],
		];

		$this->transactions['SET_REFUND'] = [
			'request' => [
				'REQUEST' => [
					'ACTION',
					'META' => [
						'MERCHANTID',
						'IPADDRESS',
						'VERSION'
					],
					'PARAMS' => [
						'PAYMENT' => [
							'PAYMENTPRODUCTID',
							'ORDERID',
							'EFFORTID',
						]
					]
				]
			],
			'values' => [
				'ACTION' => 'SET_REFUND',
				'VERSION' => '1.0',
			],
		];

		$this->transactions['TEST_CONNECTION'] = [
			'request' => [
				'REQUEST' => [
					'ACTION',
					'META' => [
						'MERCHANTID',
							'IPADDRESS',
						'VERSION'
					],
					'PARAMS' => []
				]
			],
			'values' => [
				'ACTION' => 'TEST_CONNECTION'
			]
		];

		$this->transactions['GET_ORDERSTATUS'] = [
			'request' => [
				'REQUEST' => [
					'ACTION',
					'META' => [
						'MERCHANTID',
						'IPADDRESS',
						'VERSION'
					],
					'PARAMS' => [
						'ORDER' => [
							'ORDERID',
							'EFFORTID',
						],
					]
				]
			],
			'values' => [
				'ACTION' => 'GET_ORDERSTATUS',
				'VERSION' => '2.0'
			],
			'response' => [
				'EFFORTID',
				'ATTEMPTID',
				'CURRENCYCODE',
				'AMOUNT',
				'AVSRESULT',
				'CVVRESULT',
				'STATUSID',
			],
		];

		$this->transactions['CANCEL_PAYMENT'] = [
			'request' => [
				'REQUEST' => [
					'ACTION',
					'META' => [
						'MERCHANTID',
						'IPADDRESS',
						'VERSION'
					],
					'PARAMS' => [
						'PAYMENT' => [
							'ORDERID',
							'EFFORTID',
							'ATTEMPTID',
						],
					]
				]
			],
			'values' => [
				'ACTION' => 'CANCEL_PAYMENT',
				'VERSION' => '1.0'
			],
		];

		$this->transactions['SET_PAYMENT'] = [
			'request' => [
				'REQUEST' => [
					'ACTION',
					'META' => [
						'MERCHANTID',
						'IPADDRESS',
						'VERSION'
					],
					'PARAMS' => [
						'PAYMENT' => [
							'ORDERID',
							'EFFORTID',
							'PAYMENTPRODUCTID',
						],
					]
				]
			],
			'values' => [
				'ACTION' => 'SET_PAYMENT',
				'VERSION' => '1.0'
			],
		];

		$this->transactions['DO_FINISHPAYMENT'] = [
			'request' => [
				'REQUEST' => [
					'ACTION',
					'META' => [
						'MERCHANTID',
						'IPADDRESS',
						'VERSION'
					],
					'PARAMS' => [
						'PAYMENT' => [
							'ORDERID',
							'EFFORTID',
							'ATTEMPTID',
						],
					]
				]
			],
			'values' => [
				'ACTION' => 'DO_FINISHPAYMENT',
				'VERSION' => '1.0',
			],
		];

		$this->transactions['DO_PAYMENT'] = [
			'request' => [
				'REQUEST' => [
					'ACTION',
					'META' => [
						'MERCHANTID',
						'IPADDRESS',
						'VERSION',
					],
					'PARAMS' => [
						'PAYMENT' => [
							'MERCHANTREFERENCE',
							'ORDERID',
							'EFFORTID',
							'PAYMENTPRODUCTID',
							'AMOUNT',
							'CURRENCYCODE',
							'HOSTEDINDICATOR',
							'AUTHENTICATIONINDICATOR',
						],
					]
				]
			],
			'values' => [
				'ACTION' => 'DO_PAYMENT',
				'VERSION' => '1.0',
				'HOSTEDINDICATOR' => '0',
				'AUTHENTICATIONINDICATOR' => '0',
			],
		];

		// Cancel a recurring transaction if all payment attempts can be canceled
		$this->transactions['CANCEL_ORDER'] = [
			'request' => [
				'REQUEST' => [
					'ACTION',
					'META' => [
						'MERCHANTID',
						'IPADDRESS',
						'VERSION',
					],
					'PARAMS' => [
						'ORDER' => [
							'ORDERID',
						],
					]
				]
			],
			'values' => [
				'ACTION' => 'CANCEL_ORDER',
				'VERSION' => '1.0',
			],
		];

		// End a recurring transaction, disallowing further payment attempts
		$this->transactions['END_ORDER'] = [
			'request' => [
				'REQUEST' => [
					'ACTION',
					'META' => [
						'MERCHANTID',
						'IPADDRESS',
						'VERSION',
					],
					'PARAMS' => [
						'ORDER' => [
							'ORDERID',
						],
					]
				]
			],
			'values' => [
				'ACTION' => 'END_ORDER',
				'VERSION' => '1.0',
			],
		];

		// Convert an old-style recurring payment to a profile for Connect
		$this->transactions['CONVERT_PAYMENTTOPROFILE'] = [
			'request' => [
				'REQUEST' => [
					'ACTION',
					'META' => [
						'MERCHANTID',
						'IPADDRESS',
						'VERSION',
					],
					'PARAMS' => [
						'PAYMENT' => [
							'ORDERID',
						],
					]
				]
			],
			'response' => [
				'PROFILETOKEN',
			],
			'values' => [
				'ACTION' => 'CONVERT_PAYMENTTOPROFILE',
				'VERSION' => '2.0',
			],
		];
	}

	public function doPayment() {
		$payment_method = $this->getPaymentMethod();

		// FIXME: this should happen during normalization, and before validatation.
		if ( $payment_method === 'dd'
				&& !$this->getPaymentSubmethod() ) {
			// Synthesize a submethod based on the country.
			$country_code = strtolower( $this->getData_Unstaged_Escaped( 'country' ) );
			$this->addRequestData( [
				'payment_submethod' => "dd_{$country_code}",
			] );
		}

		// Execute the proper transaction code:
		switch ( $payment_method ) {
			case 'cc':
				$this->do_transaction( 'INSERT_ORDERWITHPAYMENT' );
				$checkoutUrl = $this->getTransactionDataFormAction();

				if ( $checkoutUrl ) {
					if ( $this->getData_Staged( 'use_authentication' ) ) {
						// 3D Secure is on, redirect the whole page so the donor
						// can type in their bank verification code.
						return PaymentResult::newRedirect( $checkoutUrl );
					} else {
						// Display an iframe for credit card entry
						return PaymentResult::newIframe( $checkoutUrl );
					}
				}
				break;

			case 'dd':
				$this->do_transaction( 'Direct_Debit' );
				break;

			case 'ew':
			case 'rtbt':
			case 'cash':
				$this->do_transaction( 'INSERT_ORDERWITHPAYMENT' );
				$formAction = $this->getTransactionDataFormAction();

				// Redirect to the bank
				if ( $formAction ) {
					return PaymentResult::newRedirect( $formAction );
				}
				break;

			default:
				$this->do_transaction( 'INSERT_ORDERWITHPAYMENT' );
		}

		return PaymentResult::fromResults(
			$this->transaction_response,
			$this->getFinalStatus()
		);
	}

	/**
	 * Because GC has some processes that involve more than one do_transaction
	 * chained together, we're catching those special ones in an overload and
	 * letting the rest behave normally.
	 * @inheritDoc
	 */
	public function do_transaction( $transaction ) {
		$this->session_addDonorData();
		switch ( $transaction ) {
			case 'Confirm_CreditCard':
				$this->profiler->getStopwatch( 'Confirm_CreditCard', true );
				$result = $this->transactionConfirm_CreditCard();
				$this->profiler->saveCommunicationStats( 'Confirm_CreditCard', $transaction );
				return $result;
			case 'Direct_Debit':
				$this->profiler->getStopwatch( 'Direct_Debit', true );
				$result = $this->transactionDirect_Debit();
				$this->profiler->saveCommunicationStats( 'Direct_Debit', $transaction );
				return $result;
			case 'Recurring_Charge':
				return $this->transactionRecurring_Charge();
			default:
				return parent::do_transaction( $transaction );
		}
	}

	/**
	 * Either confirm or reject the payment
	 *
	 * FIXME: This function is way too complex.  Unroll into new functions.
	 *
	 * @return PaymentTransactionResponse
	 */
	private function transactionConfirm_CreditCard() {
		$is_orphan = $this->isBatchProcessor();
		if ( $is_orphan ) {
			// We're in orphan processing mode, so a "pending waiting for donor
			// input" status means that we'll never complete.  Set this range
			// to map to "failed".
			$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', FinalStatus::FAILED, 0, 70 );
		}

		// problem['flag'] will get set to true if we can't continue and need to give up and just log the hell out of it.
		$problem = [
			'flag' => false,
			'message' => '', // to be used in conjunction with the flag 'problem'.
			'severity' => LogLevel::ERROR, // to route the message to the appropriate log. Urf.
			'errors' => []
		];
		// FIXME: This feels like it's moving towards an object, but I'm not sure it's worth creating one for such a small use case

		$status_result = $this->getOrderStatusFromProcessor();
		$validationAction = $this->getValidationAction();
		$cvv_result = $this->getData_Unstaged_Escaped( 'cvv_result' );
		$gotCVV = strlen( $cvv_result ) > 0;
		// TODO: This logging is redundant with the response from GET_ORDERSTATUS.
		$logmsg = 'CVV Result: ' . $this->getData_Unstaged_Escaped( 'cvv_result' );
		$logmsg .= ', AVS Result: ' . $this->getData_Unstaged_Escaped( 'avs_result' );
		$this->logger->info( $logmsg );

		// reason to cancel?
		// FIXME: "isForceCancel"?
		if ( $status_result->getForceCancel() || $validationAction !== ValidationAction::PROCESS ) {
			$problem = $this->cancelCreditCardPayment(); // don't retry: We've fraud-failed them intentionally.
		} elseif ( $status_result->getCommunicationStatus() === false ) {
		// can't communicate or internal error
			$problem['flag'] = true;
			$problem['message'] = "Can't communicate or internal error: "
				. $status_result->getMessage();
		}

		if ( $problem['flag'] ) {
			return $this->handleCreditCardProblem( $problem );
		}

		$order_status_results = false;
		$statusCode = $this->getStatusCode( $this->getTransactionData() );
		if ( $statusCode ) {
			$order_status_results = $this->findCodeAction( 'GET_ORDERSTATUS', 'STATUSID', $statusCode );
			if ( $is_orphan ) {
				// save stats.
				if ( !isset( $this->orphanstats[$statusCode] ) ) {
					$this->orphanstats[$statusCode] = 1;
				} else {
					$this->orphanstats[$statusCode] += 1;
				}
			}
		}
		switch ( $order_status_results ) {
			case null:
			case false:
				$problem['flag'] = true;
				$problem['message'] = "We don't have an order status after doing a GET_ORDERSTATUS.";
				break;
			case FinalStatus::FAILED:
			case FinalStatus::REVISED:
			case FinalStatus::CANCELLED:
				$problem = $this->cancelCreditCardPayment( $order_status_results );
				break;
			case FinalStatus::COMPLETE:
				$problem['flag'] = true; // nothing to be done.
				$problem['message'] = "GET_ORDERSTATUS reports that the payment is already complete.";
				$problem['severity'] = LogLevel::INFO;
				break;
			case FinalStatus::PENDING:
				// If it's really pending at this point, we need to
				// leave it alone.
				// FIXME: If we're orphan slaying, this should stay in
				// the queue, but we currently delete it. <--I'm not sure that's true now
				break;
			case FinalStatus::PENDING_POKE:
				if ( $is_orphan && !$gotCVV ) {
					$problem['flag'] = true;
					$problem['message'] = "Unable to retrieve orphan cvv/avs results (Communication problem?).";
					break;
				}
				// FIXME: should we flag anything here?
				// removed 'DO_FINISHPAYMENT' for status '200' because it was no longer working or applicable
			// else fall through
			default:
				$result = $this->finalizeCreditCardPayment( $statusCode );
				if ( !$result ) {
					$problem['flag'] = true;
					$problem['message'] = "SET_PAYMENT couldn't communicate properly!";
				}
		}
		// FIXME: not loving the repetition here...
		if ( $problem['flag'] ) {
			return $this->handleCreditCardProblem( $problem );
		}
		// return something better... if we need to!
		return $status_result;
	}

	protected function finalizeCreditCardPayment( $statusCode ) {
		$final = $this->approvePayment();
		if ( $final->getCommunicationStatus() === true ) {
			$this->finalizeInternalStatus( FinalStatus::COMPLETE );
			// get the old status from the first txn, and add in the part where we set the payment.
			$this->transaction_response->setTxnMessage( "Original Response Status (pre-SET_PAYMENT): " . $statusCode );
			$this->postProcessDonation();  // Queueing is in here.
			return true;
		} else {
			$this->finalizeInternalStatus( FinalStatus::FAILED );
			return false;
		}
	}

	protected function cancelCreditCardPayment( $status = FinalStatus::FAILED ) {
		$this->finalizeInternalStatus( $status );
		return [ 'flag' => 'true', 'message' => 'Cancelling payment', 'severity' => LogLevel::INFO, 'errors' => [ '1000001' => 'Cancelling payment' ] ];
	}

	protected function handleCreditCardProblem( $problem ) {
		if ( !count( $problem['errors'] ) ) {
			$problem['errors'] = [ '1000000' => 'Transaction could not be processed due to an internal error.' ];
		}
		// we have probably had a communication problem that could mean stranded payments.
		$this->logger->log( $problem['severity'], $problem['message'] );
		// hurm. It would be swell if we had a message that told the user we had some kind of internal error.
		$ret = new PaymentTransactionResponse();
		$ret->setCommunicationStatus( false );
		// DO NOT PREPEND $problem['message'] WITH ANYTHING!
		// orphans.php is looking for specific things in position 0.
		$ret->setMessage( $problem['message'] );
		foreach ( $problem['errors'] as $code => $error ) {
			$ret->addError( new PaymentError(
				$code,
				'Failure in transactionConfirm_CreditCard',
				$problem['severity']
			) );
		}
		// TODO: should we set $this->transaction_response ?
		return $ret;
	}

	protected function getOrderStatusFromProcessor() {
		return $this->do_transaction( 'GET_ORDERSTATUS' );
	}

	/**
	 * Process a non-initial effort_id charge.
	 *
	 * Finalizes the transaction according to the outcome.
	 *
	 * @return PaymentTransactionResponse Last API response we received, in
	 * case the caller wants to try to extract information.
	 */
	protected function transactionRecurring_Charge() {
		$do_payment_response = $this->do_transaction( 'DO_PAYMENT' );
		// Ignore possible NOK, we might be resuming an incomplete charge in which
		// case DO_PAYMENT is expected to fail.  There's no status code returned
		// from this call, in that case.

		// So get the status and see what we've accomplished so far.
		$get_orderstatus_response = $this->do_transaction( 'GET_ORDERSTATUS' );
		$data = $this->getTransactionData();

		// If can't even get the status, fail.
		if ( !$get_orderstatus_response->getCommunicationStatus() ) {
			$this->finalizeInternalStatus( FinalStatus::FAILED );
			return $get_orderstatus_response;
		}

		// Test that we're in status 600 now, and fail if not.
		if ( !isset( $data['STATUSID'] )
			|| $this->findCodeAction( 'GET_ORDERSTATUS', 'STATUSID', $data['STATUSID'] ) !== FinalStatus::PENDING_POKE
		) {
			// FIXME: It could actually be in a pending state at this point,
			// I wish we could express that uncertainty.
			$this->finalizeInternalStatus( FinalStatus::FAILED );
			return $get_orderstatus_response;
		}

		// Settle.
		$this->transactions['SET_PAYMENT']['values']['PAYMENTPRODUCTID'] = $data['PAYMENTPRODUCTID'];
		$set_payment_response = $this->do_transaction( 'SET_PAYMENT' );

		// Finalize the transaction as complete or failed.
		if ( $set_payment_response->getCommunicationStatus() ) {
			$this->finalizeInternalStatus( FinalStatus::COMPLETE );
		} else {
			$this->finalizeInternalStatus( FinalStatus::FAILED );
		}

		return $set_payment_response;
	}

	protected function transactionDirect_Debit() {
		$result = $this->do_transaction( 'DO_BANKVALIDATION' );
		if ( $result->getCommunicationStatus() ) {
			$this->transactions['INSERT_ORDERWITHPAYMENT']['values']['HOSTEDINDICATOR'] = 0;
			$result = $this->do_transaction( 'INSERT_ORDERWITHPAYMENT' );
			if ( $result->getCommunicationStatus() === true ) {
				if ( $this->getFinalStatus() === FinalStatus::PENDING_POKE ) {
					$txn_data = $this->getTransactionData();
					$original_status_code = $txn_data['STATUSID'] ?? 'NOT SET';

					$result = $this->do_transaction( 'SET_PAYMENT' );
					if ( $result->getCommunicationStatus() === true ) {
						$this->finalizeInternalStatus( FinalStatus::COMPLETE );
					} else {
						$this->finalizeInternalStatus( FinalStatus::FAILED );
						// get the old status from the first txn, and add in the part where we set the payment.
						$this->transaction_response->setTxnMessage( "Original Response Status (pre-SET_PAYMENT): " . $original_status_code );
					}
				}
			}
		}
		return $result;
	}

	/**
	 * Refunds a transaction.  Assumes that we're running in batch mode with
	 * payment_method = cc, and that all of these have been set:
	 * order_id, effort_id, country, currency, amount, and payment_submethod
	 * Also requires merchant_reference to be set to the reference from the
	 * original transaction.  FIXME: store that some place besides the logs
	 * @return PaymentResult
	 */
	public function doRefund() {
		$effortId = $this->getData_Unstaged_Escaped( 'effort_id' );

		// Don't want to use standard ct_id staging
		$this->var_map['MERCHANTREFERENCE'] = 'merchant_reference';

		// Try cancelling first, it's fast and cheap.
		// TODO: Look into AUTHORIZATIONREVERSALINDICATOR
		$cancel_payment_response = $this->do_transaction( 'CANCEL_PAYMENT' );

		if ( $cancel_payment_response->getCommunicationStatus() ) {
			// That's all we need!
			$this->logger->info( "Canceled payment attempt effort $effortId" );
			return PaymentResult::fromResults( $cancel_payment_response, FinalStatus::COMPLETE );
		}

		// Get the status and see what we've accomplished so far.
		$get_orderstatus_response = $this->do_transaction( 'GET_ORDERSTATUS' );
		$get_orderstatus_data = $get_orderstatus_response->getData();

		// If we can't even get the status, fail.
		if ( !$get_orderstatus_response->getCommunicationStatus()
			|| !isset( $get_orderstatus_data['STATUSID'] )
		) {
			$this->logger->warning( "Could not get status for payment attempt effort $effortId." );
			return PaymentResult::fromResults( $get_orderstatus_response, FinalStatus::FAILED );
		}

		$final_status = $this->findCodeAction( 'GET_ORDERSTATUS', 'STATUSID', $get_orderstatus_data['STATUSID'] );

		// If it's already cancelled or refunded, pat own back.
		// FIXME: I don't think the original txn goes into refunded status, just
		// the refund txn with the same order id but negated effort ID
		if ( $final_status === FinalStatus::CANCELLED
			|| $final_status === FinalStatus::REFUNDED
		) {
			$this->logger->info( "Payment attempt effort $effortId already canceled or refunded." );
			return PaymentResult::fromResults( $get_orderstatus_response, FinalStatus::COMPLETE );
		}

		// Refunding a transaction creates another "payment" against the same
		// order id, but with a negated effort id.  Check to see if a refund has
		// already been requested.
		// TODO: is it always the negative of the original payment's effort id?
		$this->transactions['GET_ORDERSTATUS']['values']['EFFORTID'] =
			-1 * intval( $effortId );

		$refund_response = $this->do_transaction( 'GET_ORDERSTATUS' );
		$refund_data = $refund_response->getData();

		// If there is no existing refund, request one
		if ( !isset( $refund_data['STATUSID'] ) ) {
			$refund_response = $this->do_transaction( 'DO_REFUND' );
			$refund_data = $refund_response->getData();
		}

		if ( !$refund_response->getCommunicationStatus()
			|| !isset( $refund_data['STATUSID'] )
		) {
			// No existing refund, and requesting a new one failed
			$this->logger->warning( "Could not request refund for payment attempt effort $effortId." );
			return PaymentResult::fromResults( $refund_response, FinalStatus::FAILED );
		}

		// We should have a refund with a status code by now.
		// TODO: should refunds have their own set of code maps?  CC state diagram
		// shows a parallel refund track where 800 means 'refund ready', 900
		// means 'refund sent', and 1800 means 'refunded'
		$refund_status = $this->findCodeAction( 'GET_ORDERSTATUS', 'STATUSID', $refund_data['STATUSID'] );

		// Done? Good!
		if ( $refund_status === FinalStatus::COMPLETE || $refund_status === FinalStatus::REFUNDED ) {
			$this->logger->info( "Refund request for payment attempt effort $effortId is complete." );
			return PaymentResult::fromResults( $refund_response, FinalStatus::COMPLETE );
		}

		// If the refund is pending, settle it
		if ( $refund_status === FinalStatus::PENDING_POKE ) {
			$this->transactions['SET_REFUND']['values']['PAYMENTPRODUCTID'] = $refund_data['PAYMENTPRODUCTID'];
			$set_refund_response = $this->do_transaction( 'SET_REFUND' );

			if ( !$set_refund_response->getCommunicationStatus() ) {
				$this->logger->warning( "Could not settle refund request for payment attempt effort $effortId." );
				return PaymentResult::fromResults( $set_refund_response, FinalStatus::FAILED );
			}

			$this->logger->info( "Settled refund request for payment attempt effort $effortId." );
			return PaymentResult::fromResults( $set_refund_response, FinalStatus::COMPLETE );
		}

		// What the heck happened?
		$this->logger->warning( "Refund request for payment attempt effort $effortId has unknown status." );
		return PaymentResult::fromResults( $refund_response, FinalStatus::FAILED );
	}

	/**
	 * Cancel a subscription
	 *
	 * Uses the adapter's internal order ID.
	 *
	 * @return PaymentResult
	 */
	public function cancelSubscription() {
		// Try to cancel, in case no payment attempts have been made or all
		// payment attempts can be canceled
		$response = $this->do_transaction( 'CANCEL_ORDER' );

		if ( !$response->getCommunicationStatus() ) {
			// If we can't cancel, end it to disallow future attempts
			$response = $this->do_transaction( 'END_ORDER' );
			if ( !$response->getCommunicationStatus() ) {
				return PaymentResult::fromResults( $response, FinalStatus::FAILED );
			}
		}

		return PaymentResult::fromResults( $response, FinalStatus::COMPLETE );
	}

	/**
	 * Parse the response to get the status. Not sure if this should return a bool, or something more... telling.
	 *
	 * @param DOMDocument $response The response XML loaded into a DOMDocument
	 * @return bool
	 */
	public function parseResponseCommunicationStatus( $response ) {
		$aok = true;

		foreach ( $response->getElementsByTagName( 'RESULT' ) as $node ) {
			if ( array_key_exists( $node->nodeValue, $this->return_value_map ) && $this->return_value_map[$node->nodeValue] !== true ) {
				$aok = false;
			}
		}

		return $aok;
	}

	/**
	 * Parse the response to get the errors in a format we can log and otherwise deal with.
	 * return a key/value array of codes (if they exist) and messages.
	 *
	 * If the site has $wgDonationInterfaceDisplayDebug = true, then the real
	 * messages will be sent to the client. Messages will not be translated or
	 * obfuscated.
	 *
	 * @param DOMDocument $response The response XML as a DOMDocument
	 * @return array
	 */
	public function parseResponseErrors( $response ) {
		$errors = [];
		foreach ( $response->getElementsByTagName( 'ERROR' ) as $node ) {
			$code = '';
			$debugInfo = '';
			foreach ( $node->childNodes as $childnode ) {
				if ( $childnode->nodeName === "CODE" ) {
					$code = $childnode->nodeValue;
				}
				if ( $childnode->nodeName === "MESSAGE" ) {
					$message = $childnode->nodeValue;
					$debugInfo = $message;
					// I am hereby done screwing around with GC field constraint violations.
					// They vary between ***and within*** payment types, and their docs are a joke.
					if ( strpos( $message, 'DOES NOT HAVE LENGTH' ) !== false ) {
						$this->logger->error( $message );
					}
				}
			}

			$errors[] = new PaymentError(
				$code,
				$debugInfo,
				LogLevel::ERROR
			);
		}
		return $errors;
	}

	/**
	 * Harvest the data we need back from the gateway.
	 * return a key/value array
	 *
	 * When we set lookup error code ranges, we use GET_ORDERSTATUS as the key for search
	 * because they are only defined for that transaction type.
	 *
	 * @param DOMDocument $response The response XML as a DOMDocument
	 * @return array
	 */
	public function parseResponseData( $response ) {
		$data = [];

		$transaction = $this->getCurrentTransaction();

		switch ( $transaction ) {
			case 'INSERT_ORDERWITHPAYMENT':
				$data = $this->xmlChildrenToArray( $response, 'ROW' );
				$data['ORDER'] = $this->xmlChildrenToArray( $response, 'ORDER' );
				$data['PAYMENT'] = $this->xmlChildrenToArray( $response, 'PAYMENT' );

				// if we have no order ID yet (or it's somehow wrong), retrieve it and put it in the usual place.
				if ( array_key_exists( 'ORDERID', $data ) && ( $data['ORDERID'] != $this->getData_Unstaged_Escaped( 'order_id' ) ) ) {
					$this->logger->info( "inside " . $data['ORDERID'] );
					$this->normalizeOrderID( $data['ORDERID'] );
					$this->logger->info( print_r( $this->getOrderIDMeta(), true ) );
					$this->addRequestData( [ 'order_id' => $data['ORDERID'] ] );
					$this->logger->info( print_r( $this->getOrderIDMeta(), true ) );
					$this->session_addDonorData();
				}

				// if we're of a type that sends donors off never to return, we should record that here.
				$payment_info = $this->getPaymentMethodMeta();
				if ( array_key_exists( 'short_circuit_at', $payment_info ) && $payment_info['short_circuit_at'] === 'first_iop' ) {
					if ( array_key_exists( 'additional_success_status', $payment_info ) && is_array( $payment_info['additional_success_status'] ) ) {
						foreach ( $payment_info['additional_success_status'] as $status ) {
							// mangle the definition of success.
							$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', FinalStatus::COMPLETE, $status );
						}
					}
					if ( $this->getTransactionStatus() ) {
						$this->finalizeInternalStatus( $this->findCodeAction( 'GET_ORDERSTATUS', 'STATUSID', $data['STATUSID'] ) );
					}
				}
				break;
			case 'DO_BANKVALIDATION':
				$data = $this->xmlChildrenToArray( $response, 'RESPONSE' );
				unset( $data['META'] );
				$data['errors'] = [];
				$data['CHECKSPERFORMED'] = $this->xmlGetChecks( $response );
				$data['VALIDATIONID'] = $this->xmlChildrenToArray( $response, 'VALIDATIONID' );

				// Final Status will already be set if the transaction was unable to communicate properly.
				if ( $this->getTransactionStatus() ) {
					$this->finalizeInternalStatus( $this->checkDoBankValidation( $data ) );
				}

				break;
			case 'GET_ORDERSTATUS':
				$data = $this->xmlChildrenToArray( $response, 'STATUS' );
				$data['ORDER'] = $this->xmlChildrenToArray( $response, 'ORDER' );
				break;
			case 'DO_FINISHPAYMENT':
			case 'DO_REFUND':
			case 'CONVERT_PAYMENTTOPROFILE':
				$data = $this->xmlChildrenToArray( $response, 'ROW' );
				break;
			case 'DO_PAYMENT':
				$data = $this->xmlChildrenToArray( $response, 'ROW' );
				if ( isset( $data['STATUSID'] ) ) {
					$this->finalizeInternalStatus( $this->findCodeAction( 'GET_ORDERSTATUS', 'STATUSID', $data['STATUSID'] ) );
				} else {
					$this->finalizeInternalStatus( FinalStatus::FAILED );
				}
				$data['ORDER'] = $this->xmlChildrenToArray( $response, 'ORDER' );
				break;
		}

		return $data;
	}

	/**
	 * Parse the response object for the checked validations
	 *
	 * @param DOMDocument $response The response object
	 * @return array
	 */
	protected function xmlGetChecks( $response ) {
		$data = [
			'CHECKS' => [],
		];

		$checks = $response->getElementsByTagName( 'CHECK' );

		foreach ( $checks as $check ) {
			// Get the check code
			$checkCode = $check->getElementsByTagName( 'CHECKCODE' )->item( 0 )->nodeValue;

			// Remove zero paddding
			$checkCode = ltrim( $checkCode, '0' );

			// Convert it too an integer
			settype( $checkCode, 'integer' );

			$data['CHECKS'][ $checkCode ] = $check->getElementsByTagName( 'CHECKRESULT' )->item( 0 )->nodeValue;
		}

		// Sort the error codes
		ksort( $data['CHECKS'] );

		return $data;
	}

	/**
	 * Interpret DO_BANKVALIDATION checks performed.
	 *
	 * This will use the error map.
	 *
	 * PASSED is a successful validation.
	 *
	 * ERROR is a validation failure.
	 *
	 * WARNING: For now, this will be ignored.
	 *
	 * NOTCHECKED does not need to be worried about in the check results. These
	 * are supposed to appear if a validation failed, rendering the other
	 * validations pointless to check.
	 *
	 * @todo
	 * - There is a problem with the manual for DO_BANKVALIDATION. Failure should return NOK. Is this only on development?
	 * - Messages are not being translated by the provider.
	 * - What do we do about WARNING? For now, it is fail?
	 * - Get the validation id
	 *
	 * @param array &$data The data array
	 *
	 * @throws UnexpectedValueException
	 * @return string One of the constants defined in @see FinalStatus
	 */
	public function checkDoBankValidation( &$data ) {
		$checks = &$data['CHECKSPERFORMED'];

		$isPass = 0;
		$isError = 0;
		$isWarning = 0;
		$isNotChecked = 0;

		if ( !is_array( $checks['CHECKS'] ) ) {
			// Should we trigger an error if no checks are performed?
			// For now, just return failed.
			return FinalStatus::FAILED;
		}

		// We only mark validation as a failure if we have warnings or errors.
		$return = FinalStatus::COMPLETE;

		foreach ( $checks['CHECKS'] as $checkCode => $checkResult ) {
			// Prefix error codes with dbv for DO_BANKVALIDATION
			$code = 'dbv-' . $checkCode;

			if ( $checkResult == 'ERROR' ) {
				$isError++;

				// Message might need to be put somewhere else.
				$data['errors'][ $code ] = $this->getErrorMap( $code );
			} elseif ( $checkResult == 'NOTCHECKED' ) {
				$isNotChecked++;
			} elseif ( $checkResult == 'PASSED' ) {
				$isPass++;

			} elseif ( $checkResult == 'WARNING' ) {
				$isWarning++;

				// Message might need to be put somewhere else.
				$data['errors'][ $code ] = $this->getErrorMap( $code );
			} else {
				$message = 'Unknown check result: (' . $checkResult . ')';

				throw new UnexpectedValueException( $message );
			}
		}

		// The return text needs to match something in @see $this->defineGoToThankYouOn()
		if ( $isPass ) {
			$return = FinalStatus::COMPLETE;
		}

		if ( $isWarning ) {
			$this->logger->error( 'Got warnings from bank validation: ' . print_r( $data['errors'], true ) );
			$return = FinalStatus::COMPLETE;
		}

		if ( $isError ) {
			$return = FinalStatus::FAILED;
		}

		return $return;
	}

	public function processDonorReturn( $requestValues ) {
		$oid = null;
		if ( array_key_exists( 'order_id', $requestValues ) ) {
			$oid = $requestValues['order_id'];
		} elseif ( array_key_exists( 'REF', $requestValues ) ) {
			$oid = $requestValues['REF'];
		}

		if ( !$oid ) {
			$this->finalizeInternalStatus( FinalStatus::FAILED );
			$this->logger->error( 'Missing Order ID' );
			return PaymentResult::newFailure();
		}

		if ( $this->getData_Unstaged_Escaped( 'payment_method' ) !== 'cc' ) {
			$this->finalizeInternalStatus( FinalStatus::FAILED );
			$this->logger->error( "Payment method is not CC, OID: {$oid}" );
			return PaymentResult::newFailure();
		}

		$session_oid = $this->session_getData( 'Donor', 'order_id' );

		if ( !$session_oid ) {
			$this->logger->info( "Missing Session Order ID for OID: {$oid}" );
			// Donor has made two payment attempts, and we have the wrong one's
			// info in session. To avoid recording the wrong details, leave the
			// attempt in PENDING status, which will show the thank you page
			// but leave the payment to be resolved by the orphan rectifier.
			// FIXME: should use finalizeInternalStatus() but there are side effects.
			$this->final_status = FinalStatus::PENDING;
			return PaymentResult::newSuccess();
		}

		if ( $oid !== $session_oid ) {
			$this->logger->info( "Order ID mismatch '{$oid}'/'{$session_oid}'" );
			// FIXME: should use finalizeInternalStatus() but there are side effects
			$this->final_status = FinalStatus::PENDING;
			return PaymentResult::newSuccess();
		}

		$response = $this->do_transaction( 'Confirm_CreditCard' );
		return PaymentResult::fromResults(
			$response,
			$this->getFinalStatus()
		);
	}

	/**
	 * Process the response and set transaction_response properties
	 *
	 * @param DOMDocument $response Cleaned-up XML from the GlobalCollect API
	 *
	 * @throws ResponseProcessingException with code and potentially retry vars.
	 */
	protected function processResponse( $response ) {
		$this->transaction_response->setCommunicationStatus(
			$this->parseResponseCommunicationStatus( $response )
		);
		$errors = $this->parseResponseErrors( $response );
		$this->transaction_response->addErrors( $errors );
		$data = $this->parseResponseData( $response );
		$this->transaction_response->setData( $data );
		// set the transaction result message
		$responseStatus = $data['STATUSID'] ?? '';
		$this->transaction_response->setTxnMessage( "Response Status: " . $responseStatus ); // TODO: Translate for GC.
		$this->addRequestData( [
			'gateway_txn_id' => $this->getGatewayTransactionId()
		] );

		$retErrCode = null;
		$retryVars = [];

		// We are also curious to know if there were any recoverable errors
		foreach ( $errors as $errObj ) {
			$errCode = $errObj->getErrorCode();
			$messageFromProcessor = $errObj->getDebugMessage();
			$retryOrderId = false;
			switch ( $errCode ) {
				case 400120: // INSERTATTEMPT PAYMENT FOR ORDER ALREADY FINAL FOR COMBINATION.
					$transaction = $this->getCurrentTransaction();
					if ( $transaction !== 'INSERT_ORDERWITHPAYMENT' ) {
						// Don't regenerate order ID if it's too late, just steam
						// right through and let regular error handling deal
						// with it.
						$this->logger->error( 'Order ID already processed, remain calm.' );
						$retErrCode = $errCode;
						break;
					}
					$this->logger->error( 'InsertAttempt on a finalized order! Starting again.' );
					$retryOrderId = true;
					break;
				case 400490: // INSERTATTEMPT_MAX_NR_OF_ATTEMPTS_REACHED
					$this->logger->error( 'InsertAttempt - max attempts reached! Starting again.' );
					$retryOrderId = true;
					break;
				case 300620: // Oh no! We've already used this order # somewhere else! Restart!
					$this->logger->error( 'Order ID collision! Starting again.' );
					$retryOrderId = true;
					break;
				case 430260: // wow: If we were a point of sale, we'd be calling security.
				case 430349: // TRANSACTION_CANNOT_BE_COMPLETED_VIOLATION_OF_LAW (EXTERMINATE!)
				case 430357: // lost or stolen card
				case 430410: // CHALLENGED (GC docs say fraud)
				case 430415: // Security violation
				case 430418: // Stolen card
				case 430421: // Suspected fraud
				case 430697: // Suspected fraud
				case 485020: // DO_NOT_TRY_AGAIN (or else EXTERMINATE!)
				case 4360022: // ECARD_FRAUD
				case 4360023: // ECARD_ONLINE_FRAUD
					// These naughty codes get all the cancel treatment below, plus some extra
					// IP velocity spanking.
					if ( $this->getGlobal( 'EnableIPVelocityFilter' ) ) {
						Gateway_Extras_CustomFilters_IP_Velocity::penalize( $this );
					}
				case 430306: // Expired card.
				case 430330: // invalid card number
				case 430354: // issuer unknown
					// All of these should stop us from retrying at all
					// Null out the retry vars and throw error immediately
					$retryVars = null;
					$this->logger->info( "Got error code $errCode, not retrying to avoid Mastercard fines." );
					// TODO: move forceCancel - maybe to the exception?
					$this->transaction_response->setForceCancel( true );
					$this->transaction_response->addError( new PaymentError(
							$errCode,
							'Mastercard third rail error',
							LogLevel::ERROR
						)
					);
					throw new ResponseProcessingException(
						"Got error code $errCode, not retrying to avoid Mastercard fines.",
						$errCode
					);
				case 430285: // most common declined cc code.
				case 430396: // not authorized to cardholder, whatever that means.
				case 430409: // Declined, because "referred". We're not going to call the bank to push it through.
				case 430424: // Declined, because "SYSTEM_MALFUNCTION". I have no words.
				case 430692: // cvv2 declined
					break; // don't need to hear about these at all.

				case 11000400:  // Ingenico internal timeout, just try again as-is.
					$retryVars[] = 'timeout';
					$this->logger->error( 'Server Timeout, retrying.' );
					$retErrCode = $errCode;
					break;

				case 20001000: // REQUEST {0} NULL VALUE NOT ALLOWED FOR {1} : Validation pain. Need more.
					// look in the message for more clues.
					// Yes: That's an 8-digit error code that buckets a silly number of validation issues, some of which are legitimately ours.
					// The only way to tell is to search the English message.
					// @TODO: Refactor all 3rd party error handling for GC. This whole switch should definitely be in parseResponseErrors; It is very silly that this is here at all.
					$not_errors = [ // add more of these stupid things here, if log noise makes you want to
						'/NULL VALUE NOT ALLOWED FOR EXPIRYDATE/',
						'/DID NOT PASS THE LUHNCHECK/',
					];
					foreach ( $not_errors as $regex ) {
						if ( preg_match( $regex, $messageFromProcessor ) ) {
							// not a system error, but definitely the end of the payment attempt. Log it to info and leave.
							$this->logger->info( __FUNCTION__ . ": {$messageFromProcessor}" );
							throw new ResponseProcessingException(
								$messageFromProcessor,
								$errCode
							);
						}
					}

				case 21000050: // REQUEST {0} VALUE {2} OF FIELD {1} IS NOT A NUMBER WITH MINLENGTH {3}, MAXLENGTH {4} AND PRECISION {5}  : More validation pain.
					// say something painful here.
					$messageFromProcessor = 'Blocking validation problems with this payment. Investigation required! '
								. "Original error: '$messageFromProcessor'.  Our data: " . $this->getLogDebugJSON();

				default:
					$this->logger->error( __FUNCTION__ . " Error $errCode : $messageFromProcessor" );
					break;
			}
			if ( $retryOrderId ) {
				$retryVars[] = 'order_id';
				$retErrCode = $errCode;
			}
		}
		if ( $retErrCode ) {
			throw new ResponseProcessingException(
				$messageFromProcessor,
				$retErrCode,
				$retryVars
			);
		}

		// Unstage any data that we've configured.
		// TODO: This should be generalized into the base class.
		$allowed_keys = $this->transaction_option( 'response' );
		if ( $allowed_keys ) {
			$filtered_data = array_intersect_key( $data, array_flip( $allowed_keys ) );
			$unstaged = $this->unstageKeys( $filtered_data );
			$this->addResponseData( $unstaged );
		}
	}

	public function stageData() {
		// Must run first because staging relies on constraints.
		$this->tuneConstraints();

		parent::stageData();

		// FIXME: Move to a post-staging hook, and push most of it into the declarative block.
		$this->tuneForMethod();
		$this->tuneForRecurring();
		$this->tuneForCountry();
	}

	/**
	 * OUR language codes which are available to use in GlobalCollect.
	 * @return array
	 */
	public function getAvailableLanguages() {
		$languages = [
			'ar', // Arabic
			'cs', // Czech
			'da', // Danish
			'nl', // Dutch
			'en', // English
			'fa', // Farsi
			'fi', // Finish
			'fr', // French
			'de', // German
			'he', // Hebrew
			'hi', // Hindi
			'hu', // Hungarian
			'it', // Italian
			'ja', // Japanese
			'ko', // Korean
			'no', // Norwegian
			'pl', // Polish
			'pt', // Portuguese
			'ro', // Romanian
			'ru', // Russian
			'sl', // Slovene
			'es', // Spanish
			'sw', // Swahili
			'sv', // Swedish
			'th', // Thai
			'tr', // Turkish
			'ur', // Urdu
			'vi', // Vietnamese
			'zh', // the REAL chinese code.
		];
		return $languages;
	}

	/**
	 * Set up method-specific constraints
	 */
	protected function tuneConstraints() {
		// TODO: pull from declarative table

		if ( empty( $this->unstaged_data['payment_method'] ) ) {
			return;
		}

		switch ( $this->unstaged_data['payment_method'] ) {
		/* Bank transfer */
		case 'bt':
			// Brazil
			if ( $this->unstaged_data['country'] == 'BR' ) {
				$this->dataConstraints['direct_debit_text']['city'] = 50;
			}

			// Korea - Manual does not specify North or South
			if ( $this->unstaged_data['country'] == 'KR' ) {
				$this->dataConstraints['direct_debit_text']['city'] = 50;
			}
			break;

		/* Direct Debit */
		case 'dd':
			$this->dataConstraints['iban']['length'] = 21;

			switch ( $this->unstaged_data['country'] ) {
			case 'DE':
				$this->dataConstraints['account_number']['length'] = 10;
				$this->dataConstraints['bank_code']['length'] = 8;
				break;
			case 'NL':
				$this->dataConstraints['account_name']['length'] = 30;
				$this->dataConstraints['account_number']['length'] = 10;
				$this->dataConstraints['direct_debit_text']['length'] = 32;
				break;
			case 'AT':
				$this->dataConstraints['account_name']['length'] = 30;
				$this->dataConstraints['bank_code']['length'] = 5;
				$this->dataConstraints['direct_debit_text']['length'] = 28;
				break;
			case 'ES':
				$this->dataConstraints['account_name']['length'] = 30;
				$this->dataConstraints['account_number']['length'] = 10;
				$this->dataConstraints['bank_code']['length'] = 4;
				$this->dataConstraints['branch_code']['length'] = 4;
				$this->dataConstraints['direct_debit_text']['length'] = 40;
				break;
			case 'FR':
				$this->dataConstraints['direct_debit_text']['length'] = 18;
				break;
			case 'IT':
				$this->dataConstraints['account_name']['length'] = 30;
				$this->dataConstraints['account_number']['length'] = 12;
				$this->dataConstraints['bank_check_digit']['length'] = 1;
				$this->dataConstraints['bank_code']['length'] = 5;
				$this->dataConstraints['direct_debit_text']['length'] = 32;
				break;
			}
			break;
		}
	}

	/**
	 *
	 * @todo
	 * - Need to implement this for credit card if necessary
	 * - ISSUERID will need to provide a dropdown for rtbt_eps and rtbt_ideal.
	 * - COUNTRYCODEBANK will need it's own dropdown for country. Do not map to 'country'
	 * - DATECOLLECT is using gmdate('Ymd')
	 * - DIRECTDEBITTEXT will need to be translated. This is what appears on the bank statement for donations for a client. This is hardcoded to: Wikimedia Foundation
	 */
	protected function tuneForMethod() {
		switch ( $this->getData_Unstaged_Escaped( 'payment_method' ) ) {
		case 'dd':
		case 'ew':
			// TODO: Review.  Why is this set to country_bank_code in other cases?
			$this->var_map['COUNTRYCODEBANK'] = 'country';
			break;
		case 'rtbt':
			$this->getBankList();
			break;
		}

		// Use staged data so we pick up tricksy -_country variants
		if ( !empty( $this->staged_data['payment_submethod'] ) ) {
			$this->addKeysToTransactionForSubmethod( $this->staged_data['payment_submethod'] );
		}
	}

	/**
	 * Stage: recurring
	 * Adds the recurring payment pieces to the structure of
	 * INSERT_ORDERWITHPAYMENT if the recurring field is populated.
	 */
	protected function tuneForRecurring() {
		if ( $this->getData_Unstaged_Escaped( 'recurring' ) ) {
			$this->transactions['INSERT_ORDERWITHPAYMENT']['request']['REQUEST']['PARAMS']['ORDER'][] = 'ORDERTYPE';
			$this->transactions['INSERT_ORDERWITHPAYMENT']['values']['ORDERTYPE'] = '4';
		}
	}

	/**
	 * Stage: country
	 * This should be a catch-all for establishing weird country-based rules.
	 * Right now, we only have the one, but there could be more here later.
	 */
	protected function tuneForCountry() {
		switch ( $this->getData_Unstaged_Escaped( 'country' ) ) {
		case 'AR':
			$this->transactions['INSERT_ORDERWITHPAYMENT']['request']['REQUEST']['PARAMS']['ORDER'][] = 'USAGETYPE';
			$this->transactions['INSERT_ORDERWITHPAYMENT']['request']['REQUEST']['PARAMS']['ORDER'][] = 'PURCHASETYPE';
			$this->transactions['INSERT_ORDERWITHPAYMENT']['values']['USAGETYPE'] = '0';
			$this->transactions['INSERT_ORDERWITHPAYMENT']['values']['PURCHASETYPE'] = '1';
			break;
		}
	}

	/**
	 * Add keys to transaction for submethod
	 * TODO: Candidate for pushing to the base class.
	 * @param string $payment_submethod our code for the payment submethod
	 */
	protected function addKeysToTransactionForSubmethod( $payment_submethod ) {
		// If there are no keys to add, do not proceed.
		if ( empty( $this->payment_submethods[$payment_submethod]['keys'] ) ) {
			return;
		}

		foreach ( $this->payment_submethods[$payment_submethod]['keys'] as $key ) {
			$this->addKeyToTransaction( $key );
		}
	}

	/**
	 * hook pre_process for GET_ORDERSTATUS
	 */
	protected function post_process_get_orderstatus() {
		// Run antifraud only once per request.
		static $checked = [];

		$oid = $this->getData_Unstaged_Escaped( 'order_id' );
		$method = $this->getData_Unstaged_Escaped( 'payment_method' );
		if ( $method === 'cc' && empty( $checked[$oid] ) ) {
			$this->runAntifraudFilters();
			$checked[$oid] = true;
		} else {
			$message = 'Skipping fraud filters: ';
			if ( !empty( $checked[$oid] ) ) {
				$message .= "already checked order id '$oid'";
			} else {
				$message .= "payment method is '$method'";
			}
			$this->logger->info( $message );
		}
	}

	/**
	 * getCVVResult is intended to be used by the functions filter, to
	 * determine if we want to fail the transaction ourselves or not.
	 * @return null|false|array
	 */
	public function getCVVResult() {
		$from_processor = $this->getData_Unstaged_Escaped( 'cvv_result' );
		if ( $from_processor === null ) {
			return null;
		}

		$cvv_map = $this->getGlobal( 'CvvMap' );

		if ( !isset( $cvv_map[$from_processor] ) ) {
			$this->logger->warning( "Unrecognized cvv_result '$from_processor'" );
			return false;
		}

		$result = $cvv_map[$from_processor];
		return $result;
	}

	/**
	 * getAVSResult is intended to be used by the functions filter, to
	 * determine if we want to fail the transaction ourselves or not.
	 * @return null|array
	 */
	public function getAVSResult() {
		if ( $this->getData_Unstaged_Escaped( 'avs_result' ) === null ) {
			return null;
		}
		// Best guess here:
		// Scale of 0 - 100, of Problem we think this result is likely to cause.

		$avs_map = $this->getGlobal( 'AvsMap' );

		$result = $avs_map[$this->getData_Unstaged_Escaped( 'avs_result' )];
		return $result;
	}

	/**
	 * Update the list of banks for realtime bank transfer
	 */
	protected function getBankList() {
		// Need some basic data to do lookups
		if ( !is_array( $this->unstaged_data ) ) {
			return;
		}
		$country = $this->getData_Unstaged_Escaped( 'country' );
		$currency = $this->getData_Unstaged_Escaped( 'currency' );
		if ( $country === null || $currency === null ) {
			return;
		}
		try {
			$provider = PaymentProviderFactory::getProviderForMethod( 'rtbt' );
			$banks = $provider->getBankList( $country, $currency );
			$this->payment_submethods['rtbt_ideal']['issuerids'] = $banks;
		}
		catch ( Exception $e ) {
			$this->logger->warning(
				'Something failed trying to look up the banks, using hard-coded list' .
				$e->getMessage()
			);
		}
	}

	/**
	 * Used by ewallets, rtbt, and cash (boletos) to retrieve the URL we should
	 * be posting the form data to.
	 *
	 * @return string|false Returns FORMACTION if one exists in the transaction response, else false.
	 */
	public function getTransactionDataFormAction() {
		$data = $this->getTransactionData();

		if ( is_array( $data ) && array_key_exists( 'FORMACTION', $data ) ) {
			return $data['FORMACTION'];
		} else {
			return false;
		}
	}

	protected function getGatewayTransactionId() {
		return $this->getData_Unstaged_Escaped( 'order_id' );
	}

	protected function approvePayment() {
		return $this->do_transaction( 'SET_PAYMENT' );
	}

	protected function getStatusCode( $txnData ) {
		if ( isset( $txnData['STATUSID'] ) ) {
			return $txnData['STATUSID'];
		}
		return null;
	}

	/**
	 * Check if the curl_response is something we can process.
	 *
	 * GlobalCollect sends back an plain text error message when things goes bad
	 * so we check for it here to halt response processing if detected.
	 *
	 * @param mixed $curl_response
	 *
	 * @return bool
	 */
	protected function curlResponseIsValidFormat( $curl_response ) {
		// we're searching across the raw request
		if ( strpos( $curl_response, 'No response from application server. Please contact Global Collect' ) !== false ) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Override parent function to disallow order IDs starting with
	 * 4 or 7, which can collide with those created via the Connect
	 * API (as implemented in the 'Ingenico' adapter)
	 * @inheritDoc
	 */
	public function generateOrderID( $dataObj = null ) {
		do {
			$orderId = parent::generateOrderID( $dataObj );
			$firstChar = substr( (string)$orderId, 0, 1 );
		} while (
			// UGLY! But we don't want this to apply to the child
			// class ingenico adapter.
			self::getIdentifier() === 'globalcollect' &&
			( $firstChar === '4' || $firstChar === '7' )
		);
		return $orderId;
	}
}
