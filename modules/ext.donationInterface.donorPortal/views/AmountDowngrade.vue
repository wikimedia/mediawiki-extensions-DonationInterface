<template>
	<div id="update-donations-form">
		<recurring-update-form
			v-if="flags.showForm"
			:recurring-contribution="recurringContribution"
			:max="recurringUpgradeMaxUSD"
			for-downgrade-form
			:currency-rate-array="currencyRateArray"
			:submit-update-recurring="submitUpdateRecurring"></recurring-update-form>
		<recurring-update-success
			v-else-if="flags.donationUpdateSuccessful"
			:next-sched-contribution-date="nextSchedContributionDate"
			:new-amount="newAmount"
		></recurring-update-success>
		<recurring-update-error v-else-if="flags.donationUpdateError" :error-code="errorCode"></recurring-update-error>
	</div>
</template>

<script>
const { defineComponent, ref, reactive } = require( 'vue' );
const { useRoute } = require( 'vue-router' );
const trackingParams = require( '../trackingParams.js' );
const RecurringContributionUpdateForm = require( '../components/RecurringContributionUpdateForm.vue' );
const RecurringContributionUpdateSuccessful = require( '../components/RecurringContributionUpdateSuccess.vue' );
const ErrorComponent = require( '../components/ErrorComponent.vue' );
const { requestRecurringUpdate } = require( '../ApiUtils.js' );

module.exports = exports = defineComponent( {
	name: 'AmountDowngradeView',
	components: {
		'recurring-update-form': RecurringContributionUpdateForm,
		'recurring-update-success': RecurringContributionUpdateSuccessful,
		'recurring-update-error': ErrorComponent
	},
	setup() {
		const route = useRoute();

		const donorData = mw.config.get( 'donorData' );
		const recurringUpgradeMaxUSD = mw.config.get( 'recurringUpgradeMaxUSD' );
		const contributionRecurId = route.params.id;
		const isSave = ( route.name === 'AmountDowngradeSave' );
		let recurringContributionRecord = donorData
			.recurringContributions
			.filter( ( contribution ) => Number( contribution.id ) === Number( contributionRecurId ) )[ 0 ];
		if ( !recurringContributionRecord ) {
			recurringContributionRecord = {};
		}

		const newAmount = ref( recurringContributionRecord.currency + ' ' + recurringContributionRecord.amount );
		const currencyRateArray = mw.config.get( 'wgDonationInterfaceCurrencyRates' );
		const errorCode = ref( '' );
		const flags = reactive( {
			showForm: recurringContributionRecord.can_modify,
			donationUpdateSuccessful: false,
			donationUpdateError: false
		} );

		const submitUpdateRecurring = ( amount ) => {
			const params = {
				amount: amount,
				txn_type: 'recurring_downgrade',
				is_from_save_flow: isSave
			};
			trackingParams.addTo( params );
			newAmount.value = recurringContributionRecord.currency_symbol + amount + ' ' +  recurringContributionRecord.currency;
			requestRecurringUpdate( recurringContributionRecord, params ).then( () => {
				flags.showForm = false;
				flags.donationUpdateSuccessful = true;
			} ).catch( ( code ) => {
				errorCode.value = code || 'unknown';
				flags.donationUpdateError = true;
				flags.showForm = false;
			} );
		};

		return {
			recurringContribution: recurringContributionRecord,
			errorCode,
			flags,
			newAmount,
			nextSchedContributionDate: recurringContributionRecord.next_sched_contribution_date_formatted,
			submitUpdateRecurring,
			currencyRateArray,
			recurringUpgradeMaxUSD
		};
	}
} );
</script>
