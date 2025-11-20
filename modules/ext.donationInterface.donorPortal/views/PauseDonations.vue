<template>
	<div id="pause-donations">
		<recurring-pause-form
			v-if="flags.showDonationPauseForm"
			:recurring-contribution="recurringContributionRecord"
			:submit-form="submitPauseRecurringDuration"
			:duration-options="durationOptions"
			:default-duration="durationOptions[0]"></recurring-pause-form>
		<recurring-pause-success v-else-if="flags.donationPauseSuccessful" :next-sched-contribution-date="nextSchedContributionDate"></recurring-pause-success>
		<recurring-pause-error v-else-if="flags.donationPauseError" :failure-message="$i18n( 'donorportal-pause-failure', helpEmail ).text()"></recurring-pause-error>
	</div>
</template>

<script>
const { defineComponent, ref, reactive } = require( 'vue' );
const { useRoute } = require( 'vue-router' );
const trackingParams = require( '../trackingParams.js' );
const RecurringContributionPauseForm = require( '../components/RecurringContributionPauseForm.vue' );
const RecurringContributionPauseSuccessful = require( '../components/RecurringContributionPauseSuccess.vue' );
const ErrorComponent = require( '../components/ErrorComponent.vue' );

module.exports = exports = defineComponent( {
	name: 'PauseDonationsView',
	components: {
		'recurring-pause-form': RecurringContributionPauseForm,
		'recurring-pause-success': RecurringContributionPauseSuccessful,
		'recurring-pause-error': ErrorComponent

	},
	setup() {
		const route = useRoute();
		const donorData = mw.config.get( 'donorData' );
		const helpEmail = mw.config.get( 'help_email' );
		const contribution_recur_id = route.params.id;

		let recurringContributionRecord = donorData
			.recurringContributions
			.filter( ( contribution ) => Number( contribution.id ) === Number( contribution_recur_id ) )[ 0 ];
		if ( !recurringContributionRecord ) {
			recurringContributionRecord = {};
		}

		const contact_id = donorData.contact_id;
		const checksum = donorData.checksum;
		const nextSchedContributionDate = ref( recurringContributionRecord.next_sched_contribution_date );
		const flags = reactive( {
			donationPauseSuccessful: false,
			donationPauseError: false,
			showDonationPauseForm: true
		} );

		function requestRecurringPause( params ) {
			const api = new mw.Api();
			params.action = 'requestPauseRecurring';

			return api.post( params );
		}

		function submitPauseRecurringDuration( duration ) {
			const durationInDays = `${ duration } Days`;
			const params = {
				duration: durationInDays,
				contact_id: Number( contact_id ),
				checksum: checksum,
				contribution_recur_id: Number( contribution_recur_id ),
				next_sched_contribution_date: nextSchedContributionDate.value
			};
			trackingParams.addTo( params );
			requestRecurringPause( params ).then( ( data ) => {
				// TODO: Set next scheduled date in global store
				nextSchedContributionDate.value = data.result.next_sched_contribution_date;
				flags.donationPauseSuccessful = true;
				flags.showDonationPauseForm = false;
			} ).catch( () => {
				// TODO: Add the error to logger
				flags.donationPauseError = true;
				flags.showDonationPauseForm = false;
			} );
		}

		return {
			helpEmail,
			recurringContributionRecord,
			nextSchedContributionDate,
			flags,
			submitPauseRecurringDuration
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
