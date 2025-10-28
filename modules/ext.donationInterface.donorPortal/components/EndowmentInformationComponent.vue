<template>
	<div class="dp-blank-message">
		<p class="text text--body">
			{{ $i18n( "donorportal-endowment-short" ).text() }}
		</p>
		<div class="endowment-img-container">
			<img
				:src="`${ assets_path }/images/icon-earth.png`"
				alt="Icon"
				class="image--icon-large endowment-img"
				width="64"
			>
			<h3 class="heading heading--h3">
				{{ $i18n( "donorportal-endowment-what-is" ).text() }}
			</h3>
			<p class="text text--body">
				{{ $i18n( "donorportal-endowment-explanation" ).text() }}
			</p>
			<p class="text text--body">
				<a
					:href="endowmentLearnMoreUrl"
					target="_blank"
					class="link"
				>
					{{ $i18n( "donorportal-endowment-learn-more" ).text() }}
				</a>  |  <a
					:href="endowmentDonationUrl"
					target="_blank"
					class="link">{{ $i18n( "donorportal-endowment-donate-now" ).text() }}</a>
			</p>
		</div>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
module.exports = exports = defineComponent( {
	name: 'EndowmentInformationComponent',
	setup() {
		return {
			assets_path: mw.config.get( 'assets_path' ),
			endowmentLearnMoreUrl: mw.config.get( 'endowmentLearnMoreUrl' ),
			endowmentDonationUrl: mw.config.get( 'endowmentDonationUrl' )
		};
	}
} );
</script>
