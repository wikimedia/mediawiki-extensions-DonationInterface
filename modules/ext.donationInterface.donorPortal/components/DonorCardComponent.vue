<template>
	<section v-if="onetimeContribution || showRecurringContributions" class="dp-card">
		<template v-if="showRecurringContributions">
			<div class="dp-card__head">
				{{ $i18n( "donorportal-recurring-heading" ).text() }}
			</div>
			<div class="dp-donor-card dp-card__body dp-card__body--compact">
				<!-- Inactive contributions -->
				<recurring-contribution
					v-for="contribution in inactiveRecurringContributions"
					:key="contribution.id"
					:contribution="contribution"
					:is-active="false"
				></recurring-contribution>
				<!-- Active contributions -->
				<recurring-contribution
					v-for="contribution in activeRecurringContributions"
					:key="contribution.id"
					:contribution="contribution"
					:is-active="true"></recurring-contribution>
			</div>
		</template>
		<template v-else-if="onetimeContribution">
			<div class="dp-card__head">
				{{ $i18n( "donorportal-most-recent-donation" ).text() }}
			</div>
			<div class="dp-donor-card dp-card__body dp-card__body--compact">
				<onetime-contribution :contribution="onetimeContribution"></onetime-contribution>
			</div>
		</template>
	</section>
</template>

<script>
const RecurringContributionComponent = require( './RecurringContributionComponent.vue' );
const OnetimeContribution = require( './OnetimeContribution.vue' );

const { defineComponent } = require( 'vue' );
module.exports = exports = defineComponent( {
	name: 'DonorCardComponent',
	components: {
		'recurring-contribution': RecurringContributionComponent,
		'onetime-contribution': OnetimeContribution
	},
	props: {
		activeRecurringContributions: {
			type: Array,
            default() {
				return [];
			}
		},
		inactiveRecurringContributions: {
			type: Array,
            default() {
				return [];
			}
		},
		onetimeContribution: {
			type: Object,
            default() {
				return null;
			}
		}
	},
	computed: {
		showRecurringContributions: function () {
			return this.activeRecurringContributions.length > 0 || this.inactiveRecurringContributions.length > 0;
		}
	}
} );
</script>
