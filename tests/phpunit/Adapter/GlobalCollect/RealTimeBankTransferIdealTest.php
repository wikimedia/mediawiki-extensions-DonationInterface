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
use SmashPig\Core\Configuration;
use SmashPig\Core\Context;

/**
 * 
 * @group Fundraising
 * @group DonationInterface
 * @group GlobalCollect
 * @group RealTimeBankTransfer
 */
class DonationInterface_Adapter_GlobalCollect_RealTimeBankTransferIdealTest extends DonationInterfaceTestCase {
	/**
	 * @var PHPUnit_Framework_MockObject_MockObject
	 */
	protected $bankPaymentProvider;

	public function setUp() {
		parent::setUp();

		$config = Configuration::createForView( 'ingenico' );
		Context::initWithLogger( $config ); // gets torn down in parent

		$this->bankPaymentProvider = $this->getMockBuilder(
			'\SmashPig\PaymentProviders\Ingenico\BankPaymentProvider'
		)->disableOriginalConstructor()->getMock();

		$config->overrideObjectInstance( 'payment-provider/rtbt', $this->bankPaymentProvider );

		$this->bankPaymentProvider->method( 'getBankList' )
			->willReturn( array(
				'Test1234' => 'Test Bank 1234',
				'Test5678' => 'Test Bank 5678',
			) );


		$this->setMwGlobals( array(
			'wgGlobalCollectGatewayEnabled' => true,
			'wgDonationInterfaceAllowedHtmlForms' => array(
				'rtbt-ideal' => array(
					'gateway' => 'globalcollect',
					'payment_methods' => array('rtbt' => 'rtbt_ideal'),
					'countries' => array( '+' => 'NL' ),
					'currencies' => array( '+' => 'EUR' ),
				),
			),
		) );
	}

	/**
	 * Test for ideal form loading
	 */
	public function testGCFormLoad_rtbt_Ideal() {
		$init = $this->getDonorTestData( 'NL' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'rtbt';
		$init['ffname'] = 'rtbt-ideal';

		$assertNodes = array (
			'gross' => array (
				'nodename' => 'input',
				'value' => '1.55',
			),
			'currency' => array (
				'nodename' => 'select',
				'selected' => 'EUR',
			),
			'country' => array (
				'nodename' => 'input',
				'value' => 'NL',
			),
			'issuer_id' => array (
				'innerhtmlmatches' => '/Test Bank 1234/'
			)
		);

		$this->verifyFormOutput( 'GlobalCollectGateway', $init, $assertNodes, true );
	}

	/**
	 * testBuildRequestXmlWithIssuerId21
	 *
	 * Rabobank: 21
	 *
	 * @covers GatewayAdapter::__construct
	 * @covers GatewayAdapter::setCurrentTransaction
	 * @covers GatewayAdapter::buildRequestXML
	 * @covers GatewayAdapter::getData_Unstaged_Escaped
	 */
	public function testBuildRequestXmlWithIssuerId21() {
		
		$optionsForTestData = array(
			'form_name' => 'TwoStepAmount',
			'payment_method' => 'rtbt',
			'payment_submethod' => 'rtbt_ideal',
			'payment_product_id' => 809,
			'issuer_id' => 21,
		);

		//somewhere else?
		$options = $this->getDonorTestData( 'ES' );
		$options = array_merge( $options, $optionsForTestData );
		unset( $options['payment_product_id'] );

		$this->buildRequestXmlForGlobalCollect( $optionsForTestData, $options );
	}

	/**
	 * testBuildRequestXmlWithIssuerId31
	 *
	 * ABN AMRO: 31
	 *
	 * @covers GatewayAdapter::__construct
	 * @covers GatewayAdapter::setCurrentTransaction
	 * @covers GatewayAdapter::buildRequestXML
	 * @covers GatewayAdapter::getData_Unstaged_Escaped
	 */
	public function testBuildRequestXmlWithIssuerId31() {
		
		$optionsForTestData = array(
			'form_name' => 'TwoStepAmount',
			'payment_method' => 'rtbt',
			'payment_submethod' => 'rtbt_ideal',
			'payment_product_id' => 809,
			'issuer_id' => 31,
		);

		//somewhere else?
		$options = $this->getDonorTestData( 'ES' );
		$options = array_merge( $options, $optionsForTestData );
		unset( $options['payment_product_id'] );

		$this->buildRequestXmlForGlobalCollect( $optionsForTestData, $options );
	}

	/**
	 * testBuildRequestXmlWithIssuerId91
	 *
	 * Rabobank: 21
	 *
	 * @covers GatewayAdapter::__construct
	 * @covers GatewayAdapter::setCurrentTransaction
	 * @covers GatewayAdapter::buildRequestXML
	 * @covers GatewayAdapter::getData_Unstaged_Escaped
	 */
	public function testBuildRequestXmlWithIssuerId91() {
		
		$optionsForTestData = array(
			'form_name' => 'TwoStepAmount',
			'payment_method' => 'rtbt',
			'payment_submethod' => 'rtbt_ideal',
			'payment_product_id' => 809,
			'issuer_id' => 21,
		);

		//somewhere else?
		$options = $this->getDonorTestData( 'ES' );
		$options = array_merge( $options, $optionsForTestData );
		unset( $options['payment_product_id'] );

		$this->buildRequestXmlForGlobalCollect( $optionsForTestData, $options );
	}

	/**
	 * testBuildRequestXmlWithIssuerId161
	 *
	 * Van Lanschot Bankiers: 161
	 *
	 * @covers GatewayAdapter::__construct
	 * @covers GatewayAdapter::setCurrentTransaction
	 * @covers GatewayAdapter::buildRequestXML
	 * @covers GatewayAdapter::getData_Unstaged_Escaped
	 */
	public function testBuildRequestXmlWithIssuerId161() {
		
		$optionsForTestData = array(
			'form_name' => 'TwoStepAmount',
			'payment_method' => 'rtbt',
			'payment_submethod' => 'rtbt_ideal',
			'payment_product_id' => 809,
			'issuer_id' => 161,
		);

		//somewhere else?
		$options = $this->getDonorTestData( 'ES' );
		$options = array_merge( $options, $optionsForTestData );
		unset( $options['payment_product_id'] );

		$this->buildRequestXmlForGlobalCollect( $optionsForTestData, $options );
	}

	/**
	 * testBuildRequestXmlWithIssuerId511
	 *
	 * Triodos Bank: 511
	 *
	 * @covers GatewayAdapter::__construct
	 * @covers GatewayAdapter::setCurrentTransaction
	 * @covers GatewayAdapter::buildRequestXML
	 * @covers GatewayAdapter::getData_Unstaged_Escaped
	 */
	public function testBuildRequestXmlWithIssuerId511() {
		
		$optionsForTestData = array(
			'form_name' => 'TwoStepAmount',
			'payment_method' => 'rtbt',
			'payment_submethod' => 'rtbt_ideal',
			'payment_product_id' => 809,
			'issuer_id' => 511,
		);

		//somewhere else?
		$options = $this->getDonorTestData( 'ES' );
		$options = array_merge( $options, $optionsForTestData );
		unset( $options['payment_product_id'] );

		$this->buildRequestXmlForGlobalCollect( $optionsForTestData, $options );
	}

	/**
	 * testBuildRequestXmlWithIssuerId721
	 *
	 * ING: 721
	 *
	 * @covers GatewayAdapter::__construct
	 * @covers GatewayAdapter::setCurrentTransaction
	 * @covers GatewayAdapter::buildRequestXML
	 * @covers GatewayAdapter::getData_Unstaged_Escaped
	 */
	public function testBuildRequestXmlWithIssuerId721() {
		
		$optionsForTestData = array(
			'form_name' => 'TwoStepAmount',
			'payment_method' => 'rtbt',
			'payment_submethod' => 'rtbt_ideal',
			'payment_product_id' => 809,
			'issuer_id' => 721,
		);

		//somewhere else?
		$options = $this->getDonorTestData( 'ES' );
		$options = array_merge( $options, $optionsForTestData );
		unset( $options['payment_product_id'] );

		$this->buildRequestXmlForGlobalCollect( $optionsForTestData, $options );
	}

	/**
	 * testBuildRequestXmlWithIssuerId751
	 *
	 * SNS Bank: 751
	 *
	 * @covers GatewayAdapter::__construct
	 * @covers GatewayAdapter::setCurrentTransaction
	 * @covers GatewayAdapter::buildRequestXML
	 * @covers GatewayAdapter::getData_Unstaged_Escaped
	 */
	public function testBuildRequestXmlWithIssuerId751() {
		
		$optionsForTestData = array(
			'form_name' => 'TwoStepAmount',
			'payment_method' => 'rtbt',
			'payment_submethod' => 'rtbt_ideal',
			'payment_product_id' => 809,
			'issuer_id' => 751,
		);

		//somewhere else?
		$options = $this->getDonorTestData( 'ES' );
		$options = array_merge( $options, $optionsForTestData );
		unset( $options['payment_product_id'] );

		$this->buildRequestXmlForGlobalCollect( $optionsForTestData, $options );
	}

	/**
	 * testBuildRequestXmlWithIssuerId761
	 *
	 * ASN Bank: 761
	 *
	 * @covers GatewayAdapter::__construct
	 * @covers GatewayAdapter::setCurrentTransaction
	 * @covers GatewayAdapter::buildRequestXML
	 * @covers GatewayAdapter::getData_Unstaged_Escaped
	 */
	public function testBuildRequestXmlWithIssuerId761() {
		
		$optionsForTestData = array(
			'form_name' => 'TwoStepAmount',
			'payment_method' => 'rtbt',
			'payment_submethod' => 'rtbt_ideal',
			'payment_product_id' => 809,
			'issuer_id' => 761,
		);

		//somewhere else?
		$options = $this->getDonorTestData( 'ES' );
		$options = array_merge( $options, $optionsForTestData );
		unset( $options['payment_product_id'] );

		$this->buildRequestXmlForGlobalCollect( $optionsForTestData, $options );
	}

	/**
	 * testBuildRequestXmlWithIssuerId771
	 *
	 * RegioBank: 771
	 *
	 * @covers GatewayAdapter::__construct
	 * @covers GatewayAdapter::setCurrentTransaction
	 * @covers GatewayAdapter::buildRequestXML
	 * @covers GatewayAdapter::getData_Unstaged_Escaped
	 */
	public function testBuildRequestXmlWithIssuerId771() {
		
		$optionsForTestData = array(
			'form_name' => 'TwoStepAmount',
			'payment_method' => 'rtbt',
			'payment_submethod' => 'rtbt_ideal',
			'payment_product_id' => 809,
			'issuer_id' => 771,
		);

		//somewhere else?
		$options = $this->getDonorTestData( 'ES' );
		$options = array_merge( $options, $optionsForTestData );
		unset( $options['payment_product_id'] );

		$this->buildRequestXmlForGlobalCollect( $optionsForTestData, $options );
	}

	public function testFormAction() {

		$optionsForTestData = array (
			'payment_method' => 'rtbt',
			'payment_submethod' => 'rtbt_ideal',
			'issuer_id' => 771,
			// Email is required for RTBT.
			'email' => 'nobody@wikimedia.org',
		);

		//somewhere else?
		$options = $this->getDonorTestData( 'ES' );
		$options = array_merge( $options, $optionsForTestData );

		$this->gatewayAdapter = $this->getFreshGatewayObject( $options );

		$this->assertTrue( $this->gatewayAdapter->validatedOK() );

		$this->gatewayAdapter->do_transaction( "INSERT_ORDERWITHPAYMENT" );
		$action = $this->gatewayAdapter->getTransactionDataFormAction();
		$this->assertEquals( "url_placeholder", $action, "The formaction was not populated as expected (ideal)." );
	}

}

