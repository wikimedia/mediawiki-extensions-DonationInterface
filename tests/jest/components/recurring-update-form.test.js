/* global describe it expect beforeEach afterEach jest */

const VueTestUtils = require( '@vue/test-utils' );
const RecurringContributionUpdateForm = require( '../../../modules/ext.donationInterface.donorPortal/components/RecurringContributionUpdateForm.vue' );
const router = require( '../../../modules/ext.donationInterface.donorPortal/router.js' );
const { recurring_monthly: contribution_mock } = require( '../mocks/contribution_mock.mock.js' );
const { inactive_recurring: inactive_recurring_mock } = require( '../mocks/contribution_mock.mock.js' );

describe( 'Recurring update amount form component', () => {
	const submitUpdateFormMock = jest.fn();
	window.alert = jest.fn();

	it( 'Update Donations form renders successfully', async () => {
		const wrapper = VueTestUtils.mount( RecurringContributionUpdateForm, {
			props: {
				recurringContribution: contribution_mock,
				submitUpdateRecurring: submitUpdateFormMock,
				currencyRateArray: [],
				max: 10000
			},
			global: {
				plugins: [ router ]
			}
		} );
		await router.isReady();

		const updateDonationFormBody = wrapper.find( '#recurring-update-form' );
		expect( updateDonationFormBody.exists() ).toBe( true );
		expect( updateDonationFormBody.html() ).toContain( 'donorportal-update-recurring-heading' );
		expect( updateDonationFormBody.html() ).toContain( 'donorportal-update-recurring-text' );
		expect( updateDonationFormBody.html() ).toContain( 'donorportal-update-recurring-new-donation' );
		expect( updateDonationFormBody.html() ).toContain( 'donorportal-update-recurring-change-mind' );
		expect( updateDonationFormBody.html() ).toContain( 'donorportal-update-recurring-new-donation-effective-date' );
		expect( updateDonationFormBody.html() ).toContain( `donorportal-update-recurring-confirm:[${ contribution_mock.currency_symbol },,${ contribution_mock.currency }]` );

		// Ensure new amount input are rendered and visible
		expect( updateDonationFormBody.html() ).toContain( 'new-recurring-amount' );
	} );

	it( 'Update recurring amount submits successfully', async () => {
		const wrapper = VueTestUtils.mount( RecurringContributionUpdateForm, {
			props: {
				recurringContribution: contribution_mock,
				submitUpdateRecurring: submitUpdateFormMock,
				currencyRateArray: [],
				max: 10000
			},
			global: {
				plugins: [ router ]
			}
		} );
		await router.isReady();

		const updatedAmount = 100;

		const UpdateDonationsViewBody = wrapper.find( '#recurring-update-form' );
		const amountInput = UpdateDonationsViewBody.find( '#new-recurring-amount' );
		amountInput.element.value = updatedAmount;
		await amountInput.trigger( 'input' );
		await VueTestUtils.flushPromises();
		const submitButton = UpdateDonationsViewBody.find( '#submit-update-action' );
		await submitButton.trigger( 'click' );

		expect( submitUpdateFormMock ).toBeCalledWith( `${ updatedAmount }` );
	} );

	it( 'Update recurring amount submits fail due to not in range', async () => {
		const wrapper = VueTestUtils.mount( RecurringContributionUpdateForm, {
			props: {
				recurringContribution: contribution_mock,
				submitUpdateRecurring: submitUpdateFormMock,
				currencyRateArray: [],
				max: 10000
			},
			global: {
				plugins: [ router ]
			}
		} );
		await router.isReady();

		const updatedAmount = 0;
		const UpdateDonationsViewBody = wrapper.find( '#recurring-update-form' );
		const amountInput = UpdateDonationsViewBody.find( '#new-recurring-amount' );
		amountInput.element.value = updatedAmount;
		await amountInput.trigger( 'input' );
		await VueTestUtils.flushPromises();
		const submitButton = UpdateDonationsViewBody.find( '#submit-update-action' );
		await submitButton.trigger( 'click' );
		expect( submitUpdateFormMock ).toBeCalledTimes( 0 );
	} );

	it( 'Submits the updated amount when Enter is pressed in the amount field', async () => {
		// Local mock so the call history is fresh.
		const submitMock = jest.fn();
		const wrapper = VueTestUtils.shallowMount( RecurringContributionUpdateForm, {
			props: {
				recurringContribution: contribution_mock,
				submitUpdateRecurring: submitMock,
				currencyRateArray: [],
				max: 10000
			}
		} );

		const amountInput = wrapper.find( '#new-recurring-amount' );
		amountInput.element.value = 100;
		await amountInput.trigger( 'input' );
		await amountInput.trigger( 'keyup.enter' );

		expect( submitMock ).toHaveBeenCalledWith( '100' );
	} );

	it( 'Shows the annual currency-frequency label for a yearly contribution', () => {
		// Copying our default mock data, and updating frequency unit to 'year'.
		const yearlyContribution = JSON.parse( JSON.stringify( contribution_mock ) );
		yearlyContribution.frequency_unit = 'year';
		const wrapper = VueTestUtils.shallowMount( RecurringContributionUpdateForm, {
			props: {
				recurringContribution: yearlyContribution,
				submitUpdateRecurring: jest.fn(),
				currencyRateArray: [],
				max: 10000
			}
		} );

		const html = wrapper.find( '#recurring-update-form' ).html();
		expect( html ).toContain( 'donorportal-recurring-currency-annual' );
		expect( html ).not.toContain( 'donorportal-recurring-currency-monthly' );
	} );

	it( 'Prevents the default form submission', () => {
		const wrapper = VueTestUtils.shallowMount( RecurringContributionUpdateForm, {
			props: {
				recurringContribution: contribution_mock,
				submitUpdateRecurring: jest.fn(),
				currencyRateArray: [],
				max: 10000
			}
		} );

		const form = wrapper.find( '#form-upgrade' );
		const submitEvent = new Event( 'submit', { cancelable: true } );
		form.element.dispatchEvent( submitEvent );

		expect( submitEvent.defaultPrevented ).toBe( true );
	} );

	it( 'Falls back to N/A when the contribution has no next scheduled date', () => {
		const wrapper = VueTestUtils.shallowMount( RecurringContributionUpdateForm, {
			props: {
				recurringContribution: inactive_recurring_mock,
				submitUpdateRecurring: jest.fn(),
				currencyRateArray: [],
				max: 10000
			}
		} );

		expect( wrapper.find( '#recurring-update-form' ).html() )
			.toContain( 'donorportal-update-recurring-new-donation-effective-date:[N/A]' );
	} );
} );
