/* global describe it expect */

const VueTestUtils = require( '@vue/test-utils' );

const DonateButton = require( '../../../modules/ext.donationInterface.comboWiki/components/DonateButton.vue' );
const SEPAComponent = require( '../../../modules/ext.donationInterface.comboWiki/components/SEPAComponent.vue' );
const AdyenSEPA = require( '../../../modules/ext.donationInterface.comboWiki/components/AdyenSEPA.vue' );
const AdyenCardForm = require( '../../../modules/ext.donationInterface.comboWiki/components/AdyenCardForm.vue' );
const GravyCardForm = require( '../../../modules/ext.donationInterface.comboWiki/components/GravyCardForm.vue' );

describe( 'ComboWiki donate button', () => {
	const mountButton = ( props ) => VueTestUtils.mount( DonateButton, { props } );

	it( 'shows the amount and currency', () => {
		const button = mountButton( { formattedAmount: '$20.80', currency: 'USD' } );
		expect( button.text() ).toBe( 'combowiki-donate-amount:[$20.80,USD]' );
	} );

	it( 'shows a lock icon', () => {
		const button = mountButton( { formattedAmount: '$20.00', currency: 'USD' } );
		expect( button.find( '.combo-wiki__donate-button-lock' ).exists() ).toBe( true );
	} );

	it( 'falls back to plain Donate when there is no amount yet', () => {
		const button = mountButton( { formattedAmount: '', currency: 'USD' } );
		expect( button.text() ).toBe( 'donate_interface-submit-button' );
	} );

	it( 'shows ... and is disabled while submitting', () => {
		const button = mountButton( { formattedAmount: '$20.00', currency: 'USD', submitting: true } );
		expect( button.text() ).toBe( '...' );
		expect( button.find( 'button' ).attributes( 'disabled' ) ).toBeDefined();
		expect( button.find( '.combo-wiki__donate-button-lock' ).exists() ).toBe( false );
	} );

	it( 'emits click', async () => {
		const button = mountButton( { formattedAmount: '$20.00', currency: 'USD' } );
		await button.find( 'button' ).trigger( 'click' );
		expect( button.emitted( 'click' ) ).toHaveLength( 1 );
	} );
} );

// The forms that charge the donation themselves format the amount for their button.
// Without gateway config they don't load the gateway, but still render the button.
describe.each( [
	[ 'Gravy SEPA', SEPAComponent ],
	[ 'Adyen SEPA', AdyenSEPA ],
	[ 'Adyen card', AdyenCardForm ],
	[ 'Gravy card', GravyCardForm ]
] )( 'ComboWiki %s donate button', ( label, component ) => {
	const buttonFor = ( donation, language ) => VueTestUtils.mount( component, {
		props: { donation: Object.assign( { frequency: 'once' }, donation ) },
		global: { provide: { params: { language, gravyConfiguration: {} } } }
	} ).findComponent( DonateButton );

	it.each( [
		// 20.80 is a 20 USD donation with the 4% fee, as PaymentForm's chargedDonation passes it
		[ 'a USD donation with the fee', { amount: 20.8, currency: 'USD', country: 'US' }, 'en', '$20.80' ],
		[ 'a USD donation without the fee', { amount: 20, currency: 'USD', country: 'US' }, 'en', '$20.00' ],
		[ 'a EUR donation in German', { amount: 10, currency: 'EUR', country: 'DE' }, 'de', '10,00 €' ],
		[ 'a JPY donation, which has no decimals', { amount: 500, currency: 'JPY', country: 'JP' }, 'ja', '￥500' ]
	] )( 'passes the formatted amount and currency for %s', ( caseLabel, donation, language, formattedAmount ) => {
		const button = buttonFor( donation, language );
		expect( button.props( 'formattedAmount' ) ).toBe( formattedAmount );
		expect( button.props( 'currency' ) ).toBe( donation.currency );
	} );

	it( 'passes an empty amount when there is none yet', () => {
		const button = buttonFor( { amount: null, currency: 'USD', country: 'US' }, 'en' );
		expect( button.props( 'formattedAmount' ) ).toBe( '' );
	} );
} );
