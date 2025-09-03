<template>
	<div id="recurring-cancellation-confirmation">
		<div>
			<h2>{{ $i18n( "donorportal-cancel-recurring-confirmation-request-header" ).text() }}</h2>
		</div>
		<div>
			<p>{{ $i18n( "donorportal-cancel-recurring-confirmation-request-text" ).text() }}</p>
			<recurring-contribution-summary
				:recurring-contribution="recurringContribution"
			></recurring-contribution-summary>
		</div>
		<div class="donorportal-recurring-list">
			<p>{{ $i18n( "donorportal-cancel-recurring-request-for-reason" ).text() }}</p>
			<radio-button-input
				v-for="option in reasons"
				:id="option.id"
				:key="option.id"
				v-model="reason"
				:label="option.locale"
				:value="option.value"
				name="reason"
			></radio-button-input>
			<div>
				<button
					id="continue"
					type="submit"
					name="submit"
					value="continue"
					:disabled="reason === ''"
					@click="handleCancelRecurringSubmitButtonClick">
					{{ $i18n( "donorportal-cancel-recurring-cancel-button" ).text() }}
				</button>
				<router-link to="/">
					{{ $i18n( "donorportal-cancel-recurring-changed-my-mind" ).text() }}
				</router-link>
				<router-link
					v-if="recurringContribution.frequency_unit === 'month'"
					:to="`/annual-conversion/${recurringContribution.id}`"
				>
					{{ $i18n( "donorportal-cancel-recurring-switch-to-annual" ).text() }}
				</router-link>
			</div>
		</div>
	</div>
</template>

<script>
const { defineComponent, ref } = require( 'vue' );
const { RouterLink } = require( 'vue-router' );

const RecurringContributionSummary = require( './RecurringContributionSummary.vue' );
const RadioButtonInput = require( './RadioButtonInput.vue' );

module.exports = exports = defineComponent( {
	name: 'RecurringContributionCancelConfirmation',
	components: {
		'router-link': RouterLink,
		'recurring-contribution-summary': RecurringContributionSummary,
		'radio-button-input': RadioButtonInput
	},
	props: {
		recurringContribution: {
			type: Object,
			required: true,
			defaultValue() {
				return {};
			}
		},
		submitCancelRecurringForm: {
			type: Function,
			required: true
		}
	},
	setup( props ) {
		const reason = ref( '' );
		const handleCancelRecurringSubmitButtonClick = ( $event ) => {
			$event.preventDefault();
			props.submitCancelRecurringForm( reason.value );
		};

		return {
			reason,
			handleCancelRecurringSubmitButtonClick
		};
	},
	computed: {
		reasons() {
			return [
				{
					id: 'financial-reason',
					value: 'Financial reason',
					locale: `${ this.$i18n( 'donorportal-cancel-recurring-reason-financial' ).text() }`
				},
				{
					id: 'donation-frequency',
					value: 'Donation frequency',
					locale: `${ this.$i18n( 'donorportal-cancel-recurring-reason-donation-frequency' ).text() }`
				},
				{
					id: 'giving-method',
					value: 'Giving method',
					locale: `${ this.$i18n( 'donorportal-cancel-recurring-reason-prefer-other-methods' ).text() }`
				},
				{
					id: 'cancel-support',
					value: 'Cancel support',
					locale: `${ this.$i18n( 'donorportal-cancel-recurring-reason-cancel-support' ).text() }`
				},
				{
					id: 'supporting-other-organizations',
					value: 'Other organizations',
					locale: `${ this.$i18n( 'donorportal-cancel-recurring-reason-supporting-others' ).text() }`
				},
				{
					id: 'other',
					value: 'Other',
					locale: `${ this.$i18n( 'donorportal-cancel-recurring-reason-other' ).text() }`
				}
			];
		}
	}
} );
</script>
