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
		<recurring-update-error v-else-if="flags.donationUpdateError" :failure-message="updateErrorMessage"></recurring-update-error>
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
const { errorMessageMapFunction } = require( '../ErrorUtils.js' );

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
		const recurringUpgradeMaxUSD = mw.config.get( 'recurringUpgradeMaxUSD' );

		const contributionRecurId = route.params.id;

		let recurringContributionRecord = donorData
			.recurringContributions
			.filter( ( contribution ) => Number( contribution.id ) === Number( contributionRecurId ) )[ 0 ];
		if ( !recurringContributionRecord ) {
			recurringContributionRecord = {};
		}

		const newAmount = ref( recurringContributionRecord.currency + ' ' + recurringContributionRecord.amount );
		const errorCode = ref( '' );
		const currencyRateArray = mw.config.get( 'wgDonationInterfaceCurrencyRates' );
		const flags = reactive( {
			showForm: recurringContributionRecord.can_modify,
			donationUpdateSuccessful: false,
			donationUpdateError: false
		} );

		const submitUpdateRecurring = ( amount ) => {
			const params = {
				amount: amount,
				txn_type: amount > recurringContributionRecord.amount ? 'recurring_upgrade' : 'recurring_downgrade'
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
	},
	computed: {
		updateErrorMessage() {
			const errorMessageMap = errorMessageMapFunction( this.$i18n );
			if ( this.errorCode ) {
				if ( errorMessageMap[ this.errorCode ] ) {
					return errorMessageMap[ this.errorCode ];
				}
				return this.$i18n( 'donorportal-cancel-failure', mw.config.get( 'help_email' ) ).text();
			}
			return '';
		}
	}
} );
</script>
