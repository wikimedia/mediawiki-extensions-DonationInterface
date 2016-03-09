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

/**
 * AstropayAdapter
 * Implementation of GatewayAdapter for processing payments via Astropay
 */
class AstropayAdapter extends GatewayAdapter {
	const GATEWAY_NAME = 'Astropay';
	const IDENTIFIER = 'astropay';
	const GLOBAL_PREFIX = 'wgAstropayGateway';

	public function getCommunicationType() {
		return 'namevalue';
	}

	public function getResponseType() {
		$override = $this->transaction_option( 'response_type' );
		if ( $override ) {
			return $override;
		}
		return 'json';
	}

	function defineAccountInfo() {
		$this->accountInfo = $this->account_config;
	}

	function defineDataConstraints() {
		$this->dataConstraints = array(
			'x_login'		=> array( 'type' => 'alphanumeric',	'length' => 10, ),
			'x_trans_key'	=> array( 'type' => 'alphanumeric',	'length' => 10, ),
			'x_invoice'		=> array( 'type' => 'alphanumeric',	'length' => 20, ),
			'x_amount'		=> array( 'type' => 'numeric', ),
			'x_currency'	=> array( 'type' => 'alphanumeric',	'length' => 3, ),
			'x_bank'		=> array( 'type' => 'alphanumeric',	'length' => 3, ),
			'x_country'		=> array( 'type' => 'alphanumeric',	'length' => 2, ),
			'x_description'	=> array( 'type' => 'alphanumeric',	'length' => 200, ),
			'x_iduser'		=> array( 'type' => 'alphanumeric',	'length' => 20, ),
			'x_cpf'			=> array( 'type' => 'alphanumeric',	'length' => 30, ),
			'x_name'		=> array( 'type' => 'alphanumeric', ),
			'x_email'		=> array( 'type' => 'alphanumeric', ),
			'x_bdate'		=> array( 'type' => 'date',	'length' => 8, ),
			'x_address'		=> array( 'type' => 'alphanumeric', ),
			'x_zip'			=> array( 'type' => 'alphanumeric',	'length' => 10, ),
			'x_city'		=> array( 'type' => 'alphanumeric', ),
			'x_state'		=> array( 'type' => 'alphanumeric',	'length' => 2, ),
			'country_code'	=> array( 'type' => 'alphanumeric',	'length' => 2, ),
		);
	}

	function defineErrorMap() {
		$this->error_map = array(
			'internal-0000' => 'donate_interface-processing-error', // Failed pre-process checks.
			ResponseCodes::DUPLICATE_ORDER_ID => 'donate_interface-processing-error', // Order ID already used in a previous transaction
		);
	}

	function defineStagedVars() {
		$this->staged_vars = array(
			'bank_code',
			'donor_id',
			'fiscal_number',
			'full_name',
		);
	}

	/**
	 * Define var_map
	 */
	function defineVarMap() {
		$this->var_map = array(
			'x_login'		=> 'merchant_id',
			'x_trans_key'	=> 'merchant_password',
			'x_invoice'		=> 'order_id',
			'x_amount'		=> 'amount',
			'x_currency'	=> 'currency_code',
			'x_bank'		=> 'bank_code',
			'x_country'		=> 'country',
			'x_description'	=> 'description',
			'x_iduser'		=> 'donor_id',
			'x_cpf'			=> 'fiscal_number',
			'x_name'		=> 'full_name',
			'x_email'		=> 'email',
			// We've been told bdate is non-mandatory, despite the docs
			'x_bdate'		=> 'birth_date',
			'x_address'		=> 'street',
			'x_zip'			=> 'zip',
			'x_city'		=> 'city',
			'x_state'		=> 'state',
			'x_document'	=> 'gateway_txn_id',
			'country_code'	=> 'country',
		);
	}

	function defineReturnValueMap() {
		$this->return_value_map = array();
		// 6: Transaction not found in the system
		$this->addCodeRange( 'PaymentStatus', 'result', FinalStatus::FAILED, 6 );
		// 7: Pending transaction awaiting approval
		$this->addCodeRange( 'PaymentStatus', 'result', FinalStatus::PENDING, 7 );
		// 8: Operation rejected by bank
		$this->addCodeRange( 'PaymentStatus', 'result', FinalStatus::FAILED, 8 );
		// 9: Amount Paid.  Transaction successfully concluded
		$this->addCodeRange( 'PaymentStatus', 'result', FinalStatus::COMPLETE, 9 );
	}

	/**
	 * Sets up the $order_id_meta array.
	 * For Astropay, we use the ct_id.sequence format because we don't get
	 * a gateway transaction ID until the user has actually paid.  If the user
	 * doesn't return to the result switcher, we will need to use the order_id
	 * to find a pending queue message with donor details to flesh out the
	 * audit entry or listener message that tells us the payment succeeded.
	 */
	public function defineOrderIDMeta() {
		$this->order_id_meta = array (
			'alt_locations' => array ( 'request' => 'x_invoice' ),
			'generate' => TRUE,
			'ct_id' => TRUE,
			'length' => 20,
		);
	}

	function setGatewayDefaults() {}

	function defineTransactions() {
		$this->transactions = array( );

		$this->transactions[ 'NewInvoice' ] = array(
			'path' => 'api_curl/streamline/NewInvoice',
			'request' => array(
				'x_login',
				'x_trans_key', // password
				'x_invoice', // order id
				'x_amount',
				'x_currency',
				'x_bank', // payment submethod bank code
				'x_country',
				'x_description',
				'x_iduser',
				'x_cpf',
				'x_name',
				'x_email',
				// Omitting the following optional fields
				// 'x_bdate',
				// 'x_address',
				// 'x_zip',
				// 'x_city',
				// 'x_state',
				'control',
				'type',
			),
			'values' => array(
				'x_login' => $this->accountInfo['Create']['Login'],
				'x_trans_key' => $this->accountInfo['Create']['Password'],
				'x_description' => WmfFramework::formatMessage( 'donate_interface-donation-description' ),
				'type' => 'json',
			)
		);

		$this->transactions[ 'GetBanks' ] = array(
			'path' => 'api_curl/apd/get_banks_by_country',
			'request' => array(
				'x_login',
				'x_trans_key',
				'country_code',
				'type',
			),
			'values' => array(
				'x_login' => $this->accountInfo['Create']['Login'],
				'x_trans_key' => $this->accountInfo['Create']['Password'],
				'type' => 'json',
			)
		);

		$this->transactions[ 'PaymentStatus' ] = array(
			'path' => '/apd/webpaystatus',
			'request' => array(
				'x_login',
				'x_trans_key',
				'x_invoice',
			),
			'values' => array(
				'x_login' => $this->accountInfo['Status']['Login'],
				'x_trans_key' => $this->accountInfo['Status']['Password'],
			),
			'response_type' => 'delimited',
			'response_delimiter' => '|',
			'response_keys' => array(
				'result', // status code
				'x_iduser',
				'x_invoice',
				'x_amount',
				'PT', // 0 for production, 1 for test
				'x_control', // signature, calculated like control string
							// called 'Sign' in docs, but renamed here for consistency
							// with parameter POSTed to resultswitcher.
				'x_document', // unique id at Astropay
				'x_bank',
				'x_payment_type',
				'x_bank_name',
				'x_currency',
			)
		);

		// Not for running with do_transaction, just a handy place to keep track
		// of what we expect POSTed to the resultswitcher.
		$this->transactions[ 'ProcessReturn' ] = array(
			'request' => array(
				'result',
				'x_invoice',
				'x_iduser',
				'x_description',
				'x_document',
				'x_amount',
				'x_control',
			)
		);
	}

	public function definePaymentMethods() {
		$this->payment_methods = array();

		// TODO if we add countries where fiscal number is not required:
		// make fiscal_number validation depend on country
		$this->payment_methods['cc'] = array(
			'validation' => array(
				'name' => true,
				'email' => true,
				'fiscal_number' => true,
			),
		);

		$this->payment_methods['bt'] = array(
			'validation' => array(
				'name' => true,
				'email' => true,
				'fiscal_number' => true,
			),
		);

		$this->payment_methods['cash'] = array(
			'validation' => array(
				'name' => true,
				'email' => true,
				'fiscal_number' => true,
			),
		);

		$this->payment_submethods = array();

		if ( self::getGlobal( 'Test' ) ) {
			// Test bank labelled 'GNB' on their site
			// Data for testing in Brazil (other countries can use random #s)
			// Cpf: 00003456789
			// Email: testing@astropaycard.com
			// Name: ASTROPAY TESTING
			// Birthdate: 04/03/1984
			$this->payment_submethods['test_bank'] = array(
				'bank_code' => 'TE',
				'label' => 'GNB',
				'group' => 'cc',
			);
		}

		// Visa
		$this->payment_submethods['visa'] = array(
			'bank_code' => 'VI',
			'label' => 'Visa',
			'group' => 'cc',
			'countries' => array(
				'AR' => true,
				'BR' => true,
				'CL' => true,
				'MX' => true,
				'CO' => true,
			),
			'logo' => 'card-visa-lg.png',
		);

		// MasterCard
		$this->payment_submethods['mc'] = array(
			'bank_code' => 'MC',
			'label' => 'MasterCard',
			'group' => 'cc',
			'countries' => array(
				'AR' => true,
				'BR' => true,
				'CL' => true,
				'MX' => true,
				'CO' => true,
			),
			'logo' => 'card-mc-lg.png',
		);

		// Magna
		$this->payment_submethods['magna'] = array(
			'bank_code' => 'MG',
			'label' => 'Magna',
			'group' => 'cc',
			'countries' => array(
				'CL' => true,
			),
			'logo' => 'card-magna.png',
		);

		// American Express
		$this->payment_submethods['amex'] = array(
			'bank_code' => 'AE',
			'label' => 'American Express',
			'group' => 'cc',
			'countries' => array(
				'BR' => true,
				'AR' => true,
				'CL' => true,
				'CO' => true,
			),
			'logo' => 'card-amex-lg.png',
		);

		// Visa Debit
		$this->payment_submethods['visa-debit'] = array(
			'bank_code' => 'VD',
			'label' => 'Visa Debit',
			'group' => 'cc',
			'countries' => array(
				'MX' => true,
			),
			'logo' => 'card-visa-lg.png',
			'sub_text_key' => 'donate_interface-debit',
		);

		// MasterCard debit
		$this->payment_submethods['mc-debit'] = array(
			'bank_code' => 'MD',
			'label' => 'Mastercard Debit',
			'group' => 'cc',
			'countries' => array(
				'MX' => true,
			),
			'logo' => 'card-mc-lg.png',
			'sub_text_key' => 'donate_interface-debit',
		);

		// Elo (Brazil-only)
		$this->payment_submethods['elo'] = array(
			'bank_code' => 'EL',
			'label' => 'Elo',
			'group' => 'cc',
			'countries' => array( 'BR' => true, ),
			'logo' => 'card-elo.png',
		);

		// Diners Club
		$this->payment_submethods['diners'] = array(
			'bank_code' => 'DC',
			'label' => 'Diners Club',
			'group' => 'cc',
			'countries' => array(
				'BR' => true,
				'CL' => true,
				'CO' => true,
			),
			'logo' => 'card-dinersclub-lg.png',
		);

		// Hipercard
		$this->payment_submethods['hiper'] = array(
			'bank_code' => 'HI',
			'label' => 'Hipercard',
			'group' => 'cc',
			'countries' => array( 'BR' => true, ),
			'logo' => 'card-hiper.png',
		);

		// MercadoLivre
		$this->payment_submethods['mercadolivre'] = array(
			'bank_code' => 'ML',
			'label' => 'MercadoLivre',
			'group' => 'cc',
			'countries' => array( 'BR' => true, ),
			'logo' => 'card-mercadolivre.png',
		);

		// Cabal
		$this->payment_submethods['cabal'] = array(
			'bank_code' => 'CL',
			'label' => 'Cabal',
			'group' => 'cc',
			'countries' => array( 'AR' => true, ),
			'logo' => 'card-cabal.png',
		);

		// Naranja
		$this->payment_submethods['naranja'] = array(
			'bank_code' => 'NJ',
			'label' => 'Naranja',
			'group' => 'cc',
			'countries' => array( 'AR' => true, ),
			'logo' => 'card-naranja.png',  //check logo
		);

		// Tarjeta Shopping
		$this->payment_submethods['shopping'] = array(
			'bank_code' => 'TS',
			'label' => 'Tarjeta Shopping',
			'group' => 'cc',
			'countries' => array( 'AR' => true, ),
			'logo' => 'card-shopping.png',
		);

		// Nativa
		$this->payment_submethods['nativa'] = array(
			'bank_code' => 'NT',
			'label' => 'Nativa',
			'group' => 'cc',
			'countries' => array( 'AR' => true, ),
			'logo' => 'card-nativa.png',
		);

		// Cencosud
		$this->payment_submethods['cencosud'] = array(
			'bank_code' => 'TS',
			'label' => 'Cencosud',
			'group' => 'cc',
			'countries' => array( 'AR' => true, ),
			'logo' => 'card-cencosud.png',
		);

		// Argencard
		$this->payment_submethods['argen'] = array(
			'bank_code' => 'AG',
			'label' => 'Argencard',
			'group' => 'cc',
			'countries' => array( 'AR' => true, ),
			'logo' => 'card-argencard.png',
		);

		// CMR Falabella
		$this->payment_submethods['cmr'] = array(
			'bank_code' => 'CM',
			'label' => 'CMR',
			'group' => 'cc',
			'countries' => array( 'CL' => true, ),
			'logo' => 'card-cmr.png',
		);

		// Presto
		$this->payment_submethods['presto'] = array(
			'bank_code' => 'PR',
			'label' => 'Presto',
			'group' => 'cc',
			'countries' => array( 'CL' => true, ),
			'logo' => 'card-presto.png',
		);

		// Webpay
		$this->payment_submethods['webpay'] = array(
			'bank_code' => 'WP',
			'label' => 'Webpay',
			'group' => array( 'cc', 'bt', ),
			'countries' => array( 'CL' => true, ),
			'logo' => 'bank-webpay.png',
		);

		// Banco de Chile
		$this->payment_submethods['banco_de_chile'] = array(
			'bank_code' => 'BX',
			'label' => 'Banco de Chile',
			'group' => 'bt',
			'countries' => array( 'CL' => true, ),
			'logo' => 'bank-banco_de_chile.png',
		);

		// Banco do Brasil
		$this->payment_submethods['banco_do_brasil'] = array(
			'bank_code' => 'BB',
			'label' => 'Banco do Brasil',
			'group' => 'bt',
			'countries' => array( 'BR' => true, ),
			'logo' => 'bank-banco_do_brasil.png',
		);

		// Itau
		$this->payment_submethods['itau'] = array(
			'bank_code' => 'I',
			'label' => 'Itau',
			'group' => 'bt',
			'countries' => array( 'BR' => true, ),
			'logo' => 'bank-itau.png',
		);

		// Bradesco
		$this->payment_submethods['bradesco'] = array(
			'bank_code' => 'B',
			'label' => 'Bradesco',
			'group' => 'bt',
			'countries' => array( 'BR' => true, ),
			'logo' => 'bank-bradesco.png',
		);

		// Caixa (disabled by AstroPay)
		/*$this->payment_submethods['caixa'] = array(
			'bank_code' => 'CA',
			'label' => 'Caixa',
			'group' => 'bt',
			'countries' => array( 'BR' => true, ),
			'logo' => 'bank-caixa.png',
		);*/

		// HSBC (disabled by AstroPay)
		/*$this->payment_submethods['hsbc'] = array(
			'bank_code' => 'H',
			'label' => 'HSBC',
			'group' => 'bt',
			'countries' => array( 'BR' => true, ),
			'logo' => 'bank-hsbc.png',
		);*/

		// Santander (Brazil)
		$this->payment_submethods['santander'] = array(
			'bank_code' => 'SB',
			'label' => 'Santander',
			'group' => 'bt',
			'countries' => array( 'BR' => true, ),
			'logo' => 'bank-santander.png',
		);

		// Santander (Argentina)
		$this->payment_submethods['santander_rio'] = array(
			'bank_code' => 'SI',
			'label' => 'Santander',
			'group' => 'bt',
			'countries' => array( 'AR' => true, ),
			'logo' => 'bank-santander.png',
		);

		// Boletos
		$this->payment_submethods['cash_boleto'] = array(
			'bank_code' => 'BL',
			'label' => 'Boletos',
			'group' => 'cash',
			'countries' => array( 'BR' => true, ),
		);

		//OXXO
		$this->payment_submethods['cash_oxxo'] = array(
			'bank_code' => 'OX',
			'label' => 'OXXO',
			'group' => 'cash',
			'countries' => array( 'MX' => true, ),
			'logo' => 'cash-oxxo.png',
		);
		// Santander (Mexico)
		$this->payment_submethods['cash_santander'] = array(
			'bank_code' => 'SM',
			'label' => 'Santander',
			'group' => 'cash',
			'countries' => array( 'MX' => true, ),
			'logo' => 'bank-santander.png',
		);

		//Banamex
		$this->payment_submethods['cash_banamex'] = array(
			'bank_code' => 'BM',
			'label' => 'Banamex',
			'group' => 'cash',
			'countries' => array( 'MX' => true, ),
			'logo' => 'cash-banamex.png',
		);

		//Bancomer
		$this->payment_submethods['cash_bancomer'] = array(
			'bank_code' => 'BM',
			'label' => 'Bancomer',
			'group' => 'cash',
			'countries' => array( 'MX' => true, ),
			'logo' => 'cash-bancomer.png',
		);

		//Rapi Pago
		$this->payment_submethods['cash_rapipago'] = array(
			'bank_code' => 'RP',
			'label' => 'Rapi Pago',
			'group' => 'cash',
			'countries' => array( 'AR' => true, ),
			'logo' => 'cash-rapipago.png',
		);

		//Pago Facil
		$this->payment_submethods['cash_pago_facil'] = array(
			'bank_code' => 'PF',
			'label' => 'Pago Facil',
			'group' => 'cash',
			'countries' => array( 'AR' => true, ),
			'logo' => 'cash-pago-facil.png',
		);

		//Provencia Pagos
		$this->payment_submethods['cash_provencia_pagos'] = array(
			'bank_code' => 'BG',
			'label' => 'Provencia Pagos',
			'group' => 'cash',
			'countries' => array( 'AR' => true, ),
			'logo' => 'cash-provencia-pagos.png',
		);

		//Efecty
		$this->payment_submethods['cash_efecty'] = array(
			'bank_code' => 'EY',
			'label' => 'Efecty',
			'group' => 'cash',
			'countries' => array( 'CO' => true, ),
			'logo' => 'cash-efecty.png',
		);
		//Davivienda
		$this->payment_submethods['cash_davivienda'] = array(
			'bank_code' => 'DA',
			'label' => 'Davivienda',
			'group' => 'cash',
			'countries' => array( 'CO' => true, ),
			'logo' => 'cash-davivienda.png',
		);

		// PSE
		$this->payment_submethods['pse'] = array(
			'bank_code' => 'PC',
			'label' => 'PSE',
			'group' => 'bt',
			'countries' => array( 'CO' => true, ),
		);

		//Pago Efectivo
		$this->payment_submethods['cash_pago_efectivo'] = array(
			'bank_code' => 'EF',
			'label' => 'Pago Efectivo',
			'group' => 'cash',
			'countries' => array( 'PE' => true, ),
			'logo' => 'cash-pago-efectivo.png',
		);

		//Red Pagos
		$this->payment_submethods['cash_red_pagos'] = array(
			'bank_code' => 'RE',
			'label' => 'Red Pagos',
			'group' => 'cash',
			'countries' => array( 'UY' => true, ),
			'logo' => 'cash-red-pagos.png',
		);

	}

	function doPayment() {
		// If this is not our first NewInvoice call, get a fresh order ID
		if ( $this->session_getData( 'sequence' ) ) {
			$this->regenerateOrderID();
		}

		$transaction_result = $this->do_transaction( 'NewInvoice' );
		$this->runAntifraudHooks();
		if ( $this->getValidationAction() !== 'process' ) {
			$this->finalizeInternalStatus( FinalStatus::FAILED );
		}
		$result = PaymentResult::fromResults(
			$transaction_result,
			$this->getFinalStatus()
		);
		if ( $result->getRedirect() ) {
			// Write the donor's details to the log for the audit processor
			$this->logPaymentDetails();
			// Feed the message into the pending queue, so the CRM queue consumer
			// can read it to fill in donor details when it gets a partial message
			$this->setLimboMessage( 'pending' );
		}
		return $result;
	}

	/**
	 * Overriding parent method to add fiscal number
	 * @return array of required field names
	 */
	public function getRequiredFields() {
		$fields = parent::getRequiredFields();
		$fields[] = 'fiscal_number';
		$fields[] = 'payment_submethod';
		return $fields;
	}
	/**
	 * Overriding @see GatewayAdapter::getTransactionSpecificValue to add a
	 * calculated signature.
	 * @param string $gateway_field_name
	 * @param boolean $token
	 * @return mixed
	 */
	protected function getTransactionSpecificValue( $gateway_field_name, $token = false ) {
		if ( $gateway_field_name === 'control' ) {
			$message = $this->getMessageToSign();
			return $this->calculateSignature( $message );
		}
		return parent::getTransactionSpecificValue( $gateway_field_name, $token );
	}

	protected function getMessageToSign() {
		return str_replace( '+', ' ',
			$this->getData_Staged( 'order_id' ) . 'V'
			. $this->getData_Staged( 'amount' ) . 'I'
			. $this->getData_Staged( 'donor_id' ) . '2'
			. $this->getData_Staged( 'bank_code' ) . '1'
			. $this->getData_Staged( 'fiscal_number' ) . 'H'
			. /* bdate omitted */ 'G'
			. $this->getData_Staged( 'email' ) .'Y'
			. /* zip omitted */ 'A'
			. /* street omitted */ 'P'
			. /* city omitted */ 'S'
			. /* state omitted */ 'P' );
	}

	/**
	 * They need a 20 char string for a customer ID - give them the first 20
	 * characters of the email address for easy lookup
	 */
	protected function stage_donor_id() {
		// We use these to look up donations by email, so strip out the trailing
		// spam-tracking sub-address to get the email we'd see complaints from.
		$email = preg_replace( '/\+[^@]*/', '', $this->getData_Staged( 'email' ) );
		$this->staged_data['donor_id'] = substr( $email, 0, 20 );
	}

	protected function stage_bank_code() {
		$submethod = $this->getPaymentSubmethod();
		if ( $submethod ) {
			$meta = $this->getPaymentSubmethodMeta( $submethod );
			if ( isset( $meta['bank_code'] ) ) {
				$this->staged_data['bank_code'] = $meta['bank_code'];
			}
		}
	}

	/**
	 * Strip any punctuation from fiscal number before submitting
	 */
	protected function stage_fiscal_number() {
		$value = $this->getData_Unstaged_Escaped( 'fiscal_number' );
		if ( $value ) {
			$this->staged_data['fiscal_number'] = preg_replace( '/[^a-zA-Z0-9]/', '', $value );
		}
	}

	protected function unstage_payment_submethod() {
		$method = $this->getData_Staged( 'payment_method' );
		$bank = $this->getData_Staged( 'bank_code' );
		$filter = function( $submethod ) use ( $method, $bank ) {
			$groups = (array) $submethod['group'];
			return in_array( $groups, $method ) && $submethod['bank_code'] === $bank;
		};
		$candidates = array_filter( $this->payment_submethods, $filter );
		if ( count( $candidates ) !== 1 ) {
			throw new UnexpectedValueException( "No unique payment submethod defined for payment method $method and bank code $bank." );
		}
		$keys = array_keys( $candidates );
		$this->unstaged_data['payment_submethod'] = $keys[0];
	}

	public function getCurrencies( $options = array() ) {
		$country = isset( $options['country'] ) ?
					$options['country'] :
					$this->getData_Unstaged_Escaped( 'country' );

		if ( !$country ) {
			throw new InvalidArgumentException( 'Need to specify country if not yet set in unstaged data' );
		}
		$currencies = array(
			'AR' => 'ARS', // Argentinian peso
			'BO' => 'BOB', // Bolivian Boliviano
			'BR' => 'BRL', // Brazilian Real
			'BZ' => 'BZD', // Belize Dollar
			'CL' => 'CLP', // Chilean Peso
			'CO' => 'COP', // Colombian Peso
			'MX' => 'MXN', // Mexican Peso
			'PE' => 'PEN', // Peruvian Nuevo Sol
			'US' => 'USD', // U.S. dollar
		);
		if ( !isset( $currencies[$country] ) ) {
			throw new OutOfBoundsException( "No supported currencies for $country" );
		}
		return (array)$currencies[$country];
	}

	/**
	 * Processes JSON data from Astropay API, and also processes GET/POST params
	 * on donor's return to ResultSwitcher
	 * @param array $response JSON response decoded to array, or GET/POST
	 *        params from request
	 * @throws ResponseProcessingException
	 */
	public function processResponse( $response ) {
		// May need to initialize transaction_response, as we can be called by
		// GatewayPage to process responses outside of do_transaction
		if ( !$this->transaction_response ) {
			$this->transaction_response = new PaymentTransactionResponse();
		}
		$this->transaction_response->setData( $response );
		if ( !$response ) {
			throw new ResponseProcessingException(
				'Missing or badly formatted response',
				ResponseCodes::NO_RESPONSE
			);
		}
		switch( $this->getCurrentTransaction() ) {
		case 'PaymentStatus':
			$this->processStatusResponse( $response );
			break;
		case 'ProcessReturn':
			$this->processStatusResponse( $response );
			if ( !isset( $response['x_document'] ) ) {
				$this->logger->error( 'Astropay did not post back their transaction ID in x_document' );
				throw new ResponseProcessingException(
					'Astropay did not post back their transaction ID in x_document',
					ResponseCodes::MISSING_TRANSACTION_ID
				);
			}
			// Make sure we record the right amount, even if the donor has opened
			// a new window and messed with their session data.
			// Unfortunately, we don't get the currency code back.
			$this->addResponseData( array(
				'amount' => $response['x_amount'],
			) );
			$this->transaction_response->setGatewayTransactionId( $response['x_document'] );
			$status = $this->findCodeAction( 'PaymentStatus', 'result', $response['result'] );
			$this->logger->info( "Payment status $status coming back to ResultSwitcher" );
			$this->finalizeInternalStatus( $status );
			$this->runPostProcessHooks();
			$this->deleteLimboMessage( 'pending' );
			break;
		case 'NewInvoice':
			$this->processNewInvoiceResponse( $response );
			if ( isset( $response['link'] ) ) {
				$this->transaction_response->setRedirect( $response['link'] );
			}
			break;
		}
	}

	/**
	 * Sets communication status and errors for responses to NewInvoice
	 * @param array $response
	 */
	protected function processNewInvoiceResponse( $response ) {
		// Increment sequence number so next NewInvoice call gets a new order ID
		$this->incrementSequenceNumber();
		if ( !isset( $response['status'] ) ) {
			$this->transaction_response->setCommunicationStatus( false );
			$this->logger->error( 'Astropay response does not have a status code' );
			throw new ResponseProcessingException(
				'Astropay response does not have a status code',
				ResponseCodes::MISSING_REQUIRED_DATA
			);
		}
		$this->transaction_response->setCommunicationStatus( true );
		if ( $response['status'] === '0' ) {
			if ( !isset( $response['link'] ) ) {
				$this->logger->error( 'Astropay NewInvoice success has no link' );
				throw new ResponseProcessingException(
					'Astropay NewInvoice success has no link',
					ResponseCodes::MISSING_REQUIRED_DATA
				);
			}
		} else {
			$logme = 'Astropay response has non-zero status.  Full response: '
				. print_r( $response, true );
			$this->logger->warning( $logme );

			$code = 'internal-0000';
			$message = $this->getErrorMapByCodeAndTranslate( $code );
			$context = null;

			if ( isset( $response['desc'] ) ) {
				// error codes are unreliable, so we have to examine the description
				if ( preg_match( '/^invoice already used/i', $response['desc'] ) ) {
					$this->logger->error( 'Order ID collision! Starting again.' );
					throw new ResponseProcessingException(
						'Order ID collision! Starting again.',
						ResponseCodes::DUPLICATE_ORDER_ID,
						array( 'order_id' )
					);
				} else if ( preg_match( '/^could not (register user|make the deposit)/i', $response['desc'] ) ) {
					// AstroPay is overwhelmed.  Tell the donor to try again soon.
					$message = WmfFramework::formatMessage( 'donate_interface-try-again' );
				} else if ( preg_match( '/^user (unauthorized|blacklisted)/i', $response['desc'] ) ) {
					// They are blacklisted by Astropay for shady doings,
					// or listed delinquent by their government.
					// Either way, we can't process 'em through AstroPay
					$this->finalizeInternalStatus( FinalStatus::FAILED );
				} else if ( preg_match( '/^the user limit has been exceeded/i', $response['desc'] ) ) {
					// They've spent too much via AstroPay today.
					// Setting context to 'amount' will tell the form to treat
					// this like a validation error and make amount editable.
					$context = 'amount';
					$message = WmfFramework::formatMessage( 'donate_interface-error-msg-limit' );
				} else if ( preg_match( '/param x_cpf$/i', $response['desc'] ) ) {
					// Something wrong with the fiscal number
					$context = 'fiscal_number';
					$language = $this->dataObj->getVal_Escaped( 'language' );
					$country = $this->dataObj->getVal_Escaped( 'country' );
					$message = DataValidator::getErrorMessage( 'fiscal_number', 'calculated', $language, $country );
				} else if ( preg_match( '/invalid control/i', $response['desc'] ) ) {
					// They think we screwed up the signature.  Log what we signed.
					$signed = $this->getMessageToSign();
					$signature = $this->getTransactionSpecificValue( 'control' );
					$this->logger->error( "$logme Signed message: '$signed' Signature: '$signature'" );
				} else {
					// Some less common error.  Also log message at 'error' level
					$this->logger->error( $logme );
				}
			}
			$this->transaction_response->setErrors( array(
				$code => array (
					'message' => $message,
					'debugInfo' => $logme,
					'logLevel' => LogLevel::WARNING,
					'context' => $context
				)
			) );
		}
	}

	/**
	 * Sets communication status and errors for responses to PaymentStatus or
	 * parameters POSTed back to ResultSwitcher
	 * @param array $response
	 */
	protected function processStatusResponse( $response ) {
		if ( !isset( $response['result'] ) ||
			 !isset( $response['x_amount'] ) ||
			 !isset( $response['x_invoice'] ) ||
			 !isset( $response['x_control'] ) ) {
			$this->transaction_response->setCommunicationStatus( false );
			$message = 'Astropay response missing one or more required keys.  Full response: '
				. print_r( $response, true );
			$this->logger->error( $message );
			throw new ResponseProcessingException( $message, ResponseCodes::MISSING_REQUIRED_DATA );
		}
		$this->verifyStatusSignature( $response );
		if ( $response['result'] === '6' ) {
			$logme = 'Astropay reports they cannot find the transaction for order ID ' .
				$this->getData_Unstaged_Escaped( 'order_id' );
			$this->logger->error( $logme );
			$this->transaction_response->setErrors( array(
				'internal-0000' => array (
					'message' => $this->getErrorMapByCodeAndTranslate( 'internal-0000' ),
					'debugInfo' => $logme,
					'logLevel' => LogLevel::ERROR
				)
			) );
		}
	}

	/**
	 * Check whether a status message has a valid signature.
	 * @param array $data
	 *        Requires 'result', 'x_amount', 'x_invoice', and 'x_control' keys
	 * @throws ResponseProcessingException if signature is invalid
	 */
	function verifyStatusSignature( $data ) {
		if ( $this->getCurrentTransaction() === 'ProcessReturn' ) {
			$login = $this->accountInfo['Create']['Login'];
		} else {
			$login = $this->accountInfo['Status']['Login'];
		}

		$message = $login .
			$data['result'] .
			$data['x_amount'] .
			$data['x_invoice'];
		$signature = $this->calculateSignature( $message );

		if ( $signature !== $data['x_control'] ) {
			$message = 'Bad signature in transaction ' . $this->getCurrentTransaction();
			$this->logger->error( $message );
			throw new ResponseProcessingException( $message, ResponseCodes::BAD_SIGNATURE );
		}
	}

	protected function calculateSignature( $message ) {
		$key = $this->accountInfo['SecretKey'];
		return strtoupper(
			hash_hmac( 'sha256', pack( 'A*', $message ), pack( 'A*', $key ) )
		);
	}

	protected function unstage_amount() {
		// FIXME: if GlobalCollect is the only processor who needs amount in
		// cents, move its stage and unstage functions out of base adapter
		$this->unstaged_data['amount'] = $this->getData_Staged( 'amount' );
	}
}
