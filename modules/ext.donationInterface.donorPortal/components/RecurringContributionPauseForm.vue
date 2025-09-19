<template>
	<main id="pause-donations-form" class="container">
		<section class="column--base">
			<h1 class="heading heading--h1">
				{{ $i18n( "donorportal-pause-recurring-heading" ).text() }}
			</h1>
			<p class="text text--body">
				{{ $i18n( "donorportal-pause-recurring-subheading" ).text() }}
			</p>
			<p class="text text--body">
				{{ $i18n( "donorportal-pause-recurring-subtext" ).text() }}
			</p>
		</section>
		<recurring-contribution-summary
			:recurring-contribution="recurringContribution"
			extra-classes="is-lapsed"
		></recurring-contribution-summary>

		<section class="column--base">
			<p class="text text--body">
				{{ $i18n( "donorportal-pause-recurring-specify-duration" ).text() }}
			</p>
		</section>

		<section class="column--base donorportal-recurring-list">
			<form class="form">
				<fieldset class="cdx-field">
					<div class="cdx-field__control">
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
				</fieldset>
				<button
					id="continue"
					type="submit"
					name="submit"
					class="cdx-button cdx-button--weight-primary cdx-button--action-progressive cdx-button--size-large is-lapsed"
					@click="handleSubmitButtonClick"
				>
					{{ $i18n( "donorportal-pause-recurring-pause-button" ).text() }}
				</button>
				<router-link
					to="/"
					class="cdx-button cdx-button--fake-button cdx-button--fake-button--enabled cdx-button--weight-normal cdx-button--size-large"
				>
					{{ $i18n( "donorportal-pause-recurring-changed-my-mind-button" ).text() }}
				</router-link>
			</form>
		</section>
	</main>
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
