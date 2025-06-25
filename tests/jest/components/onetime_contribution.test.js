const VueTestUtils = require('@vue/test-utils');
const OnetimeContribution = require('../../../modules/ext.donationInterface.donorPortal/components/OnetimeContribution.vue');

describe('Donor contact details component', () => {
    const contribution_mock = {
        last_amount_formatted: '$100',
        last_currency: 'USD',
        last_payment_method: 'Credit Card: Visa',
        last_receive_date_formatted: 'September 2, 2025',
        id: '123'
    };
    it('Renders successfully', () => {
        const wrapper = VueTestUtils.shallowMount(OnetimeContribution, {
            props: {
                contribution: contribution_mock
            }
        });

        const element = wrapper.find('.donorportal-recent-donation');
        expect(element.exists()).toBe(true);
        expect(element.html()).toContain(contribution_mock.last_amount_formatted);
        expect(element.html()).toContain(contribution_mock.last_currency);
        expect(element.html()).toContain(contribution_mock.last_payment_method);
        expect(element.html()).toContain(contribution_mock.last_receive_date_formatted);
    });

});
