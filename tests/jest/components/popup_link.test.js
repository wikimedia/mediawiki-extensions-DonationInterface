/* global describe it expect beforeEach afterEach*/

const VueTestUtils = require( '@vue/test-utils' );
const PopupLinkComponent = require( '../../../modules/ext.donationInterface.donorPortal/components/PopupLink.vue' );

describe( 'Popup link component', () => {
	it( 'Slot rendering', () => {
		const wrapper = VueTestUtils.shallowMount( PopupLinkComponent, {
			slots: {
				'link-text': 'Open popup'
			}
		} );
		const link = wrapper.find( '.link' );
		expect( link.exists() ).toBe( true );
		expect( link.html() ).toContain( 'Open popup' );
	} );
	it( 'Click show model successfully', async () => {
		const wrapper = VueTestUtils.shallowMount( PopupLinkComponent, {
		} );
		const link = wrapper.find( '.link' );
		await link.trigger( 'click' );
		const modal = wrapper.find( '.popup-overlay' );
		// now modal should appear
		expect( modal.exists() ).toBe( true );
		// and Close button rendered
		expect( modal.html() ).toContain( 'donorportal-close' );
		const close = wrapper.find( '.popup-close' );
		// click Close button
		await close.trigger( 'click' );
		const modalAfterClose = wrapper.find( '.popup-overlay' );
		// modal disappears
		expect( modalAfterClose.exists() ).toBe( false );
	} );
} );
