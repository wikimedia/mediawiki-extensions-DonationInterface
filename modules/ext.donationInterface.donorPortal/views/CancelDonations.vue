<template>
	<div id="cancel-donations-form">
		<recurring-cancel-form
			v-if="flags.showForm"
			:recurring-contribution="recurringContribution"
			:submit-pause-recurring-form="submitPauseRecurringDuration"
			:duration-options="durationOptions"
			:default-duration="durationOptions[0]"
			:proceed-cancel-action="proceedCancelAction"></recurring-cancel-form>
		<recurring-cancel-confirmation
			v-else-if="flags.showConfirmation"
			:recurring-contribution="recurringContribution"
			:submit-cancel-recurring-form="submitCancelRecurring"
		></recurring-cancel-confirmation>
		<recurring-cancel-success v-else-if="flags.donationCancelSuccessful" :recurring-contribution="recurringContribution"></recurring-cancel-success>
		<recurring-cancel-error v-else-if="flags.donationCancelError" :error-code="cancelErrorCode"></recurring-cancel-error>
		<recurring-pause-success v-else-if="flags.donationPauseSuccessful" :next-sched-contribution-date="nextSchedContributionDate"></recurring-pause-success>
		<recurring-pause-error
			v-else-if="flags.donationPauseError"
			:error-code="pauseErrorCode"
			fallback-message-key="donorportal-pause-failure"></recurring-pause-error>
	</div>
</template>

<script>
const { defineComponent, ref, reactive } = require( 'vue' );
const { useRoute } = require( 'vue-router' );
const trackingParams = require( '../trackingParams.js' );
const RecurringContributionCancelForm = require( '../components/RecurringContributionCancelForm.vue' );
const RecurringContributionCancelSuccessful = require( '../components/RecurringContributionCancelSuccess.vue' );
const RecurringContributionCancelConfirmation = require( '../components/RecurringContributionCancelConfirmation.vue' );
const RecurringContributionPauseSuccess = require( '../components/RecurringContributionPauseSuccess.vue' );
const ErrorComponent = require( '../components/ErrorComponent.vue' );
const { requestRecurringPause, requestRecurringCancel } = require( '../ApiUtils.js' );

module.exports = exports = defineComponent( {
	name: 'CancelDonationsView',
	components: {
		'recurring-cancel-form': RecurringContributionCancelForm,
		'recurring-cancel-success': RecurringContributionCancelSuccessful,
		'recurring-cancel-confirmation': RecurringContributionCancelConfirmation,
		'recurring-pause-success': RecurringContributionPauseSuccess,
		'recurring-pause-error': ErrorComponent,
		'recurring-cancel-error': ErrorComponent
	},
	setup() {
		const route = useRoute();
		const donorData = mw.config.get( 'donorData' );
		const contributionRecurId = route.params.id;

		let recurringContributionRecord = donorData
			.recurringContributions
			.filter( ( contribution ) => Number( contribution.id ) === Number( contributionRecurId ) )[ 0 ];
		if ( !recurringContributionRecord ) {
			recurringContributionRecord = {};
		}

		const recurringContribution = ref( recurringContributionRecord );
		const nextSchedContributionDate = ref( recurringContributionRecord.next_sched_contribution_date );
		const pauseErrorCode = ref( '' );
		const cancelErrorCode = ref( '' );
		const flags = reactive( {
			showForm: recurringContributionRecord.can_modify,
			donationCancelSuccessful: false,
			donationCancelError: false,
			showConfirmation: !recurringContributionRecord.can_modify,
			donationPauseSuccessful: false,
			donationPauseError: false
		} );

		const submitPauseRecurringDuration = ( duration ) => {
			const durationInDays = `${ duration } Days`;
			const params = {
				duration: durationInDays,
				next_sched_contribution_date: nextSchedContributionDate.value,
				is_from_save_flow: true
			};
			trackingParams.addTo( params );
			requestRecurringPause( recurringContributionRecord, params ).then( ( data ) => {
				// TODO: Set next scheduled date in global store
				nextSchedContributionDate.value = data.result.next_sched_contribution_date;
				flags.showForm = false;
				flags.donationPauseSuccessful = true;
			} ).catch( ( code ) => {
				pauseErrorCode.value = code || 'unknown';
				flags.donationPauseError = true;
				flags.showForm = false;
			} );
		};

		const submitCancelRecurring = ( reason ) => {
			const params = {
				reason
			};
			trackingParams.addTo( params );
			requestRecurringCancel( recurringContributionRecord, params ).then( () => {
				// TODO: Set cancel state in global store
				flags.showForm = false;
				flags.showConfirmation = false;
				flags.donationCancelSuccessful = true;
			} ).catch( ( code ) => {
				cancelErrorCode.value = code || 'unknown';
				flags.donationCancelError = true;
				flags.showConfirmation = false;
				flags.showForm = false;
			} );
		};

		const proceedCancelAction = () => {
			flags.showForm = false;
			flags.showConfirmation = true;
		};

		return {
			recurringContribution,
			nextSchedContributionDate,
			pauseErrorCode,
			cancelErrorCode,
			flags,
			submitPauseRecurringDuration,
			submitCancelRecurring,
			proceedCancelAction
		};
	},
	computed: {
		durationOptions() {
			return [
				{
					id: '30days',
					value: 30,
					locale: this.$i18n( 'donorportal-pause-recurring-days', 30 ).text()
				},
				{
					id: '60days',
					value: 60,
					locale: this.$i18n( 'donorportal-pause-recurring-days', 60 ).text()
				},
				{
					id: '90days',
					value: 90,
					locale: this.$i18n( 'donorportal-pause-recurring-days', 90 ).text()
				}
			];
		}
	}
} );
</script>
