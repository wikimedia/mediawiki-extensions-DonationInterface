<template>
	<div id="recurring-cancellation-form">
		<div>
			<h2>{{ $i18n( "donorportal-cancel-recurring-other-ways-heading" ).text() }}</h2>
			<p>{{ $i18n( "donorportal-cancel-recurring-other-ways-text" ).text() }}</p>
		</div>
		<div>
			<alternative-option-container
				id="pause-recurring-alt"
				:header="pauseRecurringHeading"
				:text="pauseRecurringText"
				:button-text="pauseRecurringButtonText"
				:action="handlePauseRecurringSubmitButtonClick"
			>
				<template #content>
					<div class="option-list">
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
				</template>
			</alternative-option-container>
			<alternative-option-container
				v-if="recurringContribution.frequency_unit === 'month'"
				:header="annualConversionHeading"
				:text="annualConversionText"
				:button-text="annualConversionButtonText"
				:action="annualConversionAction"
			></alternative-option-container>
			<alternative-option-container
				:header="amountChangeHeading"
				:text="amountChangeText"
				:button-text="amountChangeButtonText"
				:action="amountChangeAction"
			></alternative-option-container>
		</div>
		<div>
			<button
				id="continue"
				type="button"
				name="submit"
				class="confirm-cancel"
				@click="proceedCancelAction">
				{{ $i18n( "donorportal-cancel-recurring-confirm-cancellation" ).text() }}
			</button>
		</div>
		<div>
			<h4>{{ $i18n( "donorportal-cancel-recurring-quit-header" ).text() }}</h4>
			<router-link id="buttonBackToAccount" to="/">
				{{ $i18n( "donorportal-return-to-account-button" ).text() }}
			</router-link>
		</div>
	</div>
</template>

<script>
const { defineComponent, ref } = require( 'vue' );
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
			router.push( `/amount-downgrade/${ props.recurringContribution.id }` );
		};

		const annualConversionAction = () => {
			router.push( `/annual-conversion/${ props.recurringContribution.id }` );
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
