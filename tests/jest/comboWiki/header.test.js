/* global describe it expect */

const VueTestUtils = require( '@vue/test-utils' );

const HeaderComponent = require( '../../../modules/ext.donationInterface.comboWiki/views/Header.vue' );

// Special:Donate builds assets_path from $wgScriptPath in Donate::addStylesScriptsAndViewport().
const ASSETS_PATH = '/extensions/DonationInterface/modules/ext.donationInterface.comboWiki/assets';

// wmfParams is what Donate::setClientVariables() sends, and init.js copies it into params.
function mountHeader( utmMedium ) {
	return VueTestUtils.mount( HeaderComponent, {
		global: {
			provide: {
				params: {
					assets_path: ASSETS_PATH,
					wmfParams: { utm_medium: utmMedium }
				}
			}
		}
	} );
}

describe( 'ComboWiki header', () => {
	it( 'shows the Foundation and Wikipedia logos for regular donations', () => {
		const wrapper = mountHeader( 'email' );

		const navLogo = wrapper.find( '.nav__logo' );
		expect( navLogo.attributes( 'src' ) ).toBe( `${ ASSETS_PATH }/logos/wikimedia-foundation-logo-landscape.png` );
		expect( wrapper.find( '.nav a' ).attributes( 'href' ) ).toBe( 'https://wikimediafoundation.org/' );
		expect( wrapper.find( '.nav-global__aside' ).exists() ).toBe( true );
	} );

	it( 'shows only the endowment logo for endowment donations', () => {
		const wrapper = mountHeader( 'endowment' );

		const navLogo = wrapper.find( '.nav__logo' );
		expect( navLogo.attributes( 'src' ) ).toBe( `${ ASSETS_PATH }/logos/wikimedia-endowment-logo.png` );
		expect( navLogo.attributes( 'alt' ) ).toBe( 'Wikimedia Endowment' );
		expect( wrapper.find( '.nav a' ).attributes( 'href' ) ).toBe( 'https://wikimediaendowment.org/' );
		expect( wrapper.findAll( '.nav__logo' ) ).toHaveLength( 1 );
		expect( wrapper.find( '.nav-global__aside' ).exists() ).toBe( false );
	} );
} );
