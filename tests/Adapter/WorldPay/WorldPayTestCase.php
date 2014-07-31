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
 * @group WorldPay
 */
class DonationInterface_Adapter_WorldPay_WorldPayTestCase extends DonationInterfaceTestCase {

	/**
	 * @param $name string The name of the test case
	 * @param $data array Any parameters read from a dataProvider
	 * @param $dataName string|int The name or index of the data set
	 */
	function __construct( $name = null, array $data = array(), $dataName = '' ) {
		parent::__construct( $name, $data, $dataName );
		$this->testAdapterClass = 'TestingWorldPayAdapter';
	}

	/**
	 * Just making sure we can instantiate the thing without blowing up completely
	 */
	function testConstruct() {
		$options = $this->getDonorTestData();
		$class = $this->testAdapterClass;

		$_SERVER['REQUEST_URI'] = GatewayFormChooser::buildPaymentsFormURL( 'testytest', array ( 'gateway' => $class::getIdentifier() ) );
		$gateway = $this->getFreshGatewayObject( $options );

		$this->assertInstanceOf( 'TestingWorldPayAdapter', $gateway );
	}

	/**
	 * Test the AntiFraud hooks
	 */
	function testAntiFraudHooks() {
		$options = $this->getDonorTestData( 'US' );
		$options['utm_source'] = "somethingmedia";
		$options['email'] = "somebody@wikipedia.org";

		$gateway = $this->getFreshGatewayObject( $options );

		$gateway->runAntifraudHooks();

		$this->assertEquals( 'reject', $gateway->getValidationAction(), 'Validation action is not as expected' );
		$this->assertEquals( 113, $gateway->getRiskScore(), 'RiskScore is not as expected' );
	}

	/**
	 * Just making sure we can instantiate the thing without blowing up completely
	 */
	function testNeverLog() {
		$options = $this->getDonorTestData();
		$options['cvv'] = '123';
		$class = $this->testAdapterClass;

		$_SERVER['REQUEST_URI'] = GatewayFormChooser::buildPaymentsFormURL( 'testytest', array ( 'gateway' => $class::getIdentifier() ) );
		$gateway = $this->getFreshGatewayObject( $options );

		$this->assertInstanceOf( 'TestingWorldPayAdapter', $gateway );
		$gateway->do_transaction( 'AuthorizePaymentForFraud' );

		$logline = $this->getGatewayLogMatches( $gateway, LOG_INFO, '/Request XML/' );

		$this->assertType( 'string', $logline, "We did not receive exactly one logline back that contains request XML" );
		$this->assertEquals( '1', preg_match( '/Cleaned/', $logline ), 'The logline did not come back marked as "Cleaned".' );
		$this->assertEquals( '0', preg_match( '/CNV/', $logline ), 'The "Cleaned" logline contained CVN data!' );
	}

	function testWorldPayFormLoad() {
		$init = $this->getDonorTestData();
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['ffname'] = 'worldpay';
		$init['currency_code'] = 'EUR';

		$assertNodes = array (
			'selected-amount' => array (
				'nodename' => 'span',
				'innerhtml' => '€1.55',
			),
			'fname' => array(
				'nodename' => 'input',
				'value' => 'Firstname',
			),
			'lname' => array(
				'nodename' => 'input',
				'value' => 'Surname',
			),
			'street' => array(
				'nodename' => 'input',
				'value' => '123 Fake Street',
			),
			'city' => array(
				'nodename' => 'input',
				'value' => 'San Francisco',
			),
			'zip' => array(
				'nodename' => 'input',
				'value' => '94105',
			),
			'country' => array(
				'nodename' => 'input',
				'value' => 'US',
			),
			'emailAdd' => array(
				'nodename' => 'input',
				'value' => '',
			),
			'language' => array(
				'nodename' => 'input',
				'value' => 'en',
			),
			'state' => array(
				'nodename' => 'select',
				'selected' => 'CA',
			),
			'informationsharing' => array (
				'nodename' => 'p',
				'innerhtml' => 'By donating, you are sharing your information with the Wikimedia Foundation, the nonprofit organization that hosts Wikipedia and other Wikimedia projects, and its service providers in the U.S. and elsewhere pursuant to our donor privacy policy. We do not sell or trade your information to anyone. For more information please read <a href="//wikimediafoundation.org/wiki/Donor_policy/en">our donor policy</a>.',
			),
		);

		$this->verifyFormOutput( 'TestingWorldPayGateway', $init, $assertNodes, true );
	}

	function testPaymentFormSubmit() {
		$init = $this->getDonorTestData( 'FR' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['ffname'] = 'worldpay';
		$init['currency_code'] = 'EUR';
		$init['email'] = 'noemailfraudscore@test.org';

		$init['OTT'] = 'SALT123456789';

		$assertNodes = array(
			'headers' => array(
				'Location' => 'https://wikimediafoundation.org/wiki/Thank_You/fr',
			),
		);

		$this->verifyFormOutput( 'TestingWorldPayGateway', $init, $assertNodes, true );
	}

	function testWorldPayFormLoad_FR() {
		$init = $this->getDonorTestData( 'FR' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['ffname'] = 'worldpay';

		$assertNodes = array (
			'selected-amount' => array (
				'nodename' => 'span',
				'innerhtml' => '€1.55',
			),
			'fname' => array (
				'nodename' => 'input',
				'value' => 'Prénom',
			),
			'lname' => array (
				'nodename' => 'input',
				'value' => 'Nom',
			),
			'informationsharing' => array (
				'nodename' => 'p',
				'innerhtml' => 'En faisant ce don, vous acceptez notre politique de confidentialité en matière de donation ainsi que de partager vos données personnelles avec la Fondation Wikipedia et ses prestataires de services situés aux Etats-Unis et ailleurs, dont les lois sur la protection de la vie privée ne sont pas forcement équivalentes aux vôtres.',
			),
		);

		$this->verifyFormOutput( 'TestingWorldPayGateway', $init, $assertNodes, true );
	}

	/**
	 * Make sure Belgian form loads in all of that country's supported languages
	 * @dataProvider belgiumLanguageProvider
	 */
	public function testWorldPayFormLoad_BE( $language ) {
		$init = $this->getDonorTestData( 'BE' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['ffname'] = 'worldpay';
		$init['language'] = $language;

		$assertNodes = array (
			'selected-amount' => array (
				'nodename' => 'span',
				'innerhtml' => '€1.55',
			),
			'fname-label' => array (
				'nodename' => 'label',
				'innerhtml' => wfMessage( 'donate_interface-donor-fname' )->inLanguage( $language )->text(),
			),
			'lname-label' => array (
				'nodename' => 'label',
				'innerhtml' => wfMessage( 'donate_interface-donor-lname' )->inLanguage( $language )->text(),
			),
			'emailAdd-label' => array (
				'nodename' => 'label',
				'innerhtml' => wfMessage( 'donate_interface-donor-email' )->inLanguage( $language )->text(),
			),
			'informationsharing' => array (
				'nodename' => 'p',
				'innerhtml' => wfMessage( 'donate_interface-informationsharing', '.*' )->inLanguage( $language )->text(),
			),
		);

		$this->verifyFormOutput( 'TestingWorldPayGateway', $init, $assertNodes, true );
	}

	/**
	 * Supported languages for Belgium
	 */
	public function belgiumLanguageProvider() {
		return array(
			array( 'nl' ),
			array( 'de' ),
			array( 'fr' ),
		);
	}

	/**
	 * Testing that we can retrieve the cvv_match value and run antifraud on it correctly
	 */
	function testAntifraudCVVMatch() {
		$options = $this->getDonorTestData(); //don't really care: We'll be using the dummy response directly.
		$class = $this->testAdapterClass;

		$gateway = $this->getFreshGatewayObject( $options );
		$gateway->do_transaction( 'AuthorizePaymentForFraud' );

		$this->assertEquals( '1', $gateway->getData_Unstaged_Escaped( 'cvv_result' ), 'cvv_result was not set after AuthorizePaymentForFraud' );
		$this->assertTrue( $gateway->getCVVResult(), 'getCVVResult not passing somebody with a match.' );

		//and now, for fun, test a wrong code.
		$gateway->addData( array ( 'cvv_result' => '2' ), 'response' );
		$this->assertFalse( $gateway->getCVVResult(), 'getCVVResult not failing somebody with garbage.' );
	}

}
