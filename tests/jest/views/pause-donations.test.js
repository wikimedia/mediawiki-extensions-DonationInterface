/* global global describe it expect beforeEach afterEach jest */
/* eslint-disable es-x/no-promise */

// Mock vue router composables
jest.mock( 'vue-router', () => ( Object.assign( jest.requireActual( 'vue-router' ), { useRoute: jest.fn() } ) ) );

const VueTestUtils = require( '@vue/test-utils' );
const { when } = require( 'jest-when' );
const router = require( '../../../modules/ext.donationInterface.donorPortal/router.js' );
const PauseDonationsView = require( '../../../modules/ext.donationInterface.donorPortal/views/PauseDonations.vue' );
const { useRoute } = require( 'vue-router' );

const RECURRING_PAUSE_API_ACTION = 'requestPauseRecurring';
describe( 'Pause donations view', () => {
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
            contact_id: '12345',
            checksum: 'random-checksum',
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
                    id: 123,
                    next_sched_contribution_date: '2025-08-02 00:00:02',
                    amount: 10
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
        when( global.mw.config.get ).calledWith( 'requestDonorPortalPage' ).mockReturnValue( 'DonorPortal' );
        when( global.mw.config.get ).calledWith( 'help_email' ).mockReturnValue( 'help@example.com' );
        useRoute.mockImplementationOnce( () => ( {
            params: {
                id: '123'
            }
        } ) );
    } );

    afterEach( () => {
        global.mw.Api.prototype.post.mockReturnValue(
            new Promise( ( resolve, _ ) => {
                resolve( {} );
            } )
        );
    } );

    it( 'Pause Donations view renders successfully', () => {
        const wrapper = VueTestUtils.mount( PauseDonationsView, {
            global: {
                plugins: [ router ]
            }
        } );

        const pauseDonationViewBody = wrapper.find( '#pause-donations' );
        expect( pauseDonationViewBody.exists() ).toBe( true );
        expect( pauseDonationViewBody.html() ).toContain( 'donorportal-pause-recurring-heading' );
        expect( pauseDonationViewBody.html() ).toContain( 'donorportal-pause-recurring-subheading' );
        expect( pauseDonationViewBody.html() ).toContain( 'donorportal-pause-recurring-subtext' );
        expect( pauseDonationViewBody.html() ).toContain( 'donorportal-pause-recurring-specify-duration' );
        expect( pauseDonationViewBody.html() ).toContain( 'donorportal-pause-recurring-days' );
        expect( pauseDonationViewBody.html() ).toContain( 'donorportal-pause-recurring-pause-button' );

        const pauseDonationsOptionsList = wrapper.findAll( '#radio-button-options-list' );
        expect( pauseDonationsOptionsList.length ).toBe( 3 );

        // Ensure success text is not visible on first load
        const successText = wrapper.find( '#recurring-contribution-pause-success' );
        expect( successText.exists() ).toBe( false );

        // Ensure failure text is not visible on first load
        const failureText = wrapper.find( '#error-component' );
        expect( failureText.exists() ).toBe( false );
    } );

   it( 'Renders the success view on success', async () => {
        const wrapper = VueTestUtils.mount( PauseDonationsView, {
            global: {
                plugins: [ router ]
            }
        } );

        const pauseDonationViewBody = wrapper.find( '#pause-donations' );
        const selectedPeriod = pauseDonationViewBody.find( '#option-60days' );
        global.mw.Api.prototype.post.mockImplementation( () => Promise.resolve( {
            result: {
                message: 'Success',
                next_sched_contribution_date: '2025-10-02 00:00:02'
            } } )
        );
        selectedPeriod.element.selected = true;
        await selectedPeriod.trigger( 'input' );
        await VueTestUtils.flushPromises();
        const submitButton = pauseDonationViewBody.find( '#continue' );
        await submitButton.trigger( 'click' );
        await VueTestUtils.flushPromises();

        expect( global.mw.Api.prototype.post ).toHaveBeenCalledWith( {
            action: RECURRING_PAUSE_API_ACTION,
            duration: '60 Days',
            contact_id: Number( HomeDataMock.result.contact_id ),
            checksum: HomeDataMock.result.checksum,
            contribution_recur_id: 123,
            next_sched_contribution_date: '2025-08-02 00:00:02'
        } );

        // Ensure success text is visible after successful API request
        const successText = wrapper.find( '#recurring-contribution-pause-success' );
        expect( successText.exists() ).toBe( true );
        expect( successText.html() ).toContain( '2025-10-02 00:00:02' );

        // Ensure failure text is not visible after successful API request
        const failureText = wrapper.find( '#error-component' );
        expect( failureText.exists() ).toBe( false );
    } );

   it( 'Renders the error view on failure', async () => {
        const wrapper = VueTestUtils.mount( PauseDonationsView, {
            global: {
                plugins: [ router ]
            }
        } );
        global.mw.Api.prototype.post.mockImplementation(
            () => Promise.reject( {
                    message: 'API error'
            } )
        );

        const pauseDonationViewBody = wrapper.find( '#pause-donations' );
        const selectedPeriod = pauseDonationViewBody.find( '#option-90days' );
        selectedPeriod.element.selected = true;
        await selectedPeriod.trigger( 'input' );
        await VueTestUtils.flushPromises();
        const submitButton = pauseDonationViewBody.find( '#continue' );
        await submitButton.trigger( 'click' );
        await VueTestUtils.flushPromises();

        expect( global.mw.Api.prototype.post ).toHaveBeenCalledWith( {
            action: RECURRING_PAUSE_API_ACTION,
            duration: '90 Days',
            contact_id: Number( HomeDataMock.result.contact_id ),
            checksum: HomeDataMock.result.checksum,
            contribution_recur_id: 123,
            next_sched_contribution_date: '2025-08-02 00:00:02'
        } );

        // Ensure success text is visible after successful API request
        const successText = wrapper.find( '#recurring-contribution-pause-success' );
        expect( successText.exists() ).toBe( false );

        // Ensure failure text is not visible after successful API request
        const failureText = wrapper.find( '#error-component' );
        expect( failureText.exists() ).toBe( true );
        expect( failureText.html() ).toContain( 'donorportal-pause-failure' );

    } );
} );
