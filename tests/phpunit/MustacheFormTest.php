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
class MustacheFormTest extends DonationInterfaceTestCase {
	protected $form;
	protected $adapter;
	protected $outputPage;
	protected $gatewayPage;

	protected function setUp(): void {
		$this->outputPage = $this->getMockBuilder( OutputPage::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'parseAsContent' ] )
			->getMock();

		$this->gatewayPage = new TestingGatewayPage();

		RequestContext::getMain()->setOutput( $this->outputPage );

		$req = new TestingRequest();
		RequestContext::getMain()->setRequest( $req );

		$this->setMwGlobals( [
			'wgTitle' => Title::newFromText( 'nonsense is apparently fine' )
		] );

		parent::setUp();

		$this->adapter = new TestingGenericAdapter();
		$this->adapter->addRequestData( [
			'amount' => '12',
			'currency' => 'EUR',
		] );
	}

	public static function formCases() {
		return [
			[ 'empty', '/^$/' ],
			[ 'foo', '/FOO/' ],
			[ 'currency', '/EUR/' ],
		];
	}

	/**
	 * Render a few simple Mustache files and match the output
	 *
	 * @dataProvider formCases
	 */
	public function testRendering( $name, $regexp ) {
		$this->overrideConfigValues( [
			'DonationInterfaceTemplate' => __DIR__ . "/data/mustache/{$name}.mustache",
		] );
		$this->form = new Gateway_Form_Mustache();
		$this->form->setGateway( $this->adapter );
		$this->form->setGatewayPage( $this->gatewayPage );
		$html = $this->form->getForm();

		$this->assertRegExpTemp( $regexp, $html );
	}

	public function testNoTemplateFile() {
		$this->overrideConfigValues( [
			'DonationInterfaceTemplate' => __DIR__ . "/data/mustache/DONOTCREATE.mustache",
		] );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Template file unavailable' );
		$this->form = new Gateway_Form_Mustache();
		$this->form->setGateway( $this->adapter );
		$this->form->setGatewayPage( $this->gatewayPage );
		$this->form->getForm();
	}

	/**
	 * Transclude an appeal
	 * @requires PHPUnit 4.0
	 */
	public function testAppealRendering() {
		$this->overrideConfigValues( [
			'DonationInterfaceTemplate' => __DIR__ . "/data/mustache/appeal.mustache",
			'DonationInterfaceAppealWikiTemplate' => 'JimmySezPleeeeeze/$appeal/$language',
		] );

		$this->outputPage->expects( $this->once() )
			->method( 'parseAsContent' )
			->with( '{{JimmySezPleeeeeze/JimmyQuote/en}}' )
			->willReturn( '<p>This is the template text</p>' );

		$this->form = new Gateway_Form_Mustache();
		$this->form->setGateway( $this->adapter );
		$this->form->setGatewayPage( $this->gatewayPage );
		$html = $this->form->getForm();

		$this->assertEquals( "<p>This is the template text</p>\n", $html );
	}

	/**
	 * Override the transcluded appeal on the query string
	 * @requires PHPUnit 4.0
	 */
	public function testOverrideAppeal() {
		$this->overrideConfigValues( [
			'DonationInterfaceTemplate' => __DIR__ . "/data/mustache/appeal.mustache",
			'DonationInterfaceAppealWikiTemplate' => 'JimmySezPleeeeeze/$appeal/$language',
		] );

		$this->adapter->addRequestData( [ 'appeal' => 'differentAppeal' ] );

		$this->outputPage->expects( $this->once() )
			->method( 'parseAsContent' )
			->with( '{{JimmySezPleeeeeze/differentAppeal/en}}' );

		$this->form = new Gateway_Form_Mustache();
		$this->form->setGateway( $this->adapter );
		$this->form->setGatewayPage( $this->gatewayPage );
		$this->form->getForm();
	}

	/**
	 * Same as above, but don't let any shady characters in
	 * @requires PHPUnit 4.0
	 */
	public function testSanitizeOverrideAppeal() {
		$this->overrideConfigValues( [
			'DonationInterfaceTemplate' => __DIR__ . "/data/mustache/appeal.mustache",
			'DonationInterfaceAppealWikiTemplate' => 'JimmySezPleeeeeze/$appeal/$language',
		] );

		$this->adapter->addRequestData( [
			'appeal' => '}}<script>alert("all your base are belong to us");</script>{{',
		] );

		$this->outputPage->expects( $this->once() )
			->method( 'parseAsContent' )
			->with( '{{JimmySezPleeeeeze/scriptalertallyourbasearebelongtousscript/en}}' );

		$this->form = new Gateway_Form_Mustache();
		$this->form->setGateway( $this->adapter );
		$this->form->setGatewayPage( $this->gatewayPage );
		$this->form->getForm();
	}

	/**
	 * Test rendering l10n with parameters
	 * @dataProvider belgiumLanguageProvider
	 */
	public function testL10nParams( $language ) {
		$this->overrideConfigValues( [
			'DonationInterfaceTemplate' => __DIR__ . "/data/mustache/l10n.mustache",
		] );
		$this->setLanguage( $language );
		$this->adapter->addRequestData( [ 'language' => $language ] );
		$this->form = new Gateway_Form_Mustache();
		$this->form->setGateway( $this->adapter );
		$this->form->setGatewayPage( $this->gatewayPage );
		$html = $this->form->getForm();
		$expected = htmlspecialchars(
			wfMessage( 'donate_interface-bigamount-error', '12.00', 'EUR', 'donor-support@worthyfoundation.org', 10000 )->text()
		);
		$this->assertEquals( $expected, trim( $html ) );
	}

	/**
	 * B/C: assertRegExp() is renamed in PHPUnit 9.x+
	 * @param string $pattern
	 * @param string $string
	 */
	protected function assertRegExpTemp( $pattern, $string ) {
		$method = method_exists( $this, 'assertMatchesRegularExpression' ) ?
		'assertMatchesRegularExpression' : 'assertRegExp';
		$this->$method( $pattern, $string );
	}
}
