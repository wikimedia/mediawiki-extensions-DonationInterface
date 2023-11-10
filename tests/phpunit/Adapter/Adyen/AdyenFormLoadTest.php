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

use SmashPig\PaymentProviders\Adyen\CardPaymentProvider;
use SmashPig\PaymentProviders\Responses\PaymentMethodResponse;

/**
 *
 * @group Fundraising
 * @group DonationInterface
 * @group Adyen
 */
class AdyenFormLoadTest extends BaseAdyenCheckoutTestCase {

	public function setUp(): void {
		parent::setUp();
		$this->cardPaymentProvider = $this->createMock( CardPaymentProvider::class );

		$this->providerConfig->overrideObjectInstance(
			'payment-provider/cc',
			$this->cardPaymentProvider
		);
		$this->cardPaymentProvider->expects( $this->any() )
			->method( 'getPaymentMethods' )
			->willReturn(
				( new PaymentMethodResponse() )
					->setSuccessful( true )
					->setRawResponse( 'blahblahblah' )
			);
	}

	public function testAdyenFormLoad() {
		$init = $this->getDonorTestData( 'US' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';
		$init['gateway'] = 'adyen';

		$assertNodes = [
			'selected-amount' => [
				'nodename' => 'span',
				'innerhtmlmatches' => '/^\s*' .
					str_replace(
						'$',
						'\$',
						Amount::format( 4.55, 'USD', $init['language'] . '_' . $init['country'] )
					) .
					'\s*$/',
			],
			'postal_code' => [
				'nodename' => 'input',
			],
		];

		$this->verifyFormOutput( 'AdyenCheckoutGateway', $init, $assertNodes, true );
	}

	public function testAdyenFormLoad_FR() {
		$init = $this->getDonorTestData( 'FR' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';
		$init['gateway'] = 'adyen';

		$assertNodes = [
			'selected-amount' => [
				'nodename' => 'span',
				'innerhtmlmatches' => '/^\s*' .
					Amount::format( 4.55, 'EUR', $init['language'] . '_' . $init['country'] ) .
					'\s*$/',
			],
			'email' => [
				'nodename' => 'input',
				'value' => 'nobody@wikimedia.org',
			],
			'country' => [
				'nodename' => 'input',
				'value' => 'FR',
			],
		];

		$this->verifyFormOutput( 'AdyenCheckoutGateway', $init, $assertNodes, true );
	}

	/**
	 * Ensure that form loads for Italy
	 */
	public function testAdyenFormLoad_IT() {
		$init = $this->getDonorTestData( 'IT' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';
		$init['gateway'] = 'adyen';

		$assertNodes = [
			'selected-amount' => [
				'nodename' => 'span',
				'innerhtmlmatches' => '/^\s*' .
					Amount::format( 4.55, 'EUR', $init['language'] . '_' . $init['country'] ) .
					'\s*$/',
			],
			'email' => [
				'nodename' => 'input',
				'placeholder' => wfMessage( 'donate_interface-donor-email' )->inLanguage( 'it' )->text(),
			],
			'informationsharing' => [
				'nodename' => 'p',
				'innerhtmlmatches' => '~' . wfMessage( 'donate_interface-informationsharing', '.*' )
						->inLanguage( 'it' )
						->text() . '~',
			],
			'country' => [
				'nodename' => 'input',
				'value' => 'IT',
			],
		];

		$this->verifyFormOutput( 'AdyenCheckoutGateway', $init, $assertNodes, true );
	}

	/**
	 * Make sure Belgian form loads in all of that country's supported languages
	 * @dataProvider belgiumLanguageProvider
	 */
	public function testAdyenFormLoad_BE( $language ) {
		$init = $this->getDonorTestData( 'BE' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';
		$init['language'] = $language;
		$init['gateway'] = 'adyen';

		$assertNodes = [
			'selected-amount' => [
				'nodename' => 'span',
				'innerhtmlmatches' => '/^\s*' .
					Amount::format( 4.55, 'EUR', $init['language'] . '_' . $init['country'] ) .
					'\s*$/',
			],
			'email' => [
				'nodename' => 'input',
				'placeholder' => wfMessage( 'donate_interface-donor-email' )->inLanguage( $language )->text(),
			],
			'informationsharing' => [
				'nodename' => 'p',
				'innerhtmlmatches' => '~' . wfMessage( 'donate_interface-informationsharing', '.*' )->inLanguage(
						$language
					)->text() . '~',
			],
			'country' => [
				'nodename' => 'input',
				'value' => 'BE',
			],
		];

		$this->verifyFormOutput( 'AdyenCheckoutGateway', $init, $assertNodes, true );
	}

	/**
	 * Make sure Canadian CC form loads in English and French
	 * @dataProvider canadaLanguageProvider
	 */
	public function testAdyenFormLoad_CA( $language ) {
		$init = $this->getDonorTestData( 'CA' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';
		$init['language'] = $language;
		$init['gateway'] = 'adyen';

		$assertNodes = [
			'selected-amount' => [
				'nodename' => 'span',
				'innerhtmlmatches' => '/^\s*' .
					str_replace(
						'$',
						'\$',
						Amount::format( 4.55, 'CAD', $init['language'] . '_' . $init['country'] )
					) .
					'\s*$/',
			],
			'email' => [
				'nodename' => 'input',
				'placeholder' => wfMessage( 'donate_interface-donor-email' )->inLanguage( $language )->text(),
			],
			'informationsharing' => [
				'nodename' => 'p',
				'innerhtmlmatches' => '~' . wfMessage( 'donate_interface-informationsharing', '.*' )->inLanguage(
						$language
					)->text() . '~',
			],
			'state_province' => [
				'nodename' => 'select',
				'selected' => 'SK',
			],
			'postal_code' => [
				'nodename' => 'input',
				'value' => $init['postal_code'],
			],
			'country' => [
				'nodename' => 'input',
				'value' => 'CA',
			],
		];

		$this->verifyFormOutput( 'AdyenCheckoutGateway', $init, $assertNodes, true );
		$this->verifyFormOutput( 'AdyenCheckoutGateway', $init, $assertNodes, true );
	}
}
