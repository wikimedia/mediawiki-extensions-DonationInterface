<template>
	<div id="donations-annual-conversion-form">
		<recurring-annual-conversion-form
			v-if="flags.showForm"
			:currency-rate-array="currencyRateArray"
			:recurring-contribution="recurringContribution"
			:submit-annual-conversion="submitAnnualConversion"
			:max="recurringUpgradeMaxUSD"
		></recurring-annual-conversion-form>
		<recurring-annual-conversion-success
			v-else-if="flags.donationAnnualConversionSuccessful"
			:next-yearly-sched-contribution-date-formatted="nextYearlySchedContributionDateFormatted"
		></recurring-annual-conversion-success>
		<recurring-annual-conversion-error
			v-else-if="flags.donationAnnualConversionError"
			:failure-message="$i18n( 'donorportal-cancel-failure', helpEmail ).text()"
		></recurring-annual-conversion-error>
	</div>
</template>

<script>
const { defineComponent, ref, reactive } = require( 'vue' );
const { useRoute } = require( 'vue-router' );
const trackingParams = require( '../trackingParams.js' );
const RecurringContributionAnnualConversionSuccessful = require( '../components/RecurringContributionAnnualConversionSuccess.vue' );
const RecurringAnnualConversionForm = require( '../components/RecurringContributionAnnualConversionForm.vue' );
const ErrorComponent = require( '../components/ErrorComponent.vue' );

module.exports = exports = defineComponent( {
	name: 'AnnualConversionView',
	components: {
		'recurring-annual-conversion-form': RecurringAnnualConversionForm,
		'recurring-annual-conversion-success': RecurringContributionAnnualConversionSuccessful,
		'recurring-annual-conversion-error': ErrorComponent
	},
	setup() {
		const route = useRoute();
		const donorData = mw.config.get( 'donorData' );
		const helpEmail = mw.config.get( 'help_email' );
		const recurringUpgradeMaxUSD = mw.config.get( 'recurringUpgradeMaxUSD' );
		const currencyRateArray = mw.config.get( 'wgDonationInterfaceCurrencyRates' );
		const contributionRecurId = route.params.id;
		let recurringContributionRecord = donorData
			.recurringContributions
			.filter( ( contribution ) => Number( contribution.id ) === Number( contributionRecurId ) )[ 0 ];
		if ( !recurringContributionRecord ) {
			recurringContributionRecord = {};
		}

		const flags = reactive( {
			showForm: recurringContributionRecord.can_modify,
			donationAnnualConversionSuccessful: false,
			donationAnnualConversionError: false
		} );

		function requestAnnualConversion( params ) {
			const api = new mw.Api();
			params.action = 'requestAnnualConversion';

			return api.post( params );
		}

		const submitAnnualConversion = ( amount ) => {
			const params = {
				amount,
				contact_id: Number( donorData.contact_id ),
				checksum: donorData.checksum,
				contribution_recur_id: Number( contributionRecurId ),
				next_sched_contribution_date: recurringContributionRecord.next_contribution_date_yearly
			};
			trackingParams.addTo( params );
			requestAnnualConversion( params ).then( () => {
				flags.showForm = false;
				flags.donationAnnualConversionSuccessful = true;
			} ).catch( () => {
				flags.donationAnnualConversionError = true;
				flags.showForm = false;
			} );
		};

		return {
			helpEmail,
			recurringContribution: recurringContributionRecord,
			nextYearlySchedContributionDateFormatted: recurringContributionRecord.next_contribution_date_yearly_formatted,
			flags,
			currencyRateArray,
			submitAnnualConversion,
			recurringUpgradeMaxUSD
		};
	}
} );
</script>
