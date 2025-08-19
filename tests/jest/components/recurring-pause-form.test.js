/* global global describe it expect beforeEach afterEach jest */

const VueTestUtils = require( '@vue/test-utils' );
const RecurringContributionPauseForm = require( '../../../modules/ext.donationInterface.donorPortal/components/RecurringContributionPauseForm.vue' );
const router = require( '../../../modules/ext.donationInterface.donorPortal/router.js' );
const { recurring: contribution_mock } = require( '../mocks/contribution_mock.mock.js' );

describe( 'Recurring pause form component', () => {
    const durationOptions = [
        {
			id: '30days',
			value: 30,
			locale: '30 days'
		},
		{
			id: '60days',
			value: 60,
			locale: '60 days'
		}
    ];
    const defaultDuration = durationOptions[ 0 ];
    const submitFormMock = jest.fn();

    it( 'Pause Donations form renders successfully', () => {
        const wrapper = VueTestUtils.mount( RecurringContributionPauseForm, {
            props: {
                recurringContribution: contribution_mock,
                durationOptions,
                defaultDuration,
                submitForm: submitFormMock
            },
            global: {
                plugins: [ router ]
            }
        } );

        const pauseDonationViewBody = wrapper.find( '#pause-donations-form' );
        expect( pauseDonationViewBody.exists() ).toBe( true );
        expect( pauseDonationViewBody.html() ).toContain( 'donorportal-pause-recurring-heading' );
        expect( pauseDonationViewBody.html() ).toContain( 'donorportal-pause-recurring-subheading' );
        expect( pauseDonationViewBody.html() ).toContain( 'donorportal-pause-recurring-subtext' );
        expect( pauseDonationViewBody.html() ).toContain( 'donorportal-pause-recurring-specify-duration' );
        expect( pauseDonationViewBody.html() ).toContain( 'donorportal-pause-recurring-pause-button' );

        // Ensure duration option list are rendered and visible
        const pauseDonationsOptionsList = wrapper.findAll( '#radio-button-options-list' );
        expect( pauseDonationsOptionsList.length ).toBe( 2 );
        expect( pauseDonationViewBody.html() ).toContain( '30 days' );
        expect( pauseDonationViewBody.html() ).toContain( '60 days' );
    } );

    it( 'Pause Donations submits the duration successfully', async () => {
        const wrapper = VueTestUtils.mount( RecurringContributionPauseForm, {
            props: {
                recurringContribution: contribution_mock,
                durationOptions,
                defaultDuration,
                submitForm: submitFormMock
            },
            global: {
                plugins: [ router ]
            }
        } );

        const pauseDonationViewBody = wrapper.find( '#pause-donations-form' );
        const secondDurationOption = pauseDonationViewBody.find( `#option-${ durationOptions[ 1 ].id }` );

        await secondDurationOption.trigger( 'input' );

        const submitButton = pauseDonationViewBody.find( '#continue' );
        await submitButton.trigger( 'click' );

        expect( submitFormMock ).toBeCalledWith( `${ durationOptions[ 1 ].value }` );
    } );
} );
