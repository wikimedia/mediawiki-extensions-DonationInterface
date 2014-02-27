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
 * @since		r98249
 * @author		Jeremy Postlethwaite <jpostlethwaite@wikimedia.org>
 */

/**
 * @see DonationInterfaceTestCase
 */
require_once dirname( dirname( dirname( __FILE__ ) ) ) . DIRECTORY_SEPARATOR . 'DonationInterfaceTestCase.php';

/**
 * 
 * @group Fundraising
 * @group DonationInterface
 * @group GlobalCollect
 */
class DonationInterface_Adapter_GlobalCollect_GlobalCollectTestCase extends DonationInterfaceTestCase {
	public function setUp() {

		$options = $this->getDonorTestData();
		$this->gatewayAdapter = $this->getGateway_DefaultObject( $options );
	}

	/**
	 * testDefineVarMap
	 *
	 * This is tested with a bank transfer from Spain.
	 *
	 * @covers GlobalCollectAdapter::__construct 
	 * @covers GlobalCollectAdapter::defineVarMap 
	 */
	public function testDefineVarMap() {
		global $wgGlobalCollectGatewayTest;

		$wgGlobalCollectGatewayTest = true;

		$var_map = array(
			'ORDERID' => 'order_id',
			'AMOUNT' => 'amount',
			'CURRENCYCODE' => 'currency_code',
			'LANGUAGECODE' => 'language',
			'COUNTRYCODE' => 'country',
			'MERCHANTREFERENCE' => 'order_id', //@TODO: Switch to 'contribution_tracking_id' after the refactor
			'RETURNURL' => 'returnto', 
			'IPADDRESS' => 'server_ip',
			'ISSUERID' => 'issuer_id',
			'PAYMENTPRODUCTID' => 'card_type', //@TODO: Switch to 'payment_product' after the refactor
			'CVV' => 'cvv',
			'EXPIRYDATE' => 'expiration',
			'CREDITCARDNUMBER' => 'card_num',
			'FIRSTNAME' => 'fname',
			'SURNAME' => 'lname',
			'STREET' => 'street',
			'CITY' => 'city',
			'STATE' => 'state',
			'ZIP' => 'zip',
			'EMAIL' => 'email',
			'ACCOUNTHOLDER' => 'account_holder',
			'ACCOUNTNAME' => 'account_name',
			'ACCOUNTNUMBER' => 'account_number',
			'ADDRESSLINE1E' => 'address_line_1e',
			'ADDRESSLINE2' => 'address_line_2',
			'ADDRESSLINE3' => 'address_line_3',
			'ADDRESSLINE4' => 'address_line_4',
			'ATTEMPTID' => 'attempt_id',
			'AUTHORISATIONID' => 'authorization_id',
			'BANKACCOUNTNUMBER' => 'bank_account_number',
			'BANKAGENZIA' => 'bank_agenzia',
			'BANKCHECKDIGIT' => 'bank_check_digit',
			'BANKCODE' => 'bank_code',
			'BANKFILIALE' => 'bank_filiale',
			'BANKNAME' => 'bank_name',
			'BRANCHCODE' => 'branch_code',
			'COUNTRYCODEBANK' => 'country_code_bank',
			'COUNTRYDESCRIPTION' => 'country_description',
			'CUSTOMERBANKCITY' => 'customer_bank_city',
			'CUSTOMERBANKSTREET' => 'customer_bank_street',
			'CUSTOMERBANKNUMBER' => 'customer_bank_number',
			'CUSTOMERBANKZIP' => 'customer_bank_zip',
			'DATECOLLECT' => 'date_collect',
			'DESCRIPTOR' => 'descriptor',
			'DIRECTDEBITTEXT' => 'direct_debit_text',
			'DOMICILIO' => 'domicilio',
			'EFFORTID' => 'effort_id',
			'IBAN' => 'iban',
			'IPADDRESSCUSTOMER' => 'user_ip',
			'PAYMENTREFERENCE' => 'payment_reference',
			'PROVINCIA' => 'provincia',
			'SPECIALID' => 'special_id',
			'SWIFTCODE' => 'swift_code',
			'TRANSACTIONTYPE' => 'transaction_type',
			'FISCALNUMBER' => 'fiscal_number',
		);
		
		$this->assertEquals( $var_map,  $this->gatewayAdapter->getVarMap() );
	}
}
