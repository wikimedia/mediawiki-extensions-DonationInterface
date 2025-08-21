/* global global describe it expect beforeEach afterEach*/

const VueTestUtils = require( '@vue/test-utils' );
const DonationsListComponent = require( '../../../modules/ext.donationInterface.donorPortal/components/DonationsListTable.vue' );
const AnnualFundDonationsList = require( '../mocks/annual_donations_list.mock.js' );

function assertTextCorrect( row, donation ) {
    const cells = row.findAll( 'td' );
    expect( cells[ 0 ].text() ).toBe( donation.receive_date_formatted );
    expect( cells[ 1 ].text() ).toBe( donation.donation_type_key );
    expect( cells[ 2 ].text() ).toBe( `${  donation.amount_formatted  } ${  donation.currency  }` );
    expect( cells[ 3 ].text() ).toBe( donation.payment_method );
}

describe( 'Donations List Table Component', () => {
    it( 'Renders successfully', () => {
        const wrapper = VueTestUtils.shallowMount( DonationsListComponent, {
            props: {
                donationsList: AnnualFundDonationsList.slice( 0, 2 )
            }
        } );

        const element = wrapper.find( '#donorportal-donations-table' );
        expect( element.exists() ).toBe( true );

        const tableRows = wrapper.findAll( '.donorportal-donations-table-row' );
        expect( tableRows.length ).toBe( 2 );

        assertTextCorrect( tableRows[ 0 ], AnnualFundDonationsList[ 0 ] );
        assertTextCorrect( tableRows[ 1 ], AnnualFundDonationsList[ 1 ] );

    } );

    it( 'Paginates correctly', async () => {
        const wrapper = VueTestUtils.shallowMount( DonationsListComponent, {
            props: {
                donationsList: AnnualFundDonationsList
            }
        } );

        const element = wrapper.find( '#donorportal-donations-table' );
        expect( element.exists() ).toBe( true );

        let tableRows = wrapper.findAll( '.donorportal-donations-table-row' );
        expect( tableRows.length ).toBe( 10 );
        for ( let i = 0; i < 10; i++ ) {
            assertTextCorrect( tableRows[ i ], AnnualFundDonationsList[ i ] );
        }
        let paginationLinks = wrapper.findAll( '.donorportal-donations-table-pagination a' );
        expect( paginationLinks.length ).toBe( 3 );
        expect( paginationLinks[ 0 ].text() ).toBe( '1' );
        expect( paginationLinks[ 1 ].text() ).toBe( '2' );
        expect( paginationLinks[ 2 ].text() ).toBe( '>>' );

        await paginationLinks[ 1 ].trigger( 'click' );

        tableRows = wrapper.findAll( '.donorportal-donations-table-row' );
        expect( tableRows.length ).toBe( 5 );
        for ( let i = 0; i < 5; i++ ) {
            assertTextCorrect( tableRows[ i ], AnnualFundDonationsList[ i + 10 ] );
        }
        paginationLinks = wrapper.findAll( '.donorportal-donations-table-pagination a' );
        expect( paginationLinks.length ).toBe( 3 );
        expect( paginationLinks[ 0 ].text() ).toBe( '<<' );
        expect( paginationLinks[ 1 ].text() ).toBe( '1' );
        expect( paginationLinks[ 2 ].text() ).toBe( '2' );
    } );
} );
