/* global global describe it expect beforeEach afterEach*/

const VueTestUtils = require( '@vue/test-utils' );
const { when } = require( 'jest-when' );
const HomeView = require( '../../../modules/ext.donationInterface.donorPortal/views/Home.vue' );

describe( 'Home view', () => {
    const HomeDataMock = {
        result: {
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
                    id: '123'
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
        }
    };

    beforeEach( () => {
        when( global.mw.config.get ).calledWith( 'donorData' ).mockReturnValue( HomeDataMock.result );
    } );

    it( 'Home view renders the donor data from config', async () => {
        const wrapper = VueTestUtils.mount( HomeView, {
            global: {
                mocks: {
                    $route: {
                        query: {
                            checksum: 1,
                            contact_id: 203
                        }
                    }
                }
            }
        } );
        const element = wrapper.find( '.donorportal-home' );
        expect( element.exists() ).toBe( true );
        expect( element.html() ).toContain( HomeDataMock.result.address.street_address );
        expect( element.html() ).toContain( HomeDataMock.result.address.city );
        expect( element.html() ).toContain( HomeDataMock.result.address.country );
        expect( element.html() ).toContain( HomeDataMock.result.name );
        expect( element.html() ).toContain( HomeDataMock.result.email );
        expect( element.html() ).toContain( HomeDataMock.result.donorID );
        expect( element.findAll( '.donorportal-recurring-contribution' ).length ).toBe( HomeDataMock.result.recurringContributions.length );
        expect( element.findAll( '.donorportal-inactive-recurring' ).length ).toBe( HomeDataMock.result.inactiveRecurringContributions.length );
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
                            checksum: 1,
                            contact_id: 203
                        }
                    }
                }
            }
        } );

        await VueTestUtils.flushPromises();

        const element = wrapper.find( '.donorportal-home' );
        expect( element.exists() ).toBe( true );
        expect( element.html() ).toContain( HomeDataMock.result.address.street_address );
        expect( element.html() ).toContain( HomeDataMock.result.address.city );
        expect( element.html() ).toContain( HomeDataMock.result.address.country );
        expect( element.html() ).toContain( HomeDataMock.result.name );
        expect( element.html() ).toContain( HomeDataMock.result.email );
        expect( element.html() ).toContain( HomeDataMock.result.donorID );
        expect( element.findAll( '.donorportal-recurring-contribution' ).length ).toBe( 0 );
        expect( element.findAll( '.donorportal-inactive-recurring' ).length ).toBe( 0 );
        expect( element.find( '.donorportal-recent-donation' ).exists() ).toBe( true );
    } );
} );
