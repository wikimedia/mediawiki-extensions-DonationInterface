/* global global describe it expect beforeEach jest */

// Mock vue router composables
jest.mock( 'vue-router', () => ( Object.assign( jest.requireActual( 'vue-router' ), { useRoute: jest.fn() } ) ) );

const VueTestUtils = require( '@vue/test-utils' );
const { when } = require( 'jest-when' );
const { useRoute } = require( 'vue-router' );

const router = require( '../../../modules/ext.donationInterface.donorPortal/router.js' );
const ManageDonationsView = require( '../../../modules/ext.donationInterface.donorPortal/views/ManageDonations.vue' );
const DonorDataMock = require( '../mocks/donor_data.mock.js' );

describe( 'Manage donations view', () => {
    beforeEach( () => {
        when( global.mw.config.get ).calledWith( 'donorData' ).mockReturnValue( DonorDataMock );
        when( global.mw.config.get ).calledWith( 'requestDonorPortalPage' ).mockReturnValue( 'DonorPortal' );
        when( global.mw.config.get ).calledWith( 'help_email' ).mockReturnValue( 'help@example.com' );
        when( global.mw.config.get ).calledWith( 'emailPreferencesUrl' ).mockReturnValue( 'https://emailprefs.wiki' );
        useRoute.mockImplementation( () => ( {
            params: {
                id: '123'
            }
        } ) );
    } );

    it( 'Manage Donations view renders successfully', () => {
        const wrapper = VueTestUtils.mount( ManageDonationsView, {
            global: {
                plugins: [ router ]
            }
        } );
        const manageDonationViewBody = wrapper.find( '#manage-donations' );

        expect( manageDonationViewBody.exists() ).toBe( true );
        expect( manageDonationViewBody.html() ).toContain( 'donorportal-manage-donation-heading' );
        expect( manageDonationViewBody.html() ).toContain( 'donorportal-manage-donation-text' );
        expect( manageDonationViewBody.html() ).toContain( 'donorportal-recurring-status-active' );
        expect( manageDonationViewBody.html() ).toContain( 'donorportal-manage-donation-donor-card-heading' );
        expect( manageDonationViewBody.html() ).toContain( 'donorportal-manage-donation-donor-card-text' );
        expect( manageDonationViewBody.html() ).toContain( 'donorportal-manage-donation-management-heading' );
        expect( manageDonationViewBody.html() ).toContain( 'donorportal-manage-donation-management-pause-gift' );
        expect( manageDonationViewBody.html() ).toContain( 'donorportal-manage-donation-management-change-amount' );
        expect( manageDonationViewBody.html() ).toContain( 'donorportal-cancel-recurring-frequency-annual-switch-alternative-button' );
        expect( manageDonationViewBody.html() ).toContain( 'donorportal-manage-donation-management-cancel-gift' );
        expect( manageDonationViewBody.html() ).toContain( 'donorportal-cancel-recurring-quit-header' );
        expect( manageDonationViewBody.html() ).toContain( 'donorportal-return-to-account-button' );
        expect( manageDonationViewBody.html() ).toContain( DonorDataMock.recurringContributions[ 0 ].payment_method );
        expect( manageDonationViewBody.html() ).toContain( `donorportal-recurring-amount-monthly:[${ DonorDataMock.recurringContributions[ 0 ].amount_formatted },${ DonorDataMock.recurringContributions[ 0 ].currency }]` );
        expect( manageDonationViewBody.html() ).toContain( `donorportal-recurring-next-amount-and-date:[${ DonorDataMock.recurringContributions[ 0 ].amount_formatted },${ DonorDataMock.recurringContributions[ 0 ].currency },${ DonorDataMock.recurringContributions[ 0 ].next_sched_contribution_date_formatted }]` );
    } );

    it( 'Manage Donations view renders successfully without conversion suggestion for yearly recurring', () => {
        useRoute.mockImplementationOnce( () => ( {
            params: {
                id: '456'
            }
        } ) );
        const wrapper = VueTestUtils.mount( ManageDonationsView, {
            global: {
                plugins: [ router ]
            }
        } );
        const manageDonationViewBody = wrapper.find( '#manage-donations' );

        expect( manageDonationViewBody.exists() ).toBe( true );
        expect( manageDonationViewBody.html() ).toContain( 'donorportal-manage-donation-heading' );
        expect( manageDonationViewBody.html() ).toContain( 'donorportal-manage-donation-text' );
        expect( manageDonationViewBody.html() ).toContain( 'donorportal-recurring-status-active' );
        expect( manageDonationViewBody.html() ).toContain( 'donorportal-manage-donation-donor-card-heading' );
        expect( manageDonationViewBody.html() ).toContain( 'donorportal-manage-donation-donor-card-text' );
        expect( manageDonationViewBody.html() ).toContain( 'donorportal-manage-donation-management-heading' );
        expect( manageDonationViewBody.html() ).toContain( 'donorportal-manage-donation-management-pause-gift' );
        expect( manageDonationViewBody.html() ).toContain( 'donorportal-manage-donation-management-change-amount' );
        expect( manageDonationViewBody.html() ).not.toContain( 'donorportal-cancel-recurring-frequency-annual-switch-alternative-button' );
        expect( manageDonationViewBody.html() ).toContain( 'donorportal-manage-donation-management-cancel-gift' );
        expect( manageDonationViewBody.html() ).toContain( 'donorportal-cancel-recurring-quit-header' );
        expect( manageDonationViewBody.html() ).toContain( 'donorportal-return-to-account-button' );
        expect( manageDonationViewBody.html() ).toContain( DonorDataMock.recurringContributions[ 1 ].payment_method );
        expect( manageDonationViewBody.html() ).toContain( `donorportal-recurring-amount-annual:[${ DonorDataMock.recurringContributions[ 1 ].amount_formatted },${ DonorDataMock.recurringContributions[ 1 ].currency }]` );
        expect( manageDonationViewBody.html() ).toContain( `donorportal-recurring-next-amount-and-date:[${ DonorDataMock.recurringContributions[ 1 ].amount_formatted },${ DonorDataMock.recurringContributions[ 1 ].currency },${ DonorDataMock.recurringContributions[ 1 ].next_sched_contribution_date_formatted }]` );
    } );

    it( 'Renders without crashing when the contribution id is not found', () => {
        useRoute.mockImplementationOnce( () => ( {
            params: {
                id: '999999'
            }
        } ) );
        const wrapper = VueTestUtils.mount( ManageDonationsView, {
            global: {
                plugins: [ router ]
            }
        } );
        const manageDonationViewBody = wrapper.find( '#manage-donations' );

        // With no matching recurring contribution the record falls back to {},
        // so the view still renders its shell rather than returning undefined.
        expect( manageDonationViewBody.exists() ).toBe( true );
        expect( manageDonationViewBody.html() ).toContain( 'donorportal-manage-donation-heading' );
        // Assert that no other contribution details render.
        expect( manageDonationViewBody.html() ).not.toContain( DonorDataMock.recurringContributions[ 0 ].payment_method );
    } );

    it( 'Manage Donations view hides the pause button and shows the lapsed class when the contribution is paused', () => {
        const pausedDonorDataMock = Object.assign( {}, DonorDataMock, {
            recurringContributions: [
                Object.assign( {}, DonorDataMock.recurringContributions[ 0 ], { is_paused: true } )
            ]
        } );
        when( global.mw.config.get ).calledWith( 'donorData' ).mockReturnValueOnce( pausedDonorDataMock );
        useRoute.mockImplementationOnce( () => ( {
            params: {
                id: '123'
            }
        } ) );
        const wrapper = VueTestUtils.mount( ManageDonationsView, {
            global: {
                plugins: [ router ]
            }
        } );
        const manageDonationViewBody = wrapper.find( '#manage-donations' );

        expect( manageDonationViewBody.exists() ).toBe( true );
        expect( manageDonationViewBody.html() ).toContain( 'donorportal-recurring-status-paused' );
        expect( wrapper.find( '.box.is-lapsed' ).exists() ).toBe( true );
        expect( wrapper.find( '.box.is-recurring' ).exists() ).toBe( false );
        expect( wrapper.find( '#buttonPauseGift' ).exists() ).toBe( false );
        expect( manageDonationViewBody.html() ).not.toContain( 'donorportal-manage-donation-management-pause-gift' );
        expect( wrapper.find( '#buttonChangeDonationAmount' ).exists() ).toBe( true );
    } );
} );
