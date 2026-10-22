/* global describe it expect jest */

const VueTestUtils = require( '@vue/test-utils' );
const { reactive } = require( 'vue' );
const { CdxSelect } = require( '@wikimedia/codex' );

const PaymentForm = require( '../../../modules/ext.donationInterface.comboWiki/views/PaymentForm.vue' );
const PaymentMethodForm = require( '../../../modules/ext.donationInterface.comboWiki/components/PaymentMethodForm.vue' );

// cdx-field gives its input a generated id, so find the inputs by placeholder (the i18n key here)
const input = ( wrapper, key ) => wrapper.find( `input[placeholder^="donate_interface-donor-${ key }"]` );
const button = ( wrapper, text ) => wrapper.findAll( 'button' ).find( ( b ) => b.text() === text );

/**
 * Mounts the payment methods for a country where card and SEPA ask for the same details,
 * with the donation updated from onDonorDetailsChange as PaymentForm does.
 *
 * @param {Object} donation
 * @param {Object} [extraParams]
 * @return {Object} The wrapper
 */
function mountCardAndSepa( donation, extraParams = {} ) {
	const nameAndEmail = { first_name: true, email: true };
	return VueTestUtils.mount( PaymentMethodForm, {
		props: {
			donation,
			onOnDonorDetailsChange: ( details ) => {
				Object.assign( donation, { firstName: details.first_name, email: details.email } );
			}
		},
		global: {
			provide: { params: Object.assign( {
				gateway: 'gravy',
				paymentMethods: [
					{ method: 'cc', gateway: 'gravy' },
					{ method: 'rtbt', submethod: 'sepadirectdebit', gateway: 'gravy' }
				],
				DonationInterfaceFormFields: { method: { cc: nameAndEmail, sepadirectdebit: nameAndEmail } }
			}, extraParams ) },
			// Only the shared details form matters here, not the card or SEPA parts
			stubs: { 'card-form': true, 'sepa-form': true }
		}
	} );
}

const newDonation = () => reactive( {
	frequency: 'monthly', amount: 10, currency: 'EUR', country: 'DE', paymentMethod: 'card'
} );

describe( 'ComboWiki donor details in the donation', () => {
	it( 'copies what the donor typed into the donation, like the amount', () => {
		const wrapper = VueTestUtils.shallowMount( PaymentForm, {
			global: {
				provide: { params: {
					amount: '0.00',
					country: 'AU',
					currency: 'AUD',
					payment_method: 'paypal',
					gateway: 'gravy',
					language: 'en',
					wgDonationInterfaceAmountRules: { currency: 'AUD', min: 1, max: 12000 },
					wgDonationInterfaceCurrencyRates: { AUD: 1 }
				} },
				stubs: { 'sms-optin': true }
			}
		} );

		wrapper.findComponent( PaymentMethodForm ).vm.$emit( 'onDonorDetailsChange', {
			state_province: 'NSW',
			first_name: 'Jo'
		} );

		expect( wrapper.vm.donation ).toMatchObject( { stateProvince: 'NSW', firstName: 'Jo' } );
	} );

	it( 'reports a field once the donor leaves it, not on every keystroke', async () => {
		const wrapper = mountCardAndSepa( newDonation() );
		const firstName = input( wrapper, 'first_name' );
		for ( const typed of [ 'l', 'la', 'lau', 'laur', 'laura' ] ) {
			await firstName.setValue( typed );
		}
		expect( wrapper.emitted( 'onDonorDetailsChange' ) ).toBeUndefined();

		await firstName.trigger( 'blur' );
		expect( wrapper.emitted( 'onDonorDetailsChange' ) ).toEqual( [ [ { first_name: 'laura', email: undefined } ] ] );
	} );

	it( 'reports a state picked from the list at once', async () => {
		const donation = newDonation();
		const wrapper = VueTestUtils.mount( PaymentMethodForm, {
			props: { donation: Object.assign( donation, { paymentMethod: 'card', country: 'AU', stateProvince: null } ) },
			global: { provide: { params: {
				gateway: 'gravy',
				paymentMethods: [ { method: 'cc', gateway: 'gravy' } ],
				DonationInterfaceFormFields: { method: { cc: { state_province: true } } },
				DonationInterfaceStateProvinceOptions: [ { value: 'NSW', label: 'New South Wales' } ]
			} }, stubs: { 'card-form': true } }
		} );

		wrapper.findComponent( CdxSelect ).vm.$emit( 'update:selected', 'NSW' );
		expect( wrapper.emitted( 'onDonorDetailsChange' ) ).toEqual( [ [ { state_province: 'NSW' } ] ] );
	} );

	it( 'keeps what the donor typed when they switch to another method with the same fields', async () => {
		const wrapper = mountCardAndSepa( newDonation() );
		await input( wrapper, 'first_name' ).setValue( 'Jo' );
		await input( wrapper, 'first_name' ).trigger( 'blur' );
		await input( wrapper, 'email' ).setValue( 'jo@example.org' );
		await input( wrapper, 'email' ).trigger( 'blur' );

		await button( wrapper, 'combowiki-method-sepa' ).trigger( 'click' );
		expect( input( wrapper, 'first_name' ).element.value ).toBe( 'Jo' );

		await button( wrapper, 'combowiki-method-card' ).trigger( 'click' );
		expect( input( wrapper, 'first_name' ).element.value ).toBe( 'Jo' );
		expect( input( wrapper, 'email' ).element.value ).toBe( 'jo@example.org' );
	} );
} );
