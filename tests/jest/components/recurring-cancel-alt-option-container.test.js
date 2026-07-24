/* global describe it expect jest */

const VueTestUtils = require( '@vue/test-utils' );
const RecurringContributionCancelAltOptionContainer = require(
	'../../../modules/ext.donationInterface.donorPortal/components/RecurringContributionCancelAltOptionContainer.vue' );

describe( 'Recurring cancel alternative-option container', () => {
	it( 'Renders a clickable option with its button and classes, and runs the action on click', async () => {
		const action = jest.fn();
		const wrapper = VueTestUtils.mount( RecurringContributionCancelAltOptionContainer, {
			props: {
				header: 'Alternative header',
				text: 'Alternative text',
				buttonText: 'Do the thing',
				isClickable: true,
				extraClasses: 'is-recurring',
				action
			}
		} );

		const box = wrapper.find( '#donorportal-cancel-alt-option' );
		expect( box.html() ).toContain( 'Alternative header' );
		expect( box.html() ).toContain( 'Alternative text' );
		expect( box.html() ).toContain( 'Do the thing' );
		expect( box.classes() ).toContain( 'box--clickable' );
		expect( box.classes() ).toContain( 'is-recurring' );

		await box.trigger( 'click' );
		expect( action ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'Hides the button and ignores clicks when not clickable', async () => {
		const action = jest.fn();
		const wrapper = VueTestUtils.mount( RecurringContributionCancelAltOptionContainer, {
			props: {
				header: 'Alternative header',
				text: 'Alternative text',
				buttonText: 'Do the thing',
				action
			}
		} );

		const box = wrapper.find( '#donorportal-cancel-alt-option' );
		expect( box.classes() ).not.toContain( 'box--clickable' );
		expect( box.html() ).not.toContain( 'Do the thing' );

		await box.trigger( 'click' );
		expect( action ).not.toHaveBeenCalled();
	} );

	it( 'Falls back to a no-op action so a clickable option without an action does not crash on click', async () => {
		// action intentionally omitted, so the default () => {} is used.
		const wrapper = VueTestUtils.mount( RecurringContributionCancelAltOptionContainer, {
			props: {
				header: 'Alternative header',
				text: 'Alternative text',
				buttonText: 'Do the thing',
				isClickable: true
			}
		} );

		const box = wrapper.find( '#donorportal-cancel-alt-option' );
		await box.trigger( 'click' );

		expect( box.exists() ).toBe( true );
	} );
} );
