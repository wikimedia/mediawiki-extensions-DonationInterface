/* global describe it expect */

const VueTestUtils = require( '@vue/test-utils' );

const SEPAComponent = require( '../../../modules/ext.donationInterface.comboWiki/components/SEPAComponent.vue' );
const AdyenSEPA = require( '../../../modules/ext.donationInterface.comboWiki/components/AdyenSEPA.vue' );

// Both SEPA forms show the donor the mandate for the frequency they chose
describe.each( [
	[ 'Gravy SEPA', SEPAComponent ],
	// Without Adyen config the form doesn't load Adyen, but still shows the mandate
	[ 'Adyen SEPA', AdyenSEPA ]
] )( 'ComboWiki %s mandate', ( label, component ) => {
	const mandateFor = ( frequency ) => VueTestUtils.mount( component, {
		props: { donation: { frequency, amount: 10, currency: 'EUR', country: 'DE' } },
		global: { provide: { params: {} } }
	} ).find( 'p' );

	it.each( [
		[ 'once', 'donate_interface-sepa-mandate' ],
		[ 'month', 'donate_interface-sepa-mandate-monthly' ],
		[ 'year', 'donate_interface-sepa-mandate-yearly' ]
	] )( 'shows the %s mandate', ( frequency, messageKey ) => {
		expect( mandateFor( frequency ).text() ).toBe( messageKey );
	} );

	it( 'shows no mandate for a frequency it doesn\'t know', () => {
		expect( mandateFor( 'monthly' ).exists() ).toBe( false );
	} );
} );
