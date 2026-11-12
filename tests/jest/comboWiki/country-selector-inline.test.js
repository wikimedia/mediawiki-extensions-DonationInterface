/* global describe it expect */

const VueTestUtils = require( '@vue/test-utils' );
const CountrySelectorInline = require( '../../../modules/ext.donationInterface.comboWiki/components/CountrySelectorInline.vue' );

const COUNTRIES = {
	FR: { currency: 'EUR' },
	DE: { currency: 'EUR' },
	US: { currency: 'USD' }
};

/**
 * Mounts the selector with params as init.js provides them, then opens the lookup.
 * CdxLookup is stubbed by shallowMount, but its props can still be read.
 *
 * @param {string} language Donor language (wgUserLanguage)
 * @return {Promise<Object>}
 */
const openLookup = async ( language ) => {
	const wrapper = VueTestUtils.shallowMount( CountrySelectorInline, {
		props: { modelValue: 'US', countries: COUNTRIES },
		global: {
			provide: { params: { wgUserLanguage: language } }
		}
	} );
	await wrapper.find( '.country-selector-inline__change' ).trigger( 'click' );
	return wrapper;
};

const menuLabels = ( wrapper ) => wrapper.findComponent( { name: 'CdxLookup' } )
	.props( 'menuItems' )
	.map( ( item ) => item.label );

describe( 'ComboWiki country selector', () => {
	it( 'lists localized country names, sorted', async () => {
		expect( menuLabels( await openLookup( 'en' ) ) )
			.toEqual( [ 'France (EUR)', 'Germany (EUR)', 'United States (USD)' ] );
	} );

	it( 'shows the current country name before the lookup is opened', () => {
		const wrapper = VueTestUtils.shallowMount( CountrySelectorInline, {
			props: { modelValue: 'FR', countries: COUNTRIES },
			global: {
				provide: { params: { wgUserLanguage: 'en' } }
			}
		} );
		expect( wrapper.find( '.country-selector-inline__value' ).text() ).toBe( 'France (EUR)' );
	} );
} );
