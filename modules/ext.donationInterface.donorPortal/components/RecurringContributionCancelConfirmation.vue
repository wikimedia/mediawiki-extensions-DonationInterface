<template>
	<main id="recurring-cancellation-confirmation" class="container">
		<section class="column--base">
			<h1 class="heading heading--h1">
				{{ $i18n( "donorportal-cancel-recurring-confirmation-request-header" ).text() }}
			</h1>
			<p class="text text--body">
				{{ $i18n( "donorportal-cancel-recurring-confirmation-request-text" ).text() }}
			</p>
		</section>
		<recurring-contribution-summary
			:recurring-contribution="recurringContribution"
			extra-classes="is-cancel"
		></recurring-contribution-summary>
		<section class="column--base">
			<p class="text text--body">
				{{ $i18n( "donorportal-cancel-recurring-request-for-reason" ).text() }}
			</p>
		</section>
		<section class="column--base">
			<form
				class="form"
			>
				<fieldset class="cdx-field">
					<radio-button-input
						v-for="option in reasons"
						:id="option.id"
						:key="option.id"
						v-model="reason"
						:label="option.locale"
						:value="option.value"
						name="reason"
					></radio-button-input>
				</fieldset>
				<button
					id="continue"
					type="submit"
					name="submit"
					value="continue"
					:disabled="reason === ''"
					class="cdx-button cdx-button--weight-primary cdx-button--action-progressive cdx-button--size-large is-cancel"
					@click="handleCancelRecurringSubmitButtonClick"
				>
					{{ $i18n( "donorportal-cancel-recurring-cancel-button" ).text() }}
				</button>
				<router-link to="/" class="cdx-button cdx-button--fake-button cdx-button--fake-button--enabled cdx-button--weight-normal cdx-button--size-large">
					{{ $i18n( "donorportal-cancel-recurring-changed-my-mind" ).text() }}
				</router-link>
				<router-link
					v-if="recurringContribution.frequency_unit === 'month' && recurringContribution.can_modify"
					class="cdx-button cdx-button--fake-button cdx-button--fake-button--enabled cdx-button--weight-primary cdx-button--size-large"
					:to="`/annual-conversion/${recurringContribution.id}`"
				>
					{{ $i18n( "donorportal-cancel-recurring-switch-to-annual" ).text() }}
				</router-link>
			</form>
		</section>
	</main>
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
					value: 'Financial Reasons',
					locale: `${ this.$i18n( 'donorportal-cancel-recurring-reason-financial' ).text() }`
				},
				{
					id: 'donation-frequency',
					value: 'Frequency',
					locale: `${ this.$i18n( 'donorportal-cancel-recurring-reason-donation-frequency' ).text() }`
				},
				{
					id: 'giving-method',
					value: 'Update',
					locale: `${ this.$i18n( 'donorportal-cancel-recurring-reason-prefer-other-methods' ).text() }`
				},
				{
					id: 'cancel-support',
					value: 'Cancel Support',
					locale: `${ this.$i18n( 'donorportal-cancel-recurring-reason-cancel-support' ).text() }`
				},
				{
					id: 'unintended',
					value: 'Unintended Recurring Donation',
					locale: `${ this.$i18n( 'donorportal-cancel-recurring-reason-unintended' ).text() }`
				},
				{
					id: 'other',
					value: 'Other and Unspecified',
					locale: `${ this.$i18n( 'donorportal-cancel-recurring-reason-other' ).text() }`
				}
			];
		}
	}
} );
</script>
