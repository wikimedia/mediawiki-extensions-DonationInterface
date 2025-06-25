const VueTestUtils = require('@vue/test-utils');
const DonorContactDetails = require('../../../modules/ext.donationInterface.donorPortal/components/DonorContactDetails.vue');

describe('Donor contact details component', () => {
    const contact_details_mock = {
            name: "Jimmy Wales",
            id: "1",
            email: "jwales@examples.org",
            address: {
                street_address: "1 Montgomery Street",
				city: "San Francisco",
				state_province: "California",
				postal_code: "90001",
				country: "US"
            }
    };
    it('Renders successfully', () => {
        const wrapper = VueTestUtils.shallowMount(DonorContactDetails, {
            props: {
                ...contact_details_mock
            }
        });

        const element = wrapper.find('.contact-details');
        expect(element.exists()).toBe(true);
        expect(element.html()).toContain(contact_details_mock.name);
        expect(element.html()).toContain(contact_details_mock.id);
        expect(element.html()).toContain(contact_details_mock.address.street_address);
        expect(element.html()).toContain(contact_details_mock.address.city);
        expect(element.html()).toContain(contact_details_mock.address.state_province);
        expect(element.html()).toContain(contact_details_mock.address.postal_code);
        expect(element.html()).toContain(contact_details_mock.address.country);
    });

});
