/* global global describe it expect beforeEach afterEach*/

const VueTestUtils = require( '@vue/test-utils' );
const { when } = require( 'jest-when' );

const HomeView = require( '../../../modules/ext.donationInterface.donorPortal/views/Home.vue' );
const DonorDataMock = require( '../mocks/donor_data.mock.js' );

describe( 'Home view', () => {
	beforeEach( () => {
		when( global.mw.config.get ).calledWith( 'donorData' ).mockReturnValue( DonorDataMock );
		when( global.mw.config.get ).calledWith( 'help_email' ).mockReturnValue( 'lorem@ipsum.co' );
		when( global.mw.config.get ).calledWith( 'emailPreferencesUrl' ).mockReturnValue( 'https://emailprefs.wiki' );
	} );

	it( 'Home view renders the donor data from config', async () => {
		const wrapper = VueTestUtils.mount( HomeView, {
			global: {
				mocks: {
					$route: {
						query: {
							checksum: DonorDataMock.checksum,
							contact_id: DonorDataMock.contact_id
						}
					}
				}
			}
		} );
		const element = wrapper.find( '.dp-dashboard' );
		expect( element.exists() ).toBe( true );
		expect( element.html() ).toContain( DonorDataMock.address.street_address );
		expect( element.html() ).toContain( DonorDataMock.address.city );
		expect( element.html() ).toContain( DonorDataMock.address.country );
		expect( element.html() ).toContain( DonorDataMock.name );
		expect( element.html() ).toContain( DonorDataMock.email );
		expect( element.html() ).toContain( DonorDataMock.donorID );
		expect( element.findAll( '.dp-card__appeal.is-recurring' ).length ).toBe( DonorDataMock.recurringContributions.length );
		expect( element.findAll( '.dp-card__appeal.is-lapsed' ).length ).toBe( DonorDataMock.inactiveRecurringContributions.length );
		expect( element.find( '.donorportal-recent-donation' ).exists ).not.toBe( true );
		expect( element.findAll( '.donorportal-donations-table-row' ).length ).toBe( DonorDataMock.annualFundContributions.length + DonorDataMock.endowmentContributions.length );
	} );

	it( 'Renders the most recent one time payment when no active or cancelled recurring is on record', async () => {
		const summary = DonorDataMock;
		summary.recurringContributions = [];
		summary.inactiveRecurringContributions = [];
		when( global.mw.config.get ).calledWith( 'donorData' ).mockReturnValue( summary );

		const wrapper = VueTestUtils.mount( HomeView, {
			global: {
				mocks: {
					$route: {
						query: {
							checksum: DonorDataMock.checksum,
							contact_id: DonorDataMock.contact_id
						}
					}
				}
			}
		} );

		await VueTestUtils.flushPromises();

		const element = wrapper.find( '.dp-dashboard' );
		expect( element.exists() ).toBe( true );
		expect( element.html() ).toContain( DonorDataMock.address.street_address );
		expect( element.html() ).toContain( DonorDataMock.address.city );
		expect( element.html() ).toContain( DonorDataMock.address.country );
		expect( element.html() ).toContain( DonorDataMock.name );
		expect( element.html() ).toContain( DonorDataMock.email );
		expect( element.html() ).toContain( DonorDataMock.donorID );
		expect( element.findAll( '.donorportal-recurring-contribution' ).length ).toBe( 0 );
		expect( element.findAll( '.dp-card__appeal.is-recurring' ).length ).toBe( 0 );
		expect( element.find( '.donorportal-recent-donation' ).exists() ).toBe( true );
	} );
} );
