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
 * @group Fundraising
 * @group DonationInterface
 * @group GatewayPage
 */
class GatewayPageTest extends DonationInterfaceTestCase {

	protected $page;
	protected $adapter;

	public function setUp() {
		$this->page = new TestingGatewayPage();
		$this->adapter = new TestingGenericAdapter();
		$this->adapter->addRequestData( array(
			'amount' => '200',
			'currency_code' => 'BBD' ) );
		$this->adapter->errorsForRevalidate[0] = array( 'currency_code' => 'blah' );
		$this->adapter->errorsForRevalidate[1] = array();
		$this->page->adapter = $this->adapter;
		TestingGenericAdapter::$fakeGlobals = array ( 'FallbackCurrency' => 'USD' );
		parent::setUp();
	}

	public function testFallbackWithNotification() {
		TestingGenericAdapter::$fakeGlobals['NotifyOnConvert'] = true;

		$this->page->validateForm();

		$this->assertTrue( $this->adapter->validatedOK() );

		$manualErrors = $this->adapter->getManualErrors();
		$msg = $this->page->msg( 'donate_interface-fallback-currency-notice', 'USD' )->text();
		$this->assertEquals( $msg, $manualErrors['general'] );
		$this->assertEquals( 100, $this->adapter->getData_Unstaged_Escaped( 'amount' ) );
		$this->assertEquals( 'USD', $this->adapter->getData_Unstaged_Escaped( 'currency_code' ) );
	}

	public function testFallbackIntermediateConversion() {
		TestingGenericAdapter::$fakeGlobals['FallbackCurrency'] = 'OMR';
		TestingGenericAdapter::$fakeGlobals['NotifyOnConvert'] = true;

		$this->page->validateForm();

		$manualErrors = $this->adapter->getManualErrors();
		$msg = $this->page->msg( 'donate_interface-fallback-currency-notice', 'OMR' )->text();
		$this->assertEquals( $msg, $manualErrors['general'] );
		$this->assertEquals( 38, $this->adapter->getData_Unstaged_Escaped( 'amount' ) );
		$this->assertEquals( 'OMR', $this->adapter->getData_Unstaged_Escaped( 'currency_code' ) );
	}

	public function testFallbackWithoutNotification() {
		TestingGenericAdapter::$fakeGlobals['NotifyOnConvert'] = false;

		$this->page->validateForm();

		$this->assertTrue( $this->adapter->validatedOK() );

		$manualErrors = $this->adapter->getManualErrors();
		$this->assertEquals( null, $manualErrors['general'] );
		$this->assertEquals( 100, $this->adapter->getData_Unstaged_Escaped( 'amount' ) );
		$this->assertEquals( 'USD', $this->adapter->getData_Unstaged_Escaped( 'currency_code' ) );
	}

	public function testFallbackAlwaysNotifiesIfOtherErrors() {
		TestingGenericAdapter::$fakeGlobals['NotifyOnConvert'] = false;
		$this->adapter->errorsForRevalidate[1] = array( 'amount' => 'bad amount' );

		$this->page->validateForm();

		$manualErrors = $this->adapter->getManualErrors();
		$msg = $this->page->msg( 'donate_interface-fallback-currency-notice', 'USD' )->text();
		$this->assertEquals( $msg, $manualErrors['general'] );
		$this->assertEquals( 100, $this->adapter->getData_Unstaged_Escaped( 'amount' ) );
		$this->assertEquals( 'USD', $this->adapter->getData_Unstaged_Escaped( 'currency_code' ) );
	}

	public function testNoFallbackForSupportedCurrency() {
		$this->adapter->errorsForRevalidate[0] = array( 'address' => 'blah' );

		$this->page->validateForm();

		$manualErrors = $this->adapter->getManualErrors();
		$this->assertEquals( null, $manualErrors['general'] );
		$this->assertEquals( 200, $this->adapter->getData_Unstaged_Escaped( 'amount' ) );
		$this->assertEquals( 'BBD', $this->adapter->getData_Unstaged_Escaped( 'currency_code' ) );
	}
}
