/* global global describe it expect beforeEach afterEach*/

const VueTestUtils = require( '@vue/test-utils' );
const FooterComponent = require( '../../../modules/ext.donationInterface.donorPortal/components/FooterComponent.vue' );

describe( 'Footer Component', () => {

	it( 'Renders successfully', () => {
		const wrapper = VueTestUtils.shallowMount( FooterComponent );

        const element = wrapper.find( '.footer-legal' );
		expect( element.exists() ).toBe( true );

        const questions = wrapper.find( '#donor-portal-questions' );
        expect( questions.exists() ).toBe( true );
        expect( questions.text() ).toBe( 'emailpreferences-footer-questions_email' );

        const learnMore = wrapper.find( '#donor-portal-learn-more' );
        expect( learnMore.exists() ).toBe( true );
        expect( learnMore.text() ).toBe( 'emailpreferences-footer-learn_more' );

        const license = wrapper.find( '#donor-portal-license' );
        expect( license.exists() ).toBe( true );
        expect( license.text() ).toBe( 'emailpreferences-footer-text_license' );
	} );

} );
