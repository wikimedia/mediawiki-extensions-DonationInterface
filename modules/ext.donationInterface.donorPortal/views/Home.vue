<template>
	<div class="donorportal-home">
		<greeting :name="name"></greeting>
		<donor-contact-details :name="name" :id="donorID" :address="address" :email="email"></donor-contact-details>

		<div class="donorportal-recurring-list" v-if="recurringContributions.length > 0">
			<p>{{ $i18n("donorportal-active-recurring").text() }}</p>
			<active-recurring-contribution :contribution="contribution"
				v-for="contribution in recurringContributions" :key="contribution.id"></active-recurring-contribution>
		</div>
		<div v-if="inactiveRecurringContributions.length > 0">
			<p>{{ $i18n("donorportal-inactive-recurring").text() }}</p>
			<inactive-recurring-contribution :contribution="contribution"
				v-for="contribution in inactiveRecurringContributions" :key="contribution.id"></inactive-recurring-contribution>
		</div>
		<div v-if="recurringContributions.length == 0 && inactiveRecurringContributions.length == 0">
			<p>{{ $i18n("donorportal-most-recent-donation").text() }}</p>
			<onetime-contribution :contribution="onetimeContribution"></onetime-contribution>
		</div>
		<donations-history :annual_fund_donations="annualFundContributions"
			:endowment_donations="endowmentContributions"></donations-history>
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
		return mw.config.get('donorData');
	}

};
</script>
