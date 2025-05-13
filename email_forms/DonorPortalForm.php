<?php

class DonorPortalForm extends EmailForm {
	protected function getValidParams(): array {
		return [
			'address',
			'annualFundContributions',
			'endowmentContributions',
			'name',
			'donorID',
			'email',
			'hasActiveRecurring',
			'hasInactiveRecurring',
			'recurringContributions',
			'showLogin',
			'last_amount',
			'last_amount_formatted',
			'last_currency',
			'last_payment_method',
			'last_receive_date_formatted',
		];
	}
}
