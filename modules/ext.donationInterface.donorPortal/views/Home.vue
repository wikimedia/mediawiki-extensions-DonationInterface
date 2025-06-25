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
		return {
			address: {
				street_address: "1 Montgomery Street",
				city: "San Francisco",
				state_province: "California",
				postal_code: "90001",
				country: "US"
			},
			email: "jwales@example.org",
			name: "Jimmy Wales",
			donorID: "1",
			last_amount_formatted: '$100',
			last_currency: 'USD',
			last_payment_method: 'Credit Card: Visa',
			last_receive_date_formatted: 'June 2, 2025',
			recurringContributions: [
				{
					amount_frequency_key: 'donorportal-recurring-amount-monthly',
					amount_formatted: '$100',
					currency: 'USD',
					payment_method: 'Credit Card: Visa',
					next_sched_contribution_date_formatted: 'September 2, 2025',
					id: '123'
				}
			],
			inactiveRecurringContributions: [
				{
					amount_frequency_key: 'donorportal-recurring-amount-monthly',
					amount_formatted: '$100',
					currency: 'USD',
					payment_method: 'Credit Card: Visa',
					last_contribution_date_formatted: 'September 2, 2025',
					restart_key: 'donorportal-restart-monthly',
					hasLastContribution: true,
					id: '125'
				}
			],
			onetimeContribution: {
				last_amount_formatted: '$100',
				last_currency: 'USD',
				last_payment_method: 'Credit Card: Visa',
				last_receive_date_formatted: 'September 2, 2025',
				id: '123'
			},
			annualFundContributions: [
				{
					receive_date_formatted: "02 March, 2025",
					donation_type_key: "donorportal-donation-type-monthly",
					amount_formatted: "$5.78",
					currency: "USD",
					payment_method: "Credit Card: Visa",
					id: '123'
				},
				{
					receive_date_formatted: "03 March, 2025",
					donation_type_key: "donorportal-donation-type-annual",
					amount_formatted: "$6.78",
					currency: "USD",
					payment_method: "Credit Card: MasterCard",
					id: '124'
				}
			],
			endowmentContributions: [],
		}
	}

};
</script>
