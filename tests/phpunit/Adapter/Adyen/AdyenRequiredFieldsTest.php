<?php

/**
 * @group Fundraising
 * @group DonationInterface
 * @group Adyen
 * @group AdyenCheckout
 * @covers \AdyenCheckoutAdapter
 */
class AdyenRequiredFieldsTest extends BaseAdyenCheckoutTestCase {

	public static function provideRequiredFields(): array {
		return [
			'cc US' => [ 'US', 'cc', null, [
				'country', 'first_name', 'last_name', 'email', 'street_address', 'postal_code'
			] ],
			// Apple Pay collects name, email and address in its own UI
			'apple US' => [ 'US', 'apple', null, [ 'country' ] ],
			'apple CA' => [ 'CA', 'apple', null, [ 'country' ] ],
			'apple GB' => [ 'GB', 'apple', null, [ 'country', 'opt_in' ] ],
			'apple AU' => [ 'AU', 'apple', null, [ 'country' ] ],
			'apple DE' => [ 'DE', 'apple', null, [ 'country', 'opt_in' ] ],
			// Google Pay collects street address, postal code and city in its own UI
			'google US' => [ 'US', 'google', null, [ 'country' ] ],
			'google CA' => [ 'CA', 'google', null, [ 'country', 'state_province' ] ],
			'google GB' => [ 'GB', 'google', null, [ 'country', 'opt_in' ] ],
			'google AU' => [ 'AU', 'google', null, [ 'country', 'state_province' ] ],
			'google DE' => [ 'DE', 'google', null, [ 'country', 'opt_in' ] ],
			// Direct debit does not need name or address
			'dd ach US' => [ 'US', 'dd', 'ach', [ 'country', 'email', 'bank_account_type' ] ],
			'dd GB' => [ 'GB', 'dd', null, [ 'country', 'email', 'bank_account_type', 'opt_in' ] ],
			// SEPA direct debit does not need name or address, other rtbt submethods need name
			'rtbt sepadirectdebit DE' => [ 'DE', 'rtbt', 'sepadirectdebit', [ 'country', 'email', 'opt_in' ] ],
			'rtbt ideal NL' => [ 'NL', 'rtbt', 'rtbt_ideal', [
				'country', 'first_name', 'last_name', 'email', 'opt_in'
			] ],
		];
	}

	/**
	 * @dataProvider provideRequiredFields
	 */
	public function testRequiredFields(
		string $country, string $method, ?string $submethod, array $expected
	): void {
		$gateway = $this->getFreshGatewayObject( $this->getDonorTestData() );
		$knownData = [
			'country' => $country,
			'payment_method' => $method,
			'payment_submethod' => $submethod,
		];
		$this->assertArrayEquals( $expected, $gateway->getRequiredFields( $knownData ) );
	}

	public static function provideFormFields(): array {
		return [
			'cc US' => [ 'US', 'cc', null, [
				'country' => true, 'first_name' => true, 'last_name' => true, 'email' => true,
				'street_address' => true, 'postal_code' => true, 'employer' => 'optional'
			] ],
			'cc CA' => [ 'CA', 'cc', null, [
				'country' => true, 'state_province' => true, 'first_name' => true, 'last_name' => true,
				'email' => true, 'street_address' => true, 'city' => true, 'postal_code' => true
			] ],
			// Apple Pay collects name, email and address in its own UI
			'apple US' => [ 'US', 'apple', null, [ 'country' => true, 'employer' => 'optional' ] ],
			'apple CA' => [ 'CA', 'apple', null, [ 'country' => true ] ],
			'apple GB' => [ 'GB', 'apple', null, [ 'country' => true, 'opt_in' => true ] ],
			'apple AU' => [ 'AU', 'apple', null, [ 'country' => true ] ],
			'apple DE' => [ 'DE', 'apple', null, [ 'country' => true, 'opt_in' => true ] ],
			// Google Pay collects street address, postal code and city in its own UI
			'google US' => [ 'US', 'google', null, [ 'country' => true, 'employer' => 'optional' ] ],
			'google CA' => [ 'CA', 'google', null, [ 'country' => true, 'state_province' => true ] ],
			'google GB' => [ 'GB', 'google', null, [ 'country' => true, 'opt_in' => true ] ],
			'google AU' => [ 'AU', 'google', null, [ 'country' => true, 'state_province' => true ] ],
			'google DE' => [ 'DE', 'google', null, [ 'country' => true, 'opt_in' => true ] ],
			// Direct debit does not need name or address
			'dd ach US' => [ 'US', 'dd', 'ach', [
				'country' => true, 'email' => true, 'bank_account_type' => true, 'employer' => 'optional'
			] ],
			'dd GB' => [ 'GB', 'dd', null, [
				'country' => true, 'email' => true, 'bank_account_type' => true, 'opt_in' => true
			] ],
			// SEPA direct debit does not need name or address, other rtbt submethods need name
			'rtbt sepadirectdebit DE' => [ 'DE', 'rtbt', 'sepadirectdebit', [
				'country' => true, 'email' => true, 'opt_in' => true
			] ],
			'rtbt ideal NL' => [ 'NL', 'rtbt', 'rtbt_ideal', [
				'country' => true, 'first_name' => true, 'last_name' => true, 'email' => true, 'opt_in' => true
			] ],
		];
	}

	/**
	 * Form fields include optional fields, which are shown on the form but not required
	 * @dataProvider provideFormFields
	 */
	public function testFormFields(
		string $country, string $method, ?string $submethod, array $expected
	): void {
		$gateway = $this->getFreshGatewayObject( $this->getDonorTestData() );
		$knownData = [
			'country' => $country,
			'payment_method' => $method,
			'payment_submethod' => $submethod,
		];
		$this->assertArrayEquals( $expected, $gateway->getFormFields( $knownData ), false, true );
	}
}
