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
class HandlebarsFormTest extends DonationInterfaceTestCase {
	protected $form;
	protected $adapter;

	public function setUp() {
		$this->adapter = new TestingGenericAdapter();
		$this->adapter->addRequestData( array(
			'amount' => '12',
			'currency_code' => 'EUR'
		) );

		parent::setUp();
	}

	public function formCases() {
		return array(
			array( 'empty', '/^$/' ),
			array( 'foo', '/FOO/' ),
			array( 'currency', '/EUR/' ),
		);
	}

	/**
	 * Render a few simple handlebars files and match the output
	 *
	 * @dataProvider formCases
	 */
	public function testRendering( $name, $regexp ) {
		$this->setMwGlobals( array(
			'wgDonationInterfaceTemplate' => __DIR__ . "/data/handlebars/{$name}.handlebars",
		) );
		$this->form = new Gateway_Form_Handlebars( $this->adapter );
		$html = $this->form->getForm();

		$this->assertRegExp( $regexp, $html );
	}

	/**
	 * @expectedException Exception
	 * @expectedExceptionMessage Template file unavailable
	 */
	public function testNoTemplateFile() {
		$this->setMwGlobals( array(
			'wgDonationInterfaceTemplate' => __DIR__ . "/data/handlebars/DONOTCREATE.handlebars",
		) );
		$this->form = new Gateway_Form_Handlebars( $this->adapter );
		// Suppress the error cos: we know.
		$html = @$this->form->getForm();

		$this->fail( 'I\'m not dead yet!' );
	}
}
