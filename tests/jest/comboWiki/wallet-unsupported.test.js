/* global describe it expect jest beforeEach */

jest.mock( '../../../modules/ext.donationInterface.comboWiki/utils.js', () => ( {
	loadScript: jest.fn()
} ) );

const VueTestUtils = require( '@vue/test-utils' );
const { loadScript } = require( '../../../modules/ext.donationInterface.comboWiki/utils.js' );
const ApplePayComponent = require( '../../../modules/ext.donationInterface.comboWiki/components/ApplePayComponent.vue' );
const GooglePayComponent = require( '../../../modules/ext.donationInterface.comboWiki/components/GooglePayComponent.vue' );

const OTHER_WAYS_URL = 'https://example.org/ways-to-give';

function mountWallet( component ) {
	return VueTestUtils.mount( component, {
		props: { donation: { amount: 10, currency: 'USD', country: 'US' } },
		global: {
			provide: {
				params: {
					DonationInterfaceOtherWaysURL: OTHER_WAYS_URL,
					gravyConfiguration: {
						appleScript: 'https://applepay.cdn-apple.com/jsapi/v1/apple-pay-sdk.js',
						googleScript: 'https://pay.google.com/gp/p/js/pay.js'
					}
				}
			}
		}
	} );
}

/**
 * PaymentMethodForm only shows a wallet once its check passed, so a wallet that fails
 * the check again once open tells the donor instead of leaving them with nothing to click.
 *
 * @param {Object} wrapper The mounted wallet component
 * @param {string} messageKey The unsupported message the donor should see
 */
async function expectUnsupportedError( wrapper, messageKey ) {
	await VueTestUtils.flushPromises();
	expect( wrapper.emitted( 'error' ) ).toEqual( [ [ {
		type: 'validation',
		messages: [ `${ messageKey }:[${ OTHER_WAYS_URL }]` ]
	} ] ] );
}

describe( 'ComboWiki Apple Pay the browser cannot use', () => {
	beforeEach( () => {
		window.mw.log = { error: jest.fn() };
		delete window.ApplePaySession;
	} );

	it( 'shows the donor an error when Apple\'s SDK fails to load', async () => {
		loadScript.mockReturnValue( Promise.reject( new Error( 'blocked' ) ) );
		await expectUnsupportedError( mountWallet( ApplePayComponent ), 'donate_interface-error-msg-apple_pay_unsupported' );
	} );

	it( 'shows the donor an error when the browser has no ApplePaySession', async () => {
		loadScript.mockReturnValue( Promise.resolve() );
		await expectUnsupportedError( mountWallet( ApplePayComponent ), 'donate_interface-error-msg-apple_pay_unsupported' );
	} );
} );

// walletAvailability.js keeps the first client it creates, so every test shares this one
const googleClient = { isReadyToPay: jest.fn(), createButton: jest.fn() };
window.google = { payments: { api: { PaymentsClient: jest.fn( () => googleClient ) } } };

// It also keeps an answered readiness check for the page, so the cases where the
// check never answers (load failure, rejected check) have to run first.
describe( 'ComboWiki Google Pay the browser cannot use', () => {
	beforeEach( () => {
		window.mw.log = { error: jest.fn() };
		googleClient.createButton.mockClear();
	} );

	it( 'shows the donor an error when pay.js fails to load', async () => {
		loadScript.mockReturnValue( Promise.reject( new Error( 'blocked' ) ) );
		await expectUnsupportedError( mountWallet( GooglePayComponent ), 'donate_interface-error-msg-google_pay_unsupported' );
	} );

	it( 'shows the donor an error when the readiness check fails', async () => {
		loadScript.mockReturnValue( Promise.resolve() );
		googleClient.isReadyToPay.mockRejectedValue( new Error( 'third-party cookies blocked' ) );
		await expectUnsupportedError( mountWallet( GooglePayComponent ), 'donate_interface-error-msg-google_pay_unsupported' );
		expect( googleClient.createButton ).not.toHaveBeenCalled();
	} );

	it( 'shows the donor an error when Google Pay is not ready to pay', async () => {
		loadScript.mockReturnValue( Promise.resolve() );
		googleClient.isReadyToPay.mockResolvedValue( { result: false } );
		await expectUnsupportedError( mountWallet( GooglePayComponent ), 'donate_interface-error-msg-google_pay_unsupported' );
		expect( googleClient.createButton ).not.toHaveBeenCalled();
	} );
} );
