/* global global describe it expect beforeEach afterEach*/

const VueTestUtils = require( '@vue/test-utils' );
const { when } = require( 'jest-when' );

const HomeView = require( '../../../modules/ext.donationInterface.donorPortal/views/Home.vue' );
const DonorDataMock = require( '../mocks/donor_data.mock.js' );

describe( 'Home view', () => {
    const HomeDataMock = {
        result: DonorDataMock
    };

    beforeEach( () => {
        when( global.mw.config.get ).calledWith( 'donorData' ).mockReturnValue( HomeDataMock.result );
        when( global.mw.config.get ).calledWith( 'help_email' ).mockReturnValue( 'lorem@ipsum.co' );
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
        expect( element.html() ).toContain( HomeDataMock.result.address.street_address );
        expect( element.html() ).toContain( HomeDataMock.result.address.city );
        expect( element.html() ).toContain( HomeDataMock.result.address.country );
        expect( element.html() ).toContain( HomeDataMock.result.name );
        expect( element.html() ).toContain( HomeDataMock.result.email );
        expect( element.html() ).toContain( HomeDataMock.result.donorID );
        expect( element.findAll( '.dp-card__appeal.is-recurring' ).length ).toBe( HomeDataMock.result.recurringContributions.length );
        expect( element.findAll( '.dp-card__appeal.is-lapsed' ).length ).toBe( HomeDataMock.result.inactiveRecurringContributions.length );
        expect( element.find( '.donorportal-recent-donation' ).exists ).not.toBe( true );
        expect( element.findAll( '.donorportal-donations-table-row' ).length ).toBe( HomeDataMock.result.annualFundContributions.length + HomeDataMock.result.endowmentContributions.length );
    } );

    it( 'Renders the most recent one time payment when no active or cancelled recurring is on record', async () => {
        const summary = HomeDataMock.result;
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
        expect( element.html() ).toContain( HomeDataMock.result.address.street_address );
        expect( element.html() ).toContain( HomeDataMock.result.address.city );
        expect( element.html() ).toContain( HomeDataMock.result.address.country );
        expect( element.html() ).toContain( HomeDataMock.result.name );
        expect( element.html() ).toContain( HomeDataMock.result.email );
        expect( element.html() ).toContain( HomeDataMock.result.donorID );
        expect( element.findAll( '.donorportal-recurring-contribution' ).length ).toBe( 0 );
        expect( element.findAll( '.dp-card__appeal.is-recurring' ).length ).toBe( 0 );
        expect( element.find( '.donorportal-recent-donation' ).exists() ).toBe( true );
    } );
} );
