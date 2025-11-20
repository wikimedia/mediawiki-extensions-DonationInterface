/* global describe it expect beforeEach afterEach jest */

const VueTestUtils = require( '@vue/test-utils' );
const RecurringContributionUpdateForm = require( '../../../modules/ext.donationInterface.donorPortal/components/RecurringContributionUpdateForm.vue' );
const router = require( '../../../modules/ext.donationInterface.donorPortal/router.js' );
const { recurring: contribution_mock } = require( '../mocks/contribution_mock.mock.js' );

describe( 'Recurring update amount form component', () => {
	const submitUpdateFormMock = jest.fn();
	window.alert = jest.fn();

	it( 'Update Donations form renders successfully', () => {
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
} );
