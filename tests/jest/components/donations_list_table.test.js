/* global describe it expect beforeEach afterEach*/

const VueTestUtils = require( '@vue/test-utils' );
const DonationsListComponent = require(
	'../../../modules/ext.donationInterface.donorPortal/components/DonationsListTable.vue' );
const AnnualFundDonationsList = require( '../mocks/annual_donations_list.mock.js' );

function assertTextCorrect( row, donation ) {
	const cells = row.findAll( 'td' );
	let type_key = 'donorportal-donation-type-one-time';
	if ( donation.donation_type_key !== 'donorportal-donation-type-one-time' ) {
		type_key = `${ donation.recurring_status_key }:[${ donation.donation_type_key }]`;
	}
	expect( cells[ 0 ].text() ).toBe( donation.receive_date_formatted );
	expect( cells[ 1 ].text() ).toBe( type_key );
	if ( donation.refunded_status_key ) {
		expect( cells[ 2 ].text() ).toContain( `${ donation.refunded_status_key }${ donation.amount_formatted } ${ donation.currency }` );
	} else {
		expect( cells[ 2 ].text() ).toBe( `${ donation.amount_formatted } ${ donation.currency }` );
	}
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

	it( 'Displays the right recurring status for inactive recurrings correctly', async () => {
		const inactiveRecurring = [ {
			receive_date_formatted: '02 March, 2025',
			donation_type_key: 'donorportal-donation-type-annual',
			recurring_status_key: 'donorportal-donation-type-inactive-recurring-template',
			amount_formatted: '$6.78',
			currency: 'USD',
			payment_method: 'Credit Card: MasterCard'
		} ];
		const wrapper = VueTestUtils.shallowMount( DonationsListComponent, {
			props: {
				donationsList: inactiveRecurring
			}
		} );

		const element = wrapper.find( '#donorportal-donations-table' );
		expect( element.exists() ).toBe( true );

		const tableRows = wrapper.findAll( '.donorportal-donations-table-row' );
		expect( tableRows.length ).toBe( 1 );

		assertTextCorrect( tableRows[ 0 ], inactiveRecurring[ 0 ] );
	} );

	it( 'Displays the right refund status for refunded contributions correctly', async () => {
		const refundedContribution = [ {
			receive_date_formatted: '02 March, 2025',
			donation_type_key: 'donorportal-donation-type-one-time',
			refunded_status_key: 'donorportal-donation-status-refunded',
			amount_formatted: '$6.78',
			currency: 'USD',
			payment_method: 'Credit Card: MasterCard'
		} ];
		const wrapper = VueTestUtils.shallowMount( DonationsListComponent, {
			props: {
				donationsList: refundedContribution
			}
		} );

		const element = wrapper.find( '#donorportal-donations-table' );
		expect( element.exists() ).toBe( true );

		const tableRows = wrapper.findAll( '.donorportal-donations-table-row' );
		expect( tableRows.length ).toBe( 1 );

		assertTextCorrect( tableRows[ 0 ], refundedContribution[ 0 ] );
	} );

	it( 'Paginates via the navigation buttons', async () => {
		const wrapper = VueTestUtils.shallowMount( DonationsListComponent, {
			props: {
				donationsList: AnnualFundDonationsList
			}
		} );

		const firstButton = wrapper.find( '.pagination-button-first' );
		const prevButton = wrapper.find( '.pagination-button-prev' );
		const nextButton = wrapper.find( '.pagination-button-next' );
		const lastButton = wrapper.find( '.pagination-button-last' );

		// Start on page 1: first page shows 10 rows.
		expect( wrapper.findAll( '.donorportal-donations-table-row' ).length ).toBe( 10 );

		// Next button advances to page 2 (the last page, 5 rows).
		await nextButton.trigger( 'click' );
		expect( wrapper.findAll( '.donorportal-donations-table-row' ).length ).toBe( 5 );

		// Prev button goes back to page 1.
		await prevButton.trigger( 'click' );
		expect( wrapper.findAll( '.donorportal-donations-table-row' ).length ).toBe( 10 );

		// Last button jumps to page 2.
		await lastButton.trigger( 'click' );
		expect( wrapper.findAll( '.donorportal-donations-table-row' ).length ).toBe( 5 );

		// First button jumps back to page 1.
		await firstButton.trigger( 'click' );
		expect( wrapper.findAll( '.donorportal-donations-table-row' ).length ).toBe( 10 );
	} );

	it( 'Displays N/A when the donation type key is missing', () => {
		const noTypeKey = [ {
			receive_date_formatted: '02 March, 2025',
			amount_formatted: '$6.78',
			currency: 'USD',
			payment_method: 'Credit Card: MasterCard'
		} ];
		const wrapper = VueTestUtils.shallowMount( DonationsListComponent, {
			props: {
				donationsList: noTypeKey
			}
		} );

		const tableRows = wrapper.findAll( '.donorportal-donations-table-row' );
		expect( tableRows.length ).toBe( 1 );

		const cells = tableRows[ 0 ].findAll( 'td' );
		expect( cells[ 1 ].text() ).toBe( 'N/A' );
	} );

	it( 'refundedStatusLocale returns an empty string without a status key', () => {
		const wrapper = VueTestUtils.shallowMount( DonationsListComponent, {
			props: {
				donationsList: AnnualFundDonationsList.slice( 0, 1 )
			}
		} );
		// Empty-key branch is unreachable via template; call directly.
		expect( wrapper.vm.refundedStatusLocale() ).toBe( '' );
	} );

	it( 'Renders no table rows when the donations list is empty', () => {
		const wrapper = VueTestUtils.shallowMount( DonationsListComponent, {
			props: {
				donationsList: []
			}
		} );

		const element = wrapper.find( '#donorportal-donations-table' );
		expect( element.exists() ).toBe( true );

		const tableRows = wrapper.findAll( '.donorportal-donations-table-row' );
		expect( tableRows.length ).toBe( 0 );
	} );
} );
