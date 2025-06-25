const VueTestUtils = require('@vue/test-utils');
const ActiveRecurringContribution = require('../../../modules/ext.donationInterface.donorPortal/components/ActiveRecurringContribution.vue');

describe('Donor contact details component', () => {
    const contribution_mock = {
        amount_frequency_key: 'donorportal-recurring-amount-monthly',
        amount_formatted: '$100',
        currency: 'USD',
        payment_method: 'Credit Card: Visa',
        next_sched_contribution_date_formatted: 'September 2, 2025',
        id: 123
    };
    it('Renders successfully', () => {
        const wrapper = VueTestUtils.shallowMount(ActiveRecurringContribution, {
            props: {
                contribution: contribution_mock
            }
        });

        const element = wrapper.find('.donorportal-recurring-contribution');
        expect(element.exists()).toBe(true);
        expect(element.html()).toContain(contribution_mock.amount_frequency_key);
        expect(element.html()).toContain(contribution_mock.amount_formatted);
        expect(element.html()).toContain(contribution_mock.currency);
        expect(element.html()).toContain(contribution_mock.payment_method);
        expect(element.html()).toContain(contribution_mock.next_sched_contribution_date_formatted);
        expect(element.html()).toContain(`<a href="/cancel/${contribution_mock.id}"> donorportal-recurring-cancel </a>`);
        expect(element.html()).toContain(`<a href="/pause/${contribution_mock.id}"> donorportal-recurring-pause </a>`);
    });

});
