<template>
	<div id="manage-donations">
		<main class="container">
			<section class="column--base">
				<h1 class="heading heading--h1">
					{{ $i18n( "donorportal-manage-donation-heading" ).text() }}
				</h1>
				<p class="text text--body">
					{{ $i18n( "donorportal-manage-donation-text" ).text() }}
				</p>
			</section>

			<section class="column--base">
				<section class="box is-recurring">
					<div class="box__inner gap--base">
						<div class="box__columns">
							<div class="box__column">
								<div class="box__section">
									<span class="tag is-recurring">{{ $i18n( "donorportal-recurring-status-active" ).text() }}</span>
								</div>
								<div class="box__section">
									<h2 class="heading heading--h2">
										{{ $i18n( "donorportal-manage-donation-donor-card-heading" ).text() }}
									</h2>
									<p class="text text--body">
										{{ $i18n( "donorportal-manage-donation-donor-card-text" ).text() }}
									</p>
								</div>
							</div>
							<div class="box__column">
								<img
									src="https://upload.wikimedia.org/wikipedia/donate/6/6e/Wikipedia-globe-90x80.png"
									alt="Image of Wikipedia Globe"
									class="image--callout">
							</div>
						</div>
						<div class="box__section">
							<p class="text text--body">
								<strong>{{ contributionAmount }}</strong><br>
								{{ recurringContributionRecord.payment_method }}<br>
								{{ recurringNextContributionAmountWithDate }}
							</p>
						</div>
					</div>
				</section>
			</section>

			<section class="column--base">
				<h2 class="heading heading--h2">
					{{ $i18n( "donorportal-manage-donation-management-heading" ).text() }}
				</h2>
			</section>

			<section class="column--base">
				<router-link
					id="buttonPauseGift"
					:to="`/pause-donations/${contribution_recur_id}`"
					class="cdx-button cdx-button--fake-button cdx-button--fake-button--enabled cdx-button--weight-normal cdx-button--size-large">
					{{ $i18n( "donorportal-manage-donation-management-pause-gift" ).text() }}
				</router-link>
				<router-link
					id="buttonChangeDonationAmount"
					:to="`/update-donations/${contribution_recur_id}`"
					class="cdx-button cdx-button--fake-button cdx-button--fake-button--enabled cdx-button--weight-primary cdx-button--size-large">
					{{ $i18n( "donorportal-manage-donation-management-change-amount" ).text() }}
				</router-link>
			</section>

			<section class="column--base">
				<p v-if="isMonthlyGift" class="text text--body">
					<router-link
						id="buttonSwitchAnnualGift"
						:to="`/annual-conversion/${contribution_recur_id}`"
						class="link">
						{{ $i18n( "donorportal-cancel-recurring-frequency-annual-switch-alternative-button" ).text() }}
					</router-link>
				</p>
				<p class="text text--body">
					<router-link
						id="buttonCancelRecurringGift"
						:to="`/cancel-donations/${contribution_recur_id}`"
						class="link">
						{{ $i18n( "donorportal-manage-donation-management-cancel-gift" ).text() }}
					</router-link>
				</p>
			</section>

			<section class="column--base">
				<p class="text text--body">
					{{ $i18n( "donorportal-cancel-recurring-quit-header" ).text() }}
					<router-link
						id="buttonBack"
						to="/"
						class="link">
						{{ $i18n( "donorportal-return-to-account-button" ).text() }}
					</router-link>
				</p>
			</section>
		</main>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { useRoute } = require( 'vue-router' );
const { RouterLink } = require( 'vue-router' );

module.exports = exports = defineComponent( {
	name: 'ManageDonationsView',
	components: {
		'router-link': RouterLink
	},
	setup() {
		const route = useRoute();
		const donorData = mw.config.get( 'donorData' );
		const contribution_recur_id = route.params.id;

		let recurringContributionRecord = donorData
			.recurringContributions
			.filter( ( contribution ) => Number( contribution.id ) === Number( contribution_recur_id ) )[ 0 ];
		if ( !recurringContributionRecord ) {
			recurringContributionRecord = {};
		}

		return {
			contribution_recur_id,
			recurringContributionRecord
		};
	},
	computed: {
		isMonthlyGift: function () {
			return this.recurringContributionRecord && this.recurringContributionRecord.frequency_unit === 'month';
		},
		contributionAmount: function () {
			// Frequency keys that can be used here
			// * donorportal-recurring-amount-annual
			// * donorportal-recurring-amount-monthly
			return this.$i18n( this.recurringContributionRecord.amount_frequency_key, this.recurringContributionRecord.amount_formatted, this.recurringContributionRecord.currency ).text();
		},
		recurringNextContributionAmountWithDate: function () {
			return this.$i18n( 'donorportal-recurring-next-amount-and-date', this.recurringContributionRecord.amount_formatted,
				this.recurringContributionRecord.currency, this.recurringContributionRecord.next_sched_contribution_date_formatted ).text();
		}
	}
} );
</script>
