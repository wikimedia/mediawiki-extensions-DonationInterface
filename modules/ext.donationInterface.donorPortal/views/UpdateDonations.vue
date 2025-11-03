<template>
	<div id="update-donations-form">
		<recurring-update-form
			v-if="flags.showForm"
			:recurring-contribution="recurringContribution"
			:max="recurringUpgradeMaxUSD"
			:currency-rate-array="currencyRateArray"
			:submit-update-recurring="submitUpdateRecurring"></recurring-update-form>
		<recurring-update-success
			v-else-if="flags.donationUpdateSuccessful"
			:next-sched-contribution-date="nextSchedContributionDate"
			:new-amount="newAmount"
		></recurring-update-success>
		<recurring-update-error v-else-if="flags.donationUpdateError" :failure-message="$i18n( 'donorportal-cancel-failure', helpEmail ).text()"></recurring-update-error>
	</div>
</template>

<script>
const { defineComponent, ref, reactive } = require( 'vue' );
const { useRoute } = require( 'vue-router' );
const trackingParams = require( '../trackingParams.js' );
const RecurringContributionUpdateForm = require( '../components/RecurringContributionUpdateForm.vue' );
const RecurringContributionUpdateSuccessful = require( '../components/RecurringContributionUpdateSuccess.vue' );
const ErrorComponent = require( '../components/ErrorComponent.vue' );

module.exports = exports = defineComponent( {
	name: 'CancelDonationsView',
	components: {
		'recurring-update-form': RecurringContributionUpdateForm,
		'recurring-update-success': RecurringContributionUpdateSuccessful,
		'recurring-update-error': ErrorComponent
	},
	setup() {
		const route = useRoute();
		const donorData = mw.config.get( 'donorData' );
		const helpEmail = mw.config.get( 'help_email' );
		const recurringUpgradeMaxUSD = mw.config.get( 'recurringUpgradeMaxUSD' );

		const contributionRecurId = route.params.id;

		let recurringContributionRecord = donorData
			.recurringContributions
			.filter( ( contribution ) => Number( contribution.id ) === Number( contributionRecurId ) )[ 0 ];
		if ( !recurringContributionRecord ) {
			recurringContributionRecord = {};
		}

		const recurringContribution = ref( recurringContributionRecord );
		const nextSchedContributionDate = ref( recurringContributionRecord.next_sched_contribution_date_formatted );

		const newAmount = ref( recurringContributionRecord.currency + ' ' + recurringContributionRecord.amount );
		const currencyRateArray = mw.config.get( 'wgDonationInterfaceCurrencyRates' );
		const flags = reactive( {
			showForm: recurringContributionRecord.can_modify,
			donationUpdateSuccessful: false,
			donationUpdateError: false
		} );

		function requestRecurringUpdate( params ) {
			const api = new mw.Api();
			params.action = 'requestUpdateRecurring';

			return api.post( params );
		}

		const submitUpdateRecurring = ( amount ) => {
			const params = {
				amount: amount,
				contact_id: Number( donorData.contact_id ),
				checksum: donorData.checksum,
				contribution_recur_id: Number( contributionRecurId ),
				txn_type: amount > recurringContributionRecord.amount ? 'recurring_upgrade' : 'recurring_downgrade'
			};
			trackingParams.addTo( params );
			newAmount.value = recurringContributionRecord.currency_symbol + amount + ' ' +  recurringContributionRecord.currency;
			requestRecurringUpdate( params ).then( () => {
				flags.showForm = false;
				flags.donationUpdateSuccessful = true;
			} ).catch( () => {
				flags.donationUpdateError = true;
				flags.showForm = false;
			} );
		};

		return {
			recurringContribution,
			flags,
			helpEmail,
			newAmount,
			nextSchedContributionDate,
			submitUpdateRecurring,
			currencyRateArray,
			recurringUpgradeMaxUSD
		};
	}
} );
</script>
