/* global global describe it expect beforeEach afterEach*/

const VueTestUtils = require( '@vue/test-utils' );
const DonationsHistory = require( '../../../modules/ext.donationInterface.donorPortal/components/DonationsHistory.vue' );

describe( 'Donor contact details component', () => {
    const AnnualFundDonationsList = [
        {
            receive_date_formatted: '02 March, 2025',
            donation_type_key: 'donorportal-donation-type-monthly',
            amount_formatted: '$5.78',
            currency: 'USD',
            payment_method: 'Credit Card: Visa'
        },
        {
            receive_date_formatted: '03 March, 2025',
            donation_type_key: 'donorportal-donation-type-annual',
            amount_formatted: '$6.78',
            currency: 'USD',
            payment_method: 'Credit Card: MasterCard'
        }
    ];
    const EndowmentDonationsList = [
        {
            receive_date_formatted: '02 March, 2024',
            donation_type_key: 'donorportal-endowment',
            amount_formatted: '$1005.78',
            currency: 'USD',
            payment_method: 'Credit Card: Visa'
        },
        {
            receive_date_formatted: '03 March, 2024',
            donation_type_key: 'donorportal-donation-type-annual',
            amount_formatted: '$6000.78',
            currency: 'USD',
            payment_method: 'Credit Card: MasterCard'
        }
    ];
    it( 'Renders successfully', () => {
        const wrapper = VueTestUtils.mount( DonationsHistory, {
            data() {
                return {};
            },
            props: {
                annual_fund_donations: AnnualFundDonationsList,
                endowment_donations: EndowmentDonationsList
            }
        } );

        const element = wrapper.find( '#donorportal-donation-history' );
        expect( element.exists() ).toBe( true );
        expect( element.findAll( '.donorportal-donations-table-row' ).length ).toBe( 4 );

        const endowmentTabHeader = wrapper.find( { ref: 'endowment-tab-header' } );
        expect( endowmentTabHeader.exists() ).toBe( true );
        endowmentTabHeader.trigger( 'click' );
        expect( element.html() ).toContain( EndowmentDonationsList[ 0 ].receive_date_formatted );
        expect( element.html() ).toContain( EndowmentDonationsList[ 0 ].donation_type_key );
        expect( element.html() ).toContain( EndowmentDonationsList[ 0 ].amount_formatted );
        expect( element.html() ).toContain( EndowmentDonationsList[ 0 ].currency );
        expect( element.html() ).toContain( EndowmentDonationsList[ 0 ].payment_method );

        const annualFundTabHeader = wrapper.find( { ref: 'annual-funds-tab-header' } );
        expect( annualFundTabHeader.exists() ).toBe( true );
        annualFundTabHeader.trigger( 'click' );
        expect( element.html() ).toContain( AnnualFundDonationsList[ 0 ].receive_date_formatted );
        expect( element.html() ).toContain( AnnualFundDonationsList[ 0 ].donation_type_key );
        expect( element.html() ).toContain( AnnualFundDonationsList[ 0 ].amount_formatted );
        expect( element.html() ).toContain( AnnualFundDonationsList[ 0 ].currency );
        expect( element.html() ).toContain( AnnualFundDonationsList[ 0 ].payment_method );
    } );

    it( 'Renders endowment information when empty', () => {
        const wrapper = VueTestUtils.mount( DonationsHistory, {
            data() {
                return {};
            },
            props: {
                annual_fund_donations: [],
                endowment_donations: []
            }
        } );

        const element = wrapper.find( '#donorportal-donation-history' );
        expect( element.exists() ).toBe( true );
        expect( element.findAll( '.donorportal-donations-table-row' ).length ).toBe( 0 );

        const endowmentTabHeader = wrapper.find( { ref: 'endowment-tab-header' } );
        expect( endowmentTabHeader.exists() ).toBe( true );
        endowmentTabHeader.trigger( 'click' );
        expect( element.html() ).toContain( 'donorportal-endowment-short' );
        expect( element.html() ).toContain( 'donorportal-endowment-learn-more' );
        expect( element.html() ).toContain( 'donorportal-endowment-donate-now' );
    } );
} );
