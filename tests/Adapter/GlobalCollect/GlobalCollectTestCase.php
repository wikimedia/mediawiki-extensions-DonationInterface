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

	function __construct() {
		parent::__construct();
		$this->testAdapterClass = 'TestingGlobalCollectAdapter';
	}

	/**
	 * testnormalizeOrderID
	 * Non-exhaustive integration tests to verify that order_id
	 * normalization works as expected with different settings and
	 * conditions in theGlobalCollect adapter
	 * @covers normalizeOrderID
	 */
	public function testnormalizeOrderID() {
		$init = $this->initial_vars;
		unset( $init['order_id'] );

		//no order_id from anywhere, explicit no generate
		$gateway = $this->getFreshGatewayObject( $init, array ( 'order_id_meta' => array ( 'generate' => FALSE ) ) );
		$this->assertFalse( $gateway->getOrderIDMeta( 'generate' ), 'The order_id meta generate setting override is not working properly. Deferred order_id generation may be broken.' );
		$this->assertNull( $gateway->getData_Unstaged_Escaped( 'order_id' ), 'Failed asserting that an absent order id is left as null, when not generating our own' );

		//no order_id from anywhere, explicit generate
		$gateway = $this->getFreshGatewayObject( $init, array ( 'order_id_meta' => array ( 'generate' => TRUE ) ) );
		$this->assertTrue( $gateway->getOrderIDMeta( 'generate' ), 'The order_id meta generate setting override is not working properly. Self order_id generation may be broken.' );
		$this->assertInternalType( 'numeric', $gateway->getData_Unstaged_Escaped( 'order_id' ), 'Generated order_id is not numeric, which it should be for GlobalCollect' );

		$_GET['order_id'] = '55555';
		$_SESSION['Donor']['order_id'] = '44444';

		//conflicting order_id in $GET and $SESSION, default GC generation
		$gateway = $this->getFreshGatewayObject( $init );
		$this->assertEquals( '55555', $gateway->getData_Unstaged_Escaped( 'order_id' ), 'GlobalCollect gateway is preferring session data over the $_GET. Session should be secondary.' );

		//conflicting order_id in $GET and $SESSION, garbage data in $_GET, default GC generation
		$_GET['order_id'] = 'nonsense!';
		$gateway = $this->getFreshGatewayObject( $init );
		$this->assertEquals( '44444', $gateway->getData_Unstaged_Escaped( 'order_id' ), 'GlobalCollect gateway is not ignoring nonsensical order_id candidates' );

		unset( $_GET['order_id'] );
		//order_id in $SESSION, default GC generation
		$gateway = $this->getFreshGatewayObject( $init );
		$this->assertEquals( '44444', $gateway->getData_Unstaged_Escaped( 'order_id' ), 'GlobalCollect gateway is not recognizing the session order_id' );

		$_POST['order_id'] = '33333';
		//conflicting order_id in $_POST and $SESSION, default GC generation
		$gateway = $this->getFreshGatewayObject( $init );
		$this->assertEquals( '33333', $gateway->getData_Unstaged_Escaped( 'order_id' ), 'GlobalCollect gateway is preferring session data over the $_POST. Session should be secondary.' );

		$init['order_id'] = '22222';
		//conflicting order_id in init data, $_POST and $SESSION, explicit GC generation, batch mode
		$gateway = $this->getFreshGatewayObject( $init, array ( 'order_id_meta' => array ( 'generate' => TRUE ), 'batch_mode' => TRUE, ) );
		$this->assertEquals( $init['order_id'], $gateway->getData_Unstaged_Escaped( 'order_id' ), 'Failed asserting that an extrenally provided order id is being honored in batch mode' );
	}

	/**
	 * Non-exhaustive integration tests to verify that order_id, when in
	 * self-generation mode, won't regenerate until it is told to.
	 * @covers normalizeOrderID
	 * @covers regenerateOrderID
	 */
	function testStickyGeneratedOrderID() {
		$init = $this->initial_vars;
		unset( $init['order_id'] );

		//no order_id from anywhere, explicit generate
		$gateway = $this->getFreshGatewayObject( $init, array ( 'order_id_meta' => array ( 'generate' => TRUE ) ) );
		$this->assertNotNull( $gateway->getData_Unstaged_Escaped( 'order_id' ), 'Generated order_id is null. The rest of this test is broken.' );
		$original_order_id = $gateway->getData_Unstaged_Escaped( 'order_id' );

		$gateway->normalizeOrderID();
		$this->assertEquals( $original_order_id, $gateway->getData_Unstaged_Escaped( 'order_id' ), 'Re-normalized order_id has changed without explicit regeneration.' );

		//this might look a bit strange, but we need to be able to generate valid order_ids without making them stick to anything. 
		$gateway->generateOrderID();
		$this->assertEquals( $original_order_id, $gateway->getData_Unstaged_Escaped( 'order_id' ), 'function generateOrderID auto-changed the selected order ID. Not cool.' );

		$gateway->regenerateOrderID();
		$this->assertNotEquals( $original_order_id, $gateway->getData_Unstaged_Escaped( 'order_id' ), 'Re-normalized order_id has not changed, after explicit regeneration.' );
	}

	/**
	 * Integration test to verify that order_id can be retrieved from
	 * performing an INSERT_ORDERWITHPAYMENT.
	 */
	function testOrderIDRetrieval() {
		$init = $this->getDonorTestData();
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';

		//no order_id from anywhere, explicit generate
		$gateway = $this->getFreshGatewayObject( $init, array ( 'order_id_meta' => array ( 'generate' => FALSE ) ) );
		$this->assertNull( $gateway->getData_Unstaged_Escaped( 'order_id' ), 'Ungenerated order_id is not null. The rest of this test is broken.' );

		$gateway->do_transaction( 'INSERT_ORDERWITHPAYMENT' );

		$this->assertNotNull( $gateway->getData_Unstaged_Escaped( 'order_id' ), 'No order_id was retrieved from INSERT_ORDERWITHPAYMENT' );
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

		$gateway = $this->getFreshGatewayObject( $this->initial_vars );

		$var_map = array(
			'ORDERID' => 'order_id',
			'AMOUNT' => 'amount',
			'CURRENCYCODE' => 'currency_code',
			'LANGUAGECODE' => 'language',
			'COUNTRYCODE' => 'country',
			'MERCHANTREFERENCE' => 'contribution_tracking_id',
			'RETURNURL' => 'returnto', 
			'IPADDRESS' => 'server_ip',
			'ISSUERID' => 'issuer_id',
			'PAYMENTPRODUCTID' => 'payment_product',
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
		
		$this->assertEquals( $var_map, $gateway->getVarMap() );
	}

	public function testLanguageStaging() {
		$options = $this->getDonorTestData( 'NO' );
		$options['payment_method'] = 'cc';
		$options['payment_submethod'] = 'visa';
		$gateway = $this->getFreshGatewayObject( $options );

		$gateway->_stageData();

		$this->assertEquals( $gateway->_getData_Staged( 'language' ), 'no', "'NO' donor's language was inproperly set. Should be 'no'" );
	}

	public function testGCFormLoad() {
		$init = $this->getDonorTestData( 'US' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['ffname'] = 'cc-vmad';

		$assertNodes = array (
			'cc-mc' => array (
				'nodename' => 'input'
			),
			'selected-amount' => array (
				'nodename' => 'span',
				'innerhtml' => '$1.55',
			),
			'state' => array (
				'nodename' => 'select',
				'selected' => 'CA',
			),
		);

		$this->verifyFormOutput( 'TestingGlobalCollectGateway', $init, $assertNodes, true );
	}

}
