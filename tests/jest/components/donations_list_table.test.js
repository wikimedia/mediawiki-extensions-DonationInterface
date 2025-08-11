/* global global describe it expect beforeEach afterEach*/

const VueTestUtils = require( '@vue/test-utils' );
const DonationsListComponent = require( '../../../modules/ext.donationInterface.donorPortal/components/DonationsListTable.vue' );

describe( 'Donations List Table Component', () => {
    const donationsList = [
        {
            receive_date_formatted: '02 March, 2025',
            donation_type_key: 'donorportal-donation-type-monthly',
            amount_formatted: '$5.78',
            currency: 'USD',
            payment_method: 'Credit Card: Visa',
            id: '1'
        },
        {
            receive_date_formatted: '03 March, 2025',
            donation_type_key: 'donorportal-donation-type-annual',
            amount_formatted: '$6.78',
            currency: 'USD',
            payment_method: 'Credit Card: MasterCard',
            id: '2'
        }
    ];
    it( 'Renders successfully', () => {
        const wrapper = VueTestUtils.shallowMount( DonationsListComponent, {
            props: {
                donations_list: donationsList
            }
        } );

        const element = wrapper.find( '#donorportal-donations-table' );
        expect( element.exists() ).toBe( true );

        const tableRows = wrapper.findAll( '.donorportal-donations-table-row' );
        expect( tableRows.length ).toBe( 2 );
        const first_row = tableRows[ 0 ],
            firstRowCells = first_row.findAll( 'td' );

        expect( firstRowCells[ 0 ].text() ).toBe( donationsList[ 0 ].receive_date_formatted );
        expect( firstRowCells[ 1 ].text() ).toBe( donationsList[ 0 ].donation_type_key );
        expect( firstRowCells[ 2 ].text() ).toBe( `${  donationsList[ 0 ].amount_formatted  } ${  donationsList[ 0 ].currency  }` );
        expect( firstRowCells[ 3 ].text() ).toBe( donationsList[ 0 ].payment_method );

        const second_row = tableRows[ 1 ],
            secondRowCells = second_row.findAll( 'td' );
        expect( secondRowCells[ 0 ].text() ).toBe( donationsList[ 1 ].receive_date_formatted );
        expect( secondRowCells[ 1 ].text() ).toBe( donationsList[ 1 ].donation_type_key );
        expect( secondRowCells[ 2 ].text() ).toBe( `${  donationsList[ 1 ].amount_formatted  } ${  donationsList[ 1 ].currency  }` );
        expect( secondRowCells[ 3 ].text() ).toBe( donationsList[ 1 ].payment_method );
    } );

} );
