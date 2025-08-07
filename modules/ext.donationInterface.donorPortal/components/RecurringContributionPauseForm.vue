<template>
	<div id="pause-donations-form">
		<div class="greeting">
			<h2>{{ $i18n( "donorportal-pause-recurring-heading" ).text() }}</h2>
			<h4>{{ $i18n( "donorportal-pause-recurring-subheading" ).text() }}</h4>
		</div>
		<div>
			<p>{{ $i18n( "donorportal-pause-recurring-subtext" ).text() }}</p>
			<recurring-contribution-summary
				:recurring-contribution="recurringContribution"
			></recurring-contribution-summary>
		</div>

		<div class="donorportal-recurring-list">
			<p>{{ $i18n( "donorportal-pause-recurring-specify-duration" ).text() }}</p>
			<form>
				<div class="emailPreferencesRightColumn">
					<radio-button-input
						v-for="option in durationOptions"
						:id="option.id"
						:key="option.id"
						v-model="duration"
						:label="option.locale"
						:value="option.value"
						name="duration"
					></radio-button-input>
				</div>
				<div>
					<button
						id="continue"
						type="submit"
						name="submit"
						@click="handleSubmitButtonClick">
						{{ $i18n( "donorportal-pause-recurring-pause-button" ).text() }}
					</button>
					<router-link to="/">
						{{ $i18n( "donorportal-pause-recurring-changed-my-mind-button" ).text() }}
					</router-link>
				</div>
			</form>
		</div>
	</div>
</template>

<script>
const { defineComponent, ref } = require( 'vue' );
const { RouterLink } = require( 'vue-router' );
const RadioButtonInput = require( './RadioButtonInput.vue' );
const RecurringContributionSummary = require( './RecurringContributionSummary.vue' );

module.exports = exports = defineComponent( {
	name: 'RecurringContributionPauseForm',
	components: {
		'radio-button-input': RadioButtonInput,
		'recurring-contribution-summary': RecurringContributionSummary,
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
		submitForm: {
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
		}
	},
	setup( props ) {
		const duration = ref( props.defaultDuration.value );

		const handleSubmitButtonClick = ( $event ) => {
			$event.preventDefault();

			props.submitForm( duration.value );
		};

		return {
			duration,
			handleSubmitButtonClick
		};
	}
} );
</script>
