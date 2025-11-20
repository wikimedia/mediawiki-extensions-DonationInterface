module.exports = exports = {
	recurring: {
		amount_frequency_key: 'donorportal-recurring-amount-monthly',
		amount_formatted: '$100',
		currency_symbol: '$',
		currency: 'USD',
		frequency_unit: 'month',
		payment_method: 'Credit Card: Visa',
		next_sched_contribution_date_formatted: 'September 2, 2025',
		next_sched_contribution_date: '2025-08-02 00:00:02',
		last_contribution_date_formatted: 'September 2, 2025',
		hasLastContribution: true,
		lastContributionDate: true,
		amount: 10,
		id: '125',
		min_amount: 10,
		can_modify: true
	},
	recurring_non_usd: {
		amount_frequency_key: 'donorportal-recurring-amount-yearly',
		amount_formatted: 'MX$100',
		currency_symbol: 'MX$',
		currency: 'MXN',
		frequency_unit: 'year',
		payment_method: 'Credit Card: Visa',
		next_sched_contribution_date_formatted: 'September 2, 2025',
		next_sched_contribution_date: '2025-08-02 00:00:02',
		last_contribution_date_formatted: 'September 2, 2025',
		hasLastContribution: true,
		lastContributionDate: true,
		amount: 10,
		id: '125',
		min_amount: 10,
		can_modify: true,
		donation_rules: {
			INR: {
				min: 10,
				max: 14000
			}
		}
	},
	inactive_recurring: {
		amount_frequency_key: 'donorportal-recurring-amount-monthly',
		amount_formatted: '$100',
		currency: 'USD',
		frequency_unit: 'month',
		payment_method: 'Credit Card: Visa',
		last_contribution_date_formatted: 'September 2, 2025',
		restart_key: 'donorportal-restart-monthly',
		hasLastContribution: true,
		lastContributionDate: true,
		id: '125'
	},
	onetime: {
		hasLastContribution: false,
		lastContributionDate: false,
		last_amount_formatted: '$100',
		last_currency: 'USD',
		last_payment_method: 'Credit Card: Visa',
		last_receive_date_formatted: 'September 2, 2025'
	}
};
