<template>
	<!-- Related Content -->
	<aside class="container__inner dp-dashboard__aside">
		<!-- Widget -->
		<section class="dp-card">
			<div class="dp-card__head">
				{{ $i18n( "donorportal-aside-did-you-know" ).text() }}
			</div>
			<div class="dp-card__body dp-card__excerpt">
				<p class="text text--body">
					{{ $i18n( "donorportal-aside-fun-fact" ).text() }}
				</p>
			</div>
		</section>
		<!-- End of Widget -->
		<!-- Button Stack -->
		<section class="dp-button-stack">
			<a
				:href="donorFaqUrl"
				target="_blank"
				class="cdx-button cdx-button--fake-button cdx-button--fake-button--enabled cdx-button--size-large">
				{{ $i18n( "donorportal-aside-faq" ).text() }}
			</a>
			<a
				href="https://donate.wikimedia.org/wiki/Ways_to_Give"
				target="_blank"
				class="cdx-button cdx-button--fake-button cdx-button--fake-button--enabled cdx-button--size-large">
				{{ $i18n( "donorportal-aside-faq-giving-other-ways" ).text() }}
			</a>
			<a
				href="https://donate.wikimedia.org/wiki/Legacy"
				target="_blank"
				class="cdx-button cdx-button--fake-button cdx-button--fake-button--enabled cdx-button--size-large">
				{{ $i18n( "donorportal-aside-faq-giving-legacy" ).text() }}
			</a>
			<a
				href="https://donate.wikimedia.org/?wmf_source=donorportal&wmf_medium=donorportal&wmf_campaign=donorportal&uselang=en"
				target="_blank"
				class="cdx-button cdx-button--fake-button cdx-button--fake-button--enabled cdx-button--size-large">
				{{ $i18n( "donorportal-aside-faq-giving-new-donation" ).text() }}
			</a>
		</section>
		<!-- End of Button Stack -->
		<!-- Widget -->
		<section class="dp-card">
			<div class="dp-card__head">
				{{ $i18n( "donorportal-aside-faq-how-wikipedia-works" ).text() }}
			</div>
			<div class="dp-card__body dp-card__body--compact">
				<div class="dp-card__player">
					<img
						:src="`${ assets_path }/images/how-wikipedia-works.jpg`"
						:alt="howWikipediaWorksAltText">
				</div>
			</div>
		</section>
		<!-- End of Widget -->
	</aside>
	<!-- End of Related Content -->
</template>

<script>
const { defineComponent } = require( 'vue' );

module.exports = exports = defineComponent( {
	name: 'RelatedContentComponent',

	setup() {
		return {
			assets_path: mw.config.get( 'assets_path' ),
			donorFaqUrl: mw.config.get( 'donorFaqUrl' )
		};
	},
	computed: {
		howWikipediaWorksAltText() {
			return this.$i18n( 'donorportal-aside-faq-how-wikipedia-works' ).text();
		}
	}
} );
</script>
