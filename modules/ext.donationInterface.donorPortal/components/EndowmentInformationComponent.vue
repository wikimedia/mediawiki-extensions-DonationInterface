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
					href="https://wikimediaendowment.org/"
					target="_blank"
					class="link"
				>
					{{ $i18n( "donorportal-endowment-learn-more" ).text() }}
				</a>  |  <a
					href="https://wikimediaendowment.org/"
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
		const assets_path = mw.config.get( 'assets_path' );

		return {
			assets_path
		};
	}
} );
</script>
