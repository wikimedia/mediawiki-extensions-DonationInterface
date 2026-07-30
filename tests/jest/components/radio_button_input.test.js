/* global describe it expect */

const VueTestUtils = require( '@vue/test-utils' );
const RadioButtonInput = require(
	'../../../modules/ext.donationInterface.donorPortal/components/RadioButtonInput.vue' );

const optionValue = 30;
const initiallySelectedValue = 60;

const radioProps = {
	id: '30days',
	label: '30 days',
	value: optionValue,
	modelValue: initiallySelectedValue,
	name: 'duration'
};

describe( 'Radio Button Input Component', () => {
	it( 'Renders successfully', () => {
		const wrapper = VueTestUtils.shallowMount( RadioButtonInput, { props: radioProps } );

		const element = wrapper.find( '#radio-button-options-list' );
		expect( element.exists() ).toBe( true );

		const radioInput = wrapper.find( 'input' );
		expect( radioInput.attributes( 'id' ) ).toBe( 'option-30days' );
		expect( radioInput.attributes( 'name' ) ).toBe( 'duration' );
		expect( radioInput.attributes( 'value' ) ).toBe( '30' );

		const labelElement = wrapper.find( 'label' );
		expect( labelElement.attributes( 'for' ) ).toBe( 'option-30days' );
		expect( labelElement.text() ).toBe( '30 days' );
	} );

	it( 'Emits update:modelValue when the option is selected', async () => {
		const wrapper = VueTestUtils.shallowMount( RadioButtonInput, { props: radioProps } );

		const radioInput = wrapper.find( 'input' );
		await radioInput.trigger( 'input' );

		const emittedUpdates = wrapper.emitted( 'update:modelValue' );
		expect( emittedUpdates ).toHaveLength( 1 );
		expect( emittedUpdates[ 0 ] ).toEqual( [ '30' ] );
	} );

	it( 'Tracks the checked option through v-model', async () => {
		const wrapper = VueTestUtils.shallowMount( RadioButtonInput, { props: radioProps } );

		const radioInput = wrapper.find( 'input' );
		expect( wrapper.vm.selectedValue ).toBe( initiallySelectedValue );

		await radioInput.setValue( true );

		expect( wrapper.vm.selectedValue ).toBe( optionValue );
	} );
} );
