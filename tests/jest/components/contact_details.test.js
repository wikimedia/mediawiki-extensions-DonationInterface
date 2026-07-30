/* global describe it expect beforeEach afterEach*/

const VueTestUtils = require( '@vue/test-utils' );
const DonorContactDetails = require(
	'../../../modules/ext.donationInterface.donorPortal/components/DonorContactDetails.vue' );
const contact_details_mock = require( '../mocks/contact_details.mock.js' );

describe( 'Donor contact details component', () => {

	it( 'Renders successfully', () => {
		const wrapper = VueTestUtils.shallowMount( DonorContactDetails, {
			props: contact_details_mock
		} );

		const element = wrapper.find( '.dp-card' );
		expect( element.exists() ).toBe( true );
		expect( element.html() ).toContain( contact_details_mock.name );
		expect( element.html() ).toContain( contact_details_mock.id );
		expect( element.html() ).toContain( contact_details_mock.address.street_address );
		expect( element.html() ).toContain( contact_details_mock.address.city );
		expect( element.html() ).toContain( contact_details_mock.address.state_province );
		expect( element.html() ).toContain( contact_details_mock.address.postal_code );
		expect( element.html() ).toContain( contact_details_mock.address.country );
	} );

	it( 'Falls back to defaults and hides optional fields when props are omitted', () => {
		// No props: exercises the default() factories and the false side of
		// every v-if (no street/city/postal, empty addressLine3, no prefs link).
		const wrapper = VueTestUtils.shallowMount( DonorContactDetails );

		const element = wrapper.find( '.dp-card' );
		expect( element.exists() ).toBe( true );

		// The address section renders only the (empty) name paragraph.
		const addressSection = wrapper.findAll( '.dp-card__section' )[ 0 ];
		expect( addressSection.findAll( 'p' ).length ).toBe( 1 );

		// No email preferences URL means no link.
		expect( wrapper.find( 'a.link' ).exists() ).toBe( false );
	} );

	it( 'Builds addressLine3 from state_province alone', () => {
		const wrapper = VueTestUtils.shallowMount( DonorContactDetails, {
			props: {
				address: { state_province: 'California' },
				emailPreferencesUrl: ''
			}
		} );

		// With only state_province set, the address section holds exactly the
		// name paragraph followed by the addressLine3 paragraph (street, city
		// and postal are all hidden), so index 1 is addressLine3.
		const paragraphs = wrapper.findAll( '.dp-card__section' )[ 0 ].findAll( 'p' );
		expect( paragraphs.length ).toBe( 2 );
		expect( paragraphs[ 1 ].text() ).toBe( 'California' );
	} );

	it( 'Builds addressLine3 from country alone', () => {
		const wrapper = VueTestUtils.shallowMount( DonorContactDetails, {
			props: {
				address: { country: 'US' },
				emailPreferencesUrl: ''
			}
		} );

		const paragraphs = wrapper.findAll( '.dp-card__section' )[ 0 ].findAll( 'p' );
		expect( paragraphs.length ).toBe( 2 );
		expect( paragraphs[ 1 ].text() ).toBe( 'US' );
	} );

} );
