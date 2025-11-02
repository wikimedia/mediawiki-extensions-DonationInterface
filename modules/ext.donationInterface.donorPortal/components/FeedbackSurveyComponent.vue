<template>
	<section class="ribbon">
		<div class="ribbon__inner">
			<div class="cdx-message cdx-message--block cdx-message--notice" aria-live="polite">
				<span class="cdx-message__icon"></span>
				<div class="cdx-message__content">
					{{ $i18n( "donorportal-feedbackrequest" ).text() }}
					<a
						:href="surveyUrl"
						target="_blank"
						class="link">
						{{ $i18n( "donorportal-feedbacklink" ).text() }}
					</a>
				</div>
			</div>
		</div>
	</section>
</template>

<script>
const { defineComponent } = require( 'vue' );
module.exports = exports = defineComponent( {
	name: 'FeedbackSurveyComponent',
	data() {
		return {
			surveyUrl: mw.config.get( 'surveyUrl' )
		};
	}
} );
</script>
