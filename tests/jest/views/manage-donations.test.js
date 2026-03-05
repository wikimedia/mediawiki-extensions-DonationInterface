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
        useRoute.mockImplementationOnce( () => ( {
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
} );
