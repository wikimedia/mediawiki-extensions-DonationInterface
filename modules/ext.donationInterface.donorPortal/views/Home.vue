<template>
	<div class="donorportal-home">
		<greeting :name="donorSummary.name ?? ''"></greeting>
		<donor-contact-details :name="donorSummary.name ?? ''" :id="donorSummary.donorID ?? ''" :address="donorSummary.address ?? {}" :email="donorSummary.email ?? ''"></donor-contact-details>

		<div class="donorportal-recurring-list" v-if="donorSummary.recurringContributions && donorSummary.recurringContributions?.length > 0">
			<p>{{ $i18n("donorportal-active-recurring").text() }}</p>
			<active-recurring-contribution :contribution="contribution"
				v-for="contribution in donorSummary.recurringContributions || []" :key="contribution.id"></active-recurring-contribution>
		</div>
		<div v-if="donorSummary?.inactiveRecurringContributions && donorSummary?.inactiveRecurringContributions?.length > 0">
			<p>{{ $i18n("donorportal-inactive-recurring").text() }}</p>
			<inactive-recurring-contribution :contribution="contribution"
				v-for="contribution in donorSummary.inactiveRecurringContributions || []" :key="contribution.id"></inactive-recurring-contribution>
		</div>
		<div v-if="!(donorSummary.recurringContributions || donorSummary.inactiveRecurringContributions) || (donorSummary.recurringContributions?.length == 0 && donorSummary.inactiveRecurringContributions?.length == 0)">
			<p>{{ $i18n("donorportal-most-recent-donation").text() }}</p>
			<onetime-contribution :contribution="donorSummary.onetimeContribution ?? {}"></onetime-contribution>
		</div>
		<donations-history :annual_fund_donations="donorSummary.annualFundContributions ?? []"
			:endowment_donations="donorSummary.endowmentContributions ?? []"></donations-history>
	</div>
</template>

<script>
const GreetingComponent = require('../components/GreetingComponent.vue')
const DonorContactDetails = require('../components/DonorContactDetails.vue');
const ActiveRecurringContribution = require('../components/ActiveRecurringContribution.vue');
const InactiveRecurringContribution = require('../components/InactiveRecurringContribution.vue');
const OnetimeContribution = require('../components/OnetimeContribution.vue');
const DonationsHistory = require('../components/DonationsHistory.vue');

module.exports = exports = {
	name: 'HomeView',
	components: {
		'donor-contact-details': DonorContactDetails,
		'greeting': GreetingComponent,
		'active-recurring-contribution': ActiveRecurringContribution,
		'inactive-recurring-contribution': InactiveRecurringContribution,
		'onetime-contribution': OnetimeContribution,
		'donations-history': DonationsHistory
	},
	computed: {},
	data() {
		return {
			donorSummary: mw.config.get('donorData')
		}
	},
};
</script>
