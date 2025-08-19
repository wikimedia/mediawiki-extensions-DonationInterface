/* global global describe it expect beforeEach afterEach*/

const VueTestUtils = require( '@vue/test-utils' );
const DonationsListComponent = require( '../../../modules/ext.donationInterface.donorPortal/components/DonationsListTable.vue' );
const AnnualFundDonationsList = require( '../mocks/annual_donations_list.mock.js' );

describe( 'Donations List Table Component', () => {
    it( 'Renders successfully', () => {
        const wrapper = VueTestUtils.shallowMount( DonationsListComponent, {
            props: {
                donationsList: AnnualFundDonationsList
            }
        } );

        const element = wrapper.find( '#donorportal-donations-table' );
        expect( element.exists() ).toBe( true );

        const tableRows = wrapper.findAll( '.donorportal-donations-table-row' );
        expect( tableRows.length ).toBe( 2 );
        const first_row = tableRows[ 0 ],
            firstRowCells = first_row.findAll( 'td' );

        expect( firstRowCells[ 0 ].text() ).toBe( AnnualFundDonationsList[ 0 ].receive_date_formatted );
        expect( firstRowCells[ 1 ].text() ).toBe( AnnualFundDonationsList[ 0 ].donation_type_key );
        expect( firstRowCells[ 2 ].text() ).toBe( `${  AnnualFundDonationsList[ 0 ].amount_formatted  } ${  AnnualFundDonationsList[ 0 ].currency  }` );
        expect( firstRowCells[ 3 ].text() ).toBe( AnnualFundDonationsList[ 0 ].payment_method );

        const second_row = tableRows[ 1 ],
            secondRowCells = second_row.findAll( 'td' );
        expect( secondRowCells[ 0 ].text() ).toBe( AnnualFundDonationsList[ 1 ].receive_date_formatted );
        expect( secondRowCells[ 1 ].text() ).toBe( AnnualFundDonationsList[ 1 ].donation_type_key );
        expect( secondRowCells[ 2 ].text() ).toBe( `${  AnnualFundDonationsList[ 1 ].amount_formatted  } ${  AnnualFundDonationsList[ 1 ].currency  }` );
        expect( secondRowCells[ 3 ].text() ).toBe( AnnualFundDonationsList[ 1 ].payment_method );
    } );

} );
