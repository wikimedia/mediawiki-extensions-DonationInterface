<template>
	<div class="donorportal-home">
		<greeting :name="donorSummary.name"></greeting>
		<donor-contact-details
			:id="donorSummary.donorID"
			:name="donorSummary.name"
			:address="donorSummary.address"
			:email="donorSummary.email"></donor-contact-details>

		<div v-if="donorHasActiveRecurring" class="donorportal-recurring-list">
			<p>{{ $i18n( "donorportal-active-recurring" ).text() }}</p>
			<active-recurring-contribution
				v-for="contribution in donorSummary.recurringContributions || []"
				:key="contribution.id"
				:contribution="contribution"></active-recurring-contribution>
		</div>
		<div v-if="donorHasInactiveRecurring">
			<p>{{ $i18n( "donorportal-inactive-recurring" ).text() }}</p>
			<inactive-recurring-contribution
				v-for="contribution in donorSummary.inactiveRecurringContributions || []"
				:key="contribution.id"
				:contribution="contribution"></inactive-recurring-contribution>
		</div>
		<div v-if="showOneTimeContribution">
			<p>{{ $i18n( "donorportal-most-recent-donation" ).text() }}</p>
			<onetime-contribution :contribution="donorSummary.onetimeContribution"></onetime-contribution>
		</div>
		<donations-history
			:annual-fund-donations="annualFundContributions"
			:endowment-donations="endowmentContributions"></donations-history>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const GreetingComponent = require( '../components/GreetingComponent.vue' );
const DonorContactDetails = require( '../components/DonorContactDetails.vue' );
const ActiveRecurringContribution = require( '../components/ActiveRecurringContribution.vue' );
const InactiveRecurringContribution = require( '../components/InactiveRecurringContribution.vue' );
const OnetimeContribution = require( '../components/OnetimeContribution.vue' );
const DonationsHistory = require( '../components/DonationsHistory.vue' );

module.exports = exports = defineComponent( {
	name: 'HomeView',
	components: {
		'donor-contact-details': DonorContactDetails,
		greeting: GreetingComponent,
		'active-recurring-contribution': ActiveRecurringContribution,
		'inactive-recurring-contribution': InactiveRecurringContribution,
		'onetime-contribution': OnetimeContribution,
		'donations-history': DonationsHistory
	},
	data() {
		return {
			donorSummary: mw.config.get( 'donorData' )
		};
	},
	computed: {
		donorHasActiveRecurring: function () {
			return this.donorSummary && this.donorSummary.recurringContributions && this.donorSummary.recurringContributions.length > 0;
		},
		donorHasInactiveRecurring: function () {
			return this.donorSummary && this.donorSummary.inactiveRecurringContributions && this.donorSummary.inactiveRecurringContributions.length > 0;
		},
		showOneTimeContribution: function () {
			return this.donorSummary && this.donorSummary.onetimeContribution && !( this.donorHasActiveRecurring && this.donorHasInactiveRecurring );
		},
		annualFundContributions: function () {
			if ( this.donorSummary && this.donorSummary.annualFundContributions && this.donorSummary.annualFundContributions.length > 0 ) {
				return this.donorSummary.annualFundContributions;
			}
			return [];
		},
		endowmentContributions: function () {
			if ( this.donorSummary && this.donorSummary.endowmentContributions && this.donorSummary.endowmentContributions.length > 0 ) {
				return this.donorSummary.endowmentContributions;
			}
			return [];
		}
	}
} );
</script>
