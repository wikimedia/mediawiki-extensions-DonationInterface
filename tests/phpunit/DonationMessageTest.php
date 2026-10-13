<?php

/**
 * @group DonationInterface
 * @group Messages
 * @group ComboWiki
 * @covers \MessageUtils
 */
class DonationMessageTest extends MediaWikiIntegrationTestCase {

	public function testGetCountrySpecificMessage() {
		$actual = MessageUtils::getCountrySpecificMessage(
			'donate_interface-donor-fiscal_number',
			'BR',
			'pt'
		);
		$expected = wfMessage( 'donate_interface-donor-fiscal_number-br' )
						->inLanguage( 'pt' )
						->text();
		$this->assertEquals( $expected, $actual, 'Not using the country specific message' );
	}

	public function testTranslateValidationRulesAddsRequiredFieldMessage(): void {
		$rules = [
			'first_name' => [
				[ 'required' => true, 'messageKey' => 'donate_interface-error-msg-first_name' ],
			],
		];

		$translated = MessageUtils::translateValidationRules( $rules, 'US', 'en' );

		$this->assertSame( 'Please enter your first name', $translated['first_name'][0]['message'] );
	}

	public function testTranslateValidationRulesUsesCountrySpecificFiscalNumberMessages(): void {
		// The shape GatewayAdapter::getClientSideValidationRules() returns for a Brazilian card donation
		$rules = [
			'fiscal_number' => [
				[ 'required' => true, 'messageKey' => 'donate_interface-error-msg-fiscal_number' ],
				[
					'pattern' => '^[^0-9]*([0-9][^0-9]*){11,14}$',
					'messageKey' => 'donate_interface-error-msg-invalid-fiscal_number',
				],
			],
		];

		$translated = MessageUtils::translateValidationRules( $rules, 'BR', 'en' );

		// The -br variants are used, and the pattern is passed through unchanged
		$this->assertSame( 'Please enter your CPF/CNPJ', $translated['fiscal_number'][0]['message'] );
		$this->assertSame( 'Please correct the errors in your CPF/CNPJ', $translated['fiscal_number'][1]['message'] );
		$this->assertSame( '^[^0-9]*([0-9][^0-9]*){11,14}$', $translated['fiscal_number'][1]['pattern'] );
	}
}
