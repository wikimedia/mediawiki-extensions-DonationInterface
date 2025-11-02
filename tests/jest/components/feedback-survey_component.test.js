/* global global describe it expect beforeEach afterEach*/

const VueTestUtils = require( '@vue/test-utils' );
const FeedbackSurveyComponent = require( '../../../modules/ext.donationInterface.donorPortal/components/FeedbackSurveyComponent.vue' );

describe( 'Donor feedback survey component', () => {
    it( 'Renders successfully', () => {
        const wrapper = VueTestUtils.shallowMount( FeedbackSurveyComponent );

        const element = wrapper.find( '.cdx-message__content' );
        expect( element.exists() ).toBe( true );
		expect( element.html() ).toContain( 'donorportal-feedbackrequest' );
		expect( element.html() ).toContain( 'donorportal-feedbacklink' );
    } );

} );
