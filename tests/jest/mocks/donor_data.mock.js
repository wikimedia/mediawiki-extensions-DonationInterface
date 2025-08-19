module.exports = exports = {
    address: {
        street_address: '1 Montgomery Street',
        city: 'San Francisco',
        state_province: 'California',
        postal_code: '90001',
        country: 'US'
    },
    hasActiveRecurring: true,
    hasInactiveRecurring: true,
    email: 'jwales@example.org',
    name: 'Jimmy Wales',
    donorID: '12345',
    contact_id: '12345',
    checksum: 'random-checksum',
    last_amount_formatted: '$100',
    last_currency: 'USD',
    last_payment_method: 'Credit Card: Visa',
    last_receive_date_formatted: 'June 2, 2025',
    recurringContributions: [
        {
            amount_frequency_key: 'donorportal-recurring-amount-monthly',
            amount_formatted: '$100',
            currency: 'USD',
            payment_method: 'Credit Card: Visa',
            next_sched_contribution_date_formatted: 'September 2, 2025',
            id: '123',
            next_sched_contribution_date: '2025-08-02 00:00:02',
            amount: 10
        }
    ],
    inactiveRecurringContributions: [
        {
            amount_frequency_key: 'donorportal-recurring-amount-monthly',
            amount_formatted: '$100',
            currency: 'USD',
            payment_method: 'Credit Card: Visa',
            last_contribution_date_formatted: 'September 2, 2025',
            restart_key: 'donorportal-restart-monthly',
            hasLastContribution: true,
            id: '125'
        }
    ],
    onetimeContribution: {
        last_amount_formatted: '$100',
        last_currency: 'USD',
        last_payment_method: 'Credit Card: Visa',
        last_receive_date_formatted: 'September 2, 2025',
        id: '123'
    },
    annualFundContributions: [
        {
            receive_date_formatted: '02 March, 2025',
            donation_type_key: 'donorportal-donation-type-monthly',
            amount_formatted: '$5.78',
            currency: 'USD',
            payment_method: 'Credit Card: Visa',
            id: '123'
        },
        {
            receive_date_formatted: '03 March, 2025',
            donation_type_key: 'donorportal-donation-type-annual',
            amount_formatted: '$6.78',
            currency: 'USD',
            payment_method: 'Credit Card: MasterCard',
            id: '124'
        }
    ],
    endowmentContributions: []
};
