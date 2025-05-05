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
			'showLogin'
		];
	}
}
