<template>
	<feedback-survey></feedback-survey>
	<main class="container dp-dashboard">
		<greeting :name="donorSummary.name"></greeting>
		<section class="container__inner dp-dashboard__main">
			<donor-contact-details
				:id="donorSummary.donorID"
				:name="donorSummary.name"
				:address="donorSummary.address"
				:email="donorSummary.email"
				:email-preferences-url="emailPreferencesUrl"></donor-contact-details>
			<donor-card
				:active-recurring-contributions="donorSummary.recurringContributions"
				:inactive-recurring-contributions="donorSummary.inactiveRecurringContributions"
				:onetime-contribution="donorSummary.onetimeContribution"
			></donor-card>
			<donations-history
				:annual-fund-donations="annualFundContributions"
				:endowment-donations="endowmentContributions"></donations-history>
			<donations-disclaimer
				:email="donorSummary.email"
				:help-email="helpEmail"></donations-disclaimer>
		</section>
		<related-content></related-content>
	</main>
</template>

<script>
const { defineComponent } = require( 'vue' );
const GreetingComponent = require( '../components/GreetingComponent.vue' );
const FeedbackSurveyComponent = require( '../components/FeedbackSurveyComponent.vue' );
const DonorContactDetails = require( '../components/DonorContactDetails.vue' );
const DonationsHistory = require( '../components/DonationsHistory.vue' );
const DonorCardComponent = require( '../components/DonorCardComponent.vue' );
const DonationsDisclaimerComponent = require( '../components/DonationsDisclaimerComponent.vue' );
const RelatedContentComponent = require( '../components/RelatedContentComponent.vue' );

module.exports = exports = defineComponent( {
	name: 'HomeView',
	components: {
		'donor-contact-details': DonorContactDetails,
		greeting: GreetingComponent,
		'feedback-survey': FeedbackSurveyComponent,
		'donations-history': DonationsHistory,
		'donor-card': DonorCardComponent,
		'donations-disclaimer': DonationsDisclaimerComponent,
		'related-content': RelatedContentComponent
	},
	data() {
		return {
			donorSummary: mw.config.get( 'donorData' ),
			helpEmail: mw.config.get( 'help_email' ),
			emailPreferencesUrl: mw.config.get( 'emailPreferencesUrl' )
		};
	},
	computed: {
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
