const VueTestUtils = require('@vue/test-utils');
const GreetingComponent = require('../../../modules/ext.donationInterface.donorPortal/components/GreetingComponent.vue');

describe('Donor contact details component', () => {
    const contact_details_mock = {
            name: "Jimmy Wales"
    };
    it('Renders successfully', () => {
        const wrapper = VueTestUtils.shallowMount(GreetingComponent, {
            props: {
                ...contact_details_mock
            }
        });

        const element = wrapper.find('.greeting');
        expect(element.exists()).toBe(true);
        expect(element.html()).toContain(`donorportal-greeting:[${contact_details_mock.name}]`);
        expect(element.html()).toContain("donorportal-boldtext");
        expect(element.html()).toContain("donorportal-smalltext");

    });

});
