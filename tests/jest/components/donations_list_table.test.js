/* global describe it expect beforeEach afterEach*/

const VueTestUtils = require( '@vue/test-utils' );
const DonationsListComponent = require(
	'../../../modules/ext.donationInterface.donorPortal/components/DonationsListTable.vue' );
const AnnualFundDonationsList = require( '../mocks/annual_donations_list.mock.js' );

function assertTextCorrect( row, donation ) {
	const cells = row.findAll( 'td' );
	expect( cells[ 0 ].text() ).toBe( donation.receive_date_formatted );
	expect( cells[ 1 ].text() ).toBe( `donorportal-donation-type-recurring-template:[${ donation.donation_type_key }]` );
	expect( cells[ 2 ].text() ).toBe( `${ donation.amount_formatted } ${ donation.currency }` );
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

		const table_pagination = wrapper.find( '.table-pagination' );
		expect( table_pagination.html() ).toContain(
			'donorportal-donationtable-pagination-text:[<strong>1</strong>,<strong>2</strong>,<strong>2</strong>]' );
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

		// Pagination buttons are disabled on first page
		let firstButton = wrapper.find( '.pagination-button-first' );
		let prevButton = wrapper.find( '.pagination-button-prev' );
		let nextButton = wrapper.find( '.pagination-button-next' );
		let lastButton = wrapper.find( '.pagination-button-last' );
		expect( firstButton.element.disabled ).toBe( true );
		expect( prevButton.element.disabled ).toBe( true );
		expect( nextButton.element.disabled ).toBe( false );
		expect( lastButton.element.disabled ).toBe( false );
		// Pagination row count is accurate
		let table_pagination = wrapper.find( '.table-pagination' );
		expect( table_pagination.html() ).toContain(
			'donorportal-donationtable-pagination-text:[<strong>1</strong>,<strong>10</strong>,<strong>15</strong>]' );

		// Relevant rows are displayed on the table with pagination
		for ( let i = 0; i < 10; i++ ) {
			assertTextCorrect( tableRows[ i ], AnnualFundDonationsList[ i ] );
		}
		const paginationListOptions = wrapper.findAll( '.page-select-option' );
		expect( paginationListOptions.length ).toBe( 2 );
		const paginationListSelect = wrapper.find( '.page-select' );
		await paginationListSelect.setValue( 2 );

		tableRows = wrapper.findAll( '.donorportal-donations-table-row' );
		expect( tableRows.length ).toBe( 5 );
		for ( let i = 0; i < 5; i++ ) {
			assertTextCorrect( tableRows[ i ], AnnualFundDonationsList[ i + 10 ] );
		}

		// Pagination buttons are disabled on last page
		firstButton = wrapper.find( '.pagination-button-first' );
		prevButton = wrapper.find( '.pagination-button-prev' );
		nextButton = wrapper.find( '.pagination-button-next' );
		lastButton = wrapper.find( '.pagination-button-last' );
		expect( firstButton.element.disabled ).toBe( false );
		expect( prevButton.element.disabled ).toBe( false );
		expect( nextButton.element.disabled ).toBe( true );
		expect( lastButton.element.disabled ).toBe( true );

		// Pagination row count is accurate
		table_pagination = wrapper.find( '.table-pagination' );
		expect( table_pagination.html() ).toContain(
			'donorportal-donationtable-pagination-text:[<strong>11</strong>,<strong>15</strong>,<strong>15</strong>]' );
	} );
} );
