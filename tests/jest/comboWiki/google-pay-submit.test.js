/* global describe it expect jest */

jest.mock( '../../../modules/ext.donationInterface.comboWiki/walletAvailability.js', () => ( {
	getGooglePaymentsClient: jest.fn(),
	isGooglePayAvailable: jest.fn()
} ) );

const VueTestUtils = require( '@vue/test-utils' );
const { getGooglePaymentsClient, isGooglePayAvailable } = require( '../../../modules/ext.donationInterface.comboWiki/walletAvailability.js' );
const GooglePayComponent = require( '../../../modules/ext.donationInterface.comboWiki/components/GooglePayComponent.vue' );

const paymentData = {
	email: 'jo@example.org',
	paymentMethodData: {
		info: {
			cardDetails: '1111',
			cardNetwork: 'MASTERCARD',
			billingAddress: {
				name: 'Jo Doe',
				postalCode: '94105',
				administrativeArea: 'CA',
				locality: 'San Francisco',
				address1: '1 Main St'
			}
		},
		tokenizationData: { token: 'google-token' }
	}
};

/**
 * Pays with Google Pay as the given payment method and returns the params the
 * component submits to the donate API.
 *
 * @param {string} paymentMethod ComboWiki payment method, which picks the gateway
 * @return {Promise<Object>}
 */
async function submittedParams( paymentMethod ) {
	isGooglePayAvailable.mockResolvedValue( true );
	getGooglePaymentsClient.mockReturnValue( {
		loadPaymentData: jest.fn().mockResolvedValue( paymentData )
	} );
	const wrapper = VueTestUtils.mount( GooglePayComponent, {
		props: { donation: { amount: 10, currency: 'USD', country: 'US' }, paymentMethod },
		global: {
			provide: {
				params: { gravyConfiguration: {}, adyenConfiguration: {} }
			}
		}
	} );
	wrapper.vm.onButtonClicked();
	await VueTestUtils.flushPromises();
	return wrapper.emitted( 'submit' )[ 0 ][ 0 ];
}

describe( 'ComboWiki Google Pay submit params for each gateway', () => {
	it( 'sends Gravy the card suffix and scheme its Google Pay API requires', async () => {
		const params = await submittedParams( 'googlepay' );
		expect( params ).toMatchObject( {
			payment_token: 'google-token',
			card_suffix: '1111',
			card_scheme: 'MASTERCARD'
		} );
		expect( params ).not.toHaveProperty( 'payment_submethod' );
	} );

	it( 'sends Adyen the card network as its submethod, without the Gravy-only card fields', async () => {
		const params = await submittedParams( 'adyen_googlepay' );
		expect( params ).toMatchObject( {
			payment_token: 'google-token',
			payment_submethod: 'mc'
		} );
		expect( params ).not.toHaveProperty( 'card_suffix' );
		expect( params ).not.toHaveProperty( 'card_scheme' );
	} );
} );
