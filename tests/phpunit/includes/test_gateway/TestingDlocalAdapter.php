<?php

/**
 * TestingDlocalAdapter
 */
class TestingDlocalAdapter extends DlocalAdapter {
	use TTestingAdapter;

	public function definePaymentMethods(): void {
		parent::definePaymentMethods();
		// we add a test bank_code for the cash and bank transfer payment method tests
		$this->payment_submethods['test_cash_payment_method'] = [
			'bank_code' => 'XX',
		];
	}
}
