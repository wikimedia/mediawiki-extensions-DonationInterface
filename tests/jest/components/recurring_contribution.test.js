/* global global describe it expect beforeEach afterEach*/
const VueTestUtils = require( '@vue/test-utils' );
const RecurringContributionComponent = require( '../../../modules/ext.donationInterface.donorPortal/components/RecurringContributionComponent.vue' );
const { recurring: recurring_mock } = require( '../mocks/contribution_mock.mock.js' );
const { inactive_recurring: inactive_recurring_mock } = require( '../mocks/contribution_mock.mock.js' );
const { inactive_yearly_recurring: inactive_yearly_recurring_mock } = require( '../mocks/contribution_mock.mock.js' );
const { legacy_paypal_recurring: legacy_paypal_recurring_mock } = require( '../mocks/contribution_mock.mock.js' );
const { paused_recurring: paused_recurring_mock } = require( '../mocks/contribution_mock.mock.js' );
const { cancelled_recurring: cancelled_recurring_mock } = require( '../mocks/contribution_mock.mock.js' );
const { when } = require( 'jest-when' );
const DonorDataMock = require( '../mocks/donor_data.mock.js' );
const entities = require( 'entities' );

describe( 'Active recurring contribution test', () => {
	beforeEach( () => {
		when( global.mw.config.get ).calledWith( 'donorData' ).mockReturnValue( DonorDataMock );
		when( global.mw.config.get ).calledWith( 'newDonationUrl' ).mockReturnValue( 'http://donate.test' );
	} );
	it( 'Active recurring contribution renders successfully', () => {
		const wrapper = VueTestUtils.shallowMount( RecurringContributionComponent, {
			props: {
				contribution: recurring_mock,
				isActive: true
			}
		} );

		const element = wrapper.find( '.is-recurring' );
		expect( element.exists() ).toBe( true );
		expect( element.html() ).toContain( recurring_mock.amount_frequency_key );
		expect( element.html() ).toContain( recurring_mock.amount_formatted );
		expect( element.html() ).toContain( recurring_mock.currency );
		expect( element.html() ).toContain( recurring_mock.payment_method );
		expect( element.html() ).toContain( recurring_mock.next_sched_contribution_date_formatted );
		expect( element.html() ).not.toContain( `<a href="#/cancel-donations/${ recurring_mock.id }" class="link"> donorportal-recurring-cancel </a>` );
		expect( element.html() ).not.toContain( `<a target="_self" href="#/update-donations/${ recurring_mock.id }"` );
		expect( element.html() ).not.toContain( `<a href="#/pause-donations/${ recurring_mock.id }" class="link"> donorportal-recurring-pause </a>` );
	} );

	it( 'Donor contact details component renders successfully', () => {
		const wrapper = VueTestUtils.shallowMount( RecurringContributionComponent, {
			props: {
				contribution: inactive_recurring_mock,
				isActive: false
			}
		} );

		const element = wrapper.find( '.is-lapsed' );
		expect( element.exists() ).toBe( true );
		expect( element.html() ).toContain( 'donorportal-last-amount-and-date' );
		expect( element.html() ).toContain( inactive_recurring_mock.amount_formatted );
		expect( element.html() ).toContain( inactive_recurring_mock.currency );
		expect( element.html() ).not.toContain( inactive_recurring_mock.payment_method );
		expect( element.html() ).toContain( inactive_recurring_mock.last_contribution_date_formatted );
		expect( element.html() ).toContain( inactive_recurring_mock.restart_key );
		expect( element.html() ).toContain( '<a target="_blank" href="http://donate.test' );
	} );
	it( 'Only adds last contribution if there if there is any on record', () => {
		const wrapper = VueTestUtils.shallowMount( RecurringContributionComponent, {
			props: {
				contribution: Object.assign( inactive_recurring_mock, {
					hasLastContribution: false
				} ),
				isActive: false
			}
		} );
        const element = wrapper.find( '.is-lapsed' );
        expect( element.exists() ).toBe( true );
        expect( element.html() ).not.toContain( 'donorportal-last-amount-and-date' );
        expect( entities.decodeHTML( element.html() ) ).toContain( '<a target="_blank" href="http://donate.test/?preSelect=100&country=US&frequency=monthly' );
    } );
	it( 'Render is processing status correctly', () => {
		const wrapper = VueTestUtils.shallowMount( RecurringContributionComponent, {
			props: {
				contribution: Object.assign( recurring_mock, {
					is_processing: true
				} ),
				isActive: true
			}
		} );

		const element = wrapper.find( '.is-processing' );
		expect( element.exists() ).toBe( true );
		expect( element.html() ).toContain( 'donorportal-processing' );
		expect( element.html() ).not.toContain( `<a href="#/cancel-donations/${ recurring_mock.id }" class="link"> donorportal-recurring-cancel </a>` );
		expect( element.html() ).not.toContain( `<a target="_self" href="#/update-donations/${ recurring_mock.id }"` );
		expect( element.html() ).not.toContain( `<a href="#/pause-donations/${ recurring_mock.id }" class="link"> donorportal-recurring-pause </a>` );
	} );
	it( 'Shows PayPal notice and hides manage button for unmanageable legacy PayPal donations', () => {
		const wrapper = VueTestUtils.shallowMount( RecurringContributionComponent, {
			props: {
				contribution: legacy_paypal_recurring_mock,
				isActive: true
			}
		} );

		const card = wrapper.find( '.is-recurring' );
		expect( card.exists() ).toBe( true );

		const notice = wrapper.find( '.dp-paypal-notice' );
		expect( notice.exists() ).toBe( true );
		expect( notice.html() ).toContain( 'donorportal-update-donation-legacy-paypal-disable-text' );

		expect( card.html() ).not.toContain( 'donorportal-manage-donation' );
		expect( card.html() ).not.toContain( 'donorportal-edit-text' );
	} );

	it( 'Render correct new donation link', () => {
		const wrapper = VueTestUtils.shallowMount( RecurringContributionComponent, {
			props: {
				contribution: Object.assign( inactive_yearly_recurring_mock, {
					hasLastContribution: false
				} ),
				isActive: false
			}
		} );
		const element = wrapper.find( '.is-lapsed' );
		expect( element.exists() ).toBe( true );
		expect( element.html() ).not.toContain( 'donorportal-last-amount-and-date' );
		expect( entities.decodeHTML( element.html() ) ).toContain( '<a target="_blank" href="http://donate.test/?preSelect=150&country=BR&frequency=annual' );
	} );

	it( 'Renders the paused status for a paused recurring contribution', () => {
		const wrapper = VueTestUtils.shallowMount( RecurringContributionComponent, {
			props: {
				contribution: paused_recurring_mock,
				isActive: true
			}
		} );

		const statusTag = wrapper.find( '.tag' );
		expect( statusTag.exists() ).toBe( true );
		expect( statusTag.text() ).toContain( 'donorportal-recurring-status-paused' );
	} );

	it( 'Renders the cancelled status for a donor-cancelled recurring contribution', () => {
		const wrapper = VueTestUtils.shallowMount( RecurringContributionComponent, {
			props: {
				contribution: cancelled_recurring_mock,
				isActive: false
			}
		} );

		const statusTag = wrapper.find( '.tag' );
		expect( statusTag.exists() ).toBe( true );
		expect( statusTag.text() ).toContain( 'donorportal-recurring-status-cancelled' );
	} );

	it( 'Evaluates the payment-method email template when the edit popup is opened', async () => {
		const wrapper = VueTestUtils.mount( RecurringContributionComponent, {
			props: {
				contribution: recurring_mock,
				isActive: true
			}
		} );

		const editLink = wrapper.find( '.link' );
		expect( editLink.html() ).toContain( 'donorportal-edit-text' );
		await editLink.trigger( 'click' );

		const popupBody = wrapper.find( '.popup-body' );
		expect( popupBody.exists() ).toBe( true );
		expect( popupBody.html() ).toContain( 'donorportal-update-payment-method-explanation-template-to' );
		expect( popupBody.html() ).toContain( DonorDataMock.name );
		expect( popupBody.html() ).toContain( DonorDataMock.email );
	} );
} );
