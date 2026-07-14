/* global describe it expect */

const normalizeInput = require( '../../modules/ext.donationInterface.donorPortal/normalizeInput.js' );

describe( 'normalizeInput.sanitize', () => {
	it( 'returns an empty string for empty or null input', () => {
		expect( normalizeInput.sanitize( '' ) ).toBe( '' );
		expect( normalizeInput.sanitize( null ) ).toBe( '' );
	} );

	it( 'strips non-numeric characters', () => {
		expect( normalizeInput.sanitize( 'abc123' ) ).toBe( '123' );
		expect( normalizeInput.sanitize( '$1,234' ) ).toBe( '1234' );
	} );

	it( 'keeps only the first dot and limits to two decimals', () => {
		expect( normalizeInput.sanitize( '1.2.3' ) ).toBe( '1.23' );
		expect( normalizeInput.sanitize( '12.3456' ) ).toBe( '12.34' );
	} );

	it( 'preserves a trailing dot while typing', () => {
		expect( normalizeInput.sanitize( '1.' ) ).toBe( '1.' );
		expect( normalizeInput.sanitize( '.' ) ).toBe( '.' );
	} );

	it( 'preserves a leading dot and a single leading zero', () => {
		expect( normalizeInput.sanitize( '.5' ) ).toBe( '.5' );
		expect( normalizeInput.sanitize( '0.5' ) ).toBe( '0.5' );
	} );

	it( 'strips redundant leading zeros from whole numbers', () => {
		expect( normalizeInput.sanitize( '00012' ) ).toBe( '12' );
	} );

	it( 'rounds a complete integer value to at most two decimals', () => {
		expect( normalizeInput.sanitize( '100' ) ).toBe( '100' );
	} );
} );

describe( 'normalizeInput.escapeHtml', () => {
	it( 'returns an empty string for falsy input', () => {
		expect( normalizeInput.escapeHtml( '' ) ).toBe( '' );
		expect( normalizeInput.escapeHtml( undefined ) ).toBe( '' );
	} );

	it( 'escapes all HTML-sensitive characters', () => {
		expect( normalizeInput.escapeHtml( '<a href="x">Tom & Jerry\'s</a>' ) )
			.toBe( '&lt;a href=&quot;x&quot;&gt;Tom &amp; Jerry&#39;s&lt;/a&gt;' );
	} );
} );

describe( 'normalizeInput.getRecurringPriceRange', () => {
	it( 'derives min/max from the currency rate when no donation_rules are set', () => {
		const result = normalizeInput.getRecurringPriceRange(
			{ currency: 'USD' },
			{ USD: 2 },
			100
		);
		expect( result ).toEqual( [ 2, 200 ] );
	} );

	it( 'falls back to a rate of 1 when the currency is not in the rate array', () => {
		const result = normalizeInput.getRecurringPriceRange(
			{ currency: 'USD' },
			{},
			100
		);
		expect( result ).toEqual( [ 1, 100 ] );
	} );

	it( 'overrides min/max from donation_rules for the matching currency', () => {
		const result = normalizeInput.getRecurringPriceRange(
			{
				currency: 'USD',
				donation_rules: { currency: 'USD', min: 5, max: 50 }
			},
			{ USD: 1 },
			100
		);
		expect( result ).toEqual( [ 5, 50 ] );
	} );

	it( 'converts donation_rules from another currency into the current currency', () => {
		const result = normalizeInput.getRecurringPriceRange(
			{
				currency: 'EUR',
				donation_rules: { currency: 'USD', min: 5, max: 50 }
			},
			{ EUR: 2 },
			100
		);
		expect( result ).toEqual( [ 10, 100 ] );
	} );

	it( 'keeps the rate-derived min/max when donation_rules omit min and max', () => {
		const result = normalizeInput.getRecurringPriceRange(
			{
				currency: 'USD',
				donation_rules: { currency: 'USD' }
			},
			{ USD: 3 },
			100
		);
		expect( result ).toEqual( [ 3, 300 ] );
	} );

	it( 'returns NaN bounds when no currency is provided', () => {
		const result = normalizeInput.getRecurringPriceRange(
			{},
			{ USD: 2 },
			100
		);
		expect( result[ 0 ] ).toBeNaN();
		expect( result[ 1 ] ).toBeNaN();
	} );
} );
