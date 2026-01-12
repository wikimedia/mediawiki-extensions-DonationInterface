<template>
	<main id="recurring-cancellation-form" class="container">
		<section class="column--base">
			<h1 class="heading heading--h1">
				{{ $i18n( "donorportal-cancel-recurring-other-ways-heading" ).text() }}
			</h1>
			<p class="text text--body">
				{{ $i18n( "donorportal-cancel-recurring-other-ways-text" ).text() }}
			</p>
			<p class="text text--body">
				{{ $i18n( "donorportal-cancel-recurring-other-ways-text-line2" ).text() }} <strong>{{ $i18n(
					"donorportal-cancel-recurring-other-ways-text-line2-emphasis" ).text() }}</strong>
			</p>
		</section>
		<section class="column--full speedbump">
			<alternative-option-container
				id="pause-recurring-alt"
				:header="pauseRecurringHeading"
				:text="pauseRecurringText"
				:button-text="pauseRecurringButtonText"
				extra-classes="is-lapsed"
			>
				<template #content>
					<form class="form">
						<fieldset class="cdx-field">
							<div class="cdx-field__control">
								<radio-button-input
									v-for="option in durationOptions"
									:id="option.id"
									:key="option.id"
									v-model="pauseDuration"
									:label="option.locale"
									:value="option.value"
									name="pauseDuration"
								></radio-button-input>
							</div>
						</fieldset>
						<button
							id="submit-pause-action"
							type="submit"
							name="submit"
							class="cdx-button cdx-button--weight-quiet cdx-button--size-large"
							@click="handlePauseRecurringSubmitButtonClick"
						>
							{{ $i18n( "donorportal-pause-recurring-pause-button" ).text() }}
						</button>
					</form>
				</template>
			</alternative-option-container>
			<alternative-option-container
				v-if="recurringContribution.frequency_unit === 'month'"
				:header="annualConversionHeading"
				:text="annualConversionText"
				:button-text="annualConversionButtonText"
				:action="annualConversionAction"
				:is-clickable="true"
				extra-classes="is-recurring"
			></alternative-option-container>
			<alternative-option-container
				:header="amountChangeHeading"
				:text="amountChangeText"
				:button-text="amountChangeButtonText"
				:action="amountChangeAction"
				:is-clickable="true"
			></alternative-option-container>
		</section>
		<section class="column--full column--items-center">
			<button
				id="continue"
				type="button"
				name="submit"
				class="confirm-cancel cdx-button cdx-button--fake-button cdx-button--fake-button--enabled  cdx-button--weight-primary cdx-button--size-large"
				@click="proceedCancelAction">
				{{ $i18n( "donorportal-cancel-recurring-confirm-cancellation" ).text() }}
			</button>
		</section>

		<section class="column--full">
			<p class="text text--body text--align-center">
				{{ $i18n( "donorportal-cancel-recurring-quit-header" ).text() }}
				<router-link
					id="buttonBackToAccount"
					to="/"
					class="link">
					{{ $i18n( "donorportal-return-to-account-button" ).text() }}
				</router-link>
			</p>
		</section>
	</main>
</template>

<script>
const { defineComponent, ref, computed } = require( 'vue' );
const { useRouter, RouterLink } = require( 'vue-router' );

const RecurringContributionCancelOtherOptions = require( './RecurringContributionCancelAltOptionContainer.vue' );
const RadioButtonInput = require( './RadioButtonInput.vue' );

module.exports = exports = defineComponent( {
	name: 'RecurringContributionCancelForm',
	components: {
		'alternative-option-container': RecurringContributionCancelOtherOptions,
		'radio-button-input': RadioButtonInput,
		'router-link': RouterLink
	},
	props: {
		recurringContribution: {
			type: Object,
			required: true,
			defaultValue() {
				return {};
			}
		},
		submitPauseRecurringForm: {
			type: Function,
			required: true
		},
		durationOptions: {
			type: Array,
			required: true
		},
		defaultDuration: {
			type: Object,
			required: true
		},
		proceedCancelAction: {
			type: Function,
			required: true
		}
	},
	setup( props ) {
		const pauseDuration = ref( props.defaultDuration.value );
		const router = useRouter();
		const handlePauseRecurringSubmitButtonClick = ( $event ) => {
			$event.preventDefault();

			props.submitPauseRecurringForm( pauseDuration.value );
		};
		const amountChangeAction = () => {
			router.push( `/amount-downgrade/${ props.recurringContribution.id }/save` );
		};

		const annualConversionAction = () => {
			router.push( `/annual-conversion/${ props.recurringContribution.id }/save` );
		};
		return {
			pauseDuration,
			amountChangeAction,
			annualConversionAction,
			handlePauseRecurringSubmitButtonClick
		};
	},
	computed: {
		pauseRecurringHeading() {
			return this.$i18n( 'donorportal-cancel-recurring-pause-alternative-header' ).text();
		},
		pauseRecurringText() {
			return this.$i18n( 'donorportal-cancel-recurring-pause-alternative-text' ).text();
		},
		pauseRecurringButtonText() {
			return this.$i18n( 'donorportal-pause-recurring-pause-button' ).text();
		},
		annualConversionHeading() {
			return this.$i18n( 'donorportal-cancel-recurring-frequency-annual-switch-alternative-header' ).text();
		},
		annualConversionText() {
			return this.$i18n( 'donorportal-cancel-recurring-frequency-annual-switch-alternative-text' ).text();
		},
		annualConversionButtonText() {
			return this.$i18n( 'donorportal-cancel-recurring-frequency-annual-switch-alternative-button' ).text();
		},
		amountChangeHeading() {
			return this.$i18n( 'donorportal-cancel-recurring-amount-change-alternative-header' ).text();
		},
		amountChangeText() {
			return this.$i18n( 'donorportal-cancel-recurring-amount-change-alternative-text' ).text();
		},
		amountChangeButtonText() {
			return this.$i18n( 'donorportal-cancel-recurring-amount-change-alternative-button' ).text();
		}
	}
} );
</script>
