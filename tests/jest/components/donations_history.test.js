/* global describe it expect beforeEach afterEach*/

const VueTestUtils = require( '@vue/test-utils' );
const DonationsHistory = require( '../../../modules/ext.donationInterface.donorPortal/components/DonationsHistory.vue' );
const AnnualFundDonationsList = require( '../mocks/annual_donations_list.mock.js' );
const EndowmentDonationsList = require( '../mocks/endowment_donations_list.mock.js' );

describe( 'Donor contact details component', () => {
    const elem = document.createElement( 'div' );
    let wrapper = null;
    beforeEach( () => {
        if ( document.body ) {
        document.body.appendChild( elem );
        }
    } );
    afterEach( () => {
        if ( wrapper ) {
 wrapper.unmount();
}
    } );
    it( 'Renders successfully', async () => {
        wrapper = VueTestUtils.mount( DonationsHistory, {
            data() {
                return {};
            },
            props: {
                annualFundDonations: AnnualFundDonationsList.slice( 0, 2 ),
                endowmentDonations: EndowmentDonationsList
            },
            attachTo: elem
        } );

        const element = wrapper.find( '#donorportal-donation-history' );
        expect( element.exists() ).toBe( true );
        expect( element.findAll( '.donorportal-donations-table-row' ).length ).toBe( 4 );

        const endowmentTabHeader = wrapper.find( '#form-tabs-2-label' );
        expect( endowmentTabHeader.exists() ).toBe( true );
        await endowmentTabHeader.trigger( 'click' );
        expect( element.html() ).toContain( EndowmentDonationsList[ 0 ].receive_date_formatted );
        expect( element.html() ).toContain( EndowmentDonationsList[ 0 ].donation_type_key );
        expect( element.html() ).toContain( EndowmentDonationsList[ 0 ].amount_formatted );
        expect( element.html() ).toContain( EndowmentDonationsList[ 0 ].currency );
        expect( element.html() ).toContain( EndowmentDonationsList[ 0 ].payment_method );

        const annualFundTabHeader = wrapper.find( '#form-tabs-1-label' );
        expect( annualFundTabHeader.exists() ).toBe( true );
        await annualFundTabHeader.trigger( 'click' );
        expect( element.html() ).toContain( AnnualFundDonationsList[ 0 ].receive_date_formatted );
        expect( element.html() ).toContain( AnnualFundDonationsList[ 0 ].donation_type_key );
        expect( element.html() ).toContain( AnnualFundDonationsList[ 0 ].amount_formatted );
        expect( element.html() ).toContain( AnnualFundDonationsList[ 0 ].currency );
        expect( element.html() ).toContain( AnnualFundDonationsList[ 0 ].payment_method );
    } );

    it( 'Renders endowment information when empty', () => {
        wrapper = VueTestUtils.mount( DonationsHistory, {
            data() {
                return {};
            },
            props: {
                annualFundDonations: [],
                endowmentDonations: []
            },
            attachTo: elem
        } );

        const element = wrapper.find( '#donorportal-donation-history' );
        expect( element.exists() ).toBe( true );
        expect( element.findAll( '.donorportal-donations-table-row' ).length ).toBe( 0 );

        const endowmentTabHeader = wrapper.find( '#form-tabs-1-label' );
        expect( endowmentTabHeader.exists() ).toBe( true );
        endowmentTabHeader.trigger( 'click' );
        expect( element.html() ).toContain( 'donorportal-endowment-short' );
        expect( element.html() ).toContain( 'donorportal-endowment-learn-more' );
        expect( element.html() ).toContain( 'donorportal-endowment-donate-now' );
    } );
} );
