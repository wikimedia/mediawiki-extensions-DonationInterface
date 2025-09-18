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
				href="https://donate.wikimedia.org/wiki/FAQ"
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
			<a href="#" class="cdx-button cdx-button--fake-button cdx-button--fake-button--enabled cdx-button--action-progressive cdx-button--size-large">
				{{ $i18n( "donorportal-aside-faq-giving-become-editor" ).text() }}
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
		<!-- Widget -->
		<section class="dp-card">
			<div class="dp-card__head">
				{{ $i18n( "donorportal-aside-article-of-the-day-heading" ).text() }}
			</div>
			<div class="dp-card__body dp-card__excerpt">
				<div class="text text--body">
					<img
						:src="`${ assets_path }/images/article-of-the-day-image.jpg`"
						alt="Image Alt Text">
					The goblin shark is a rare species of deep-sea shark. Sometimes called a "living fossil", it is the only extant representative of the family Mitsukurinidae, a lineage 125 million years old. This species looks unlike any other shark, with a long, flattened snout, highly protrusible jaws containing prominent nail-like teeth and pink coloration.
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
		const assets_path = mw.config.get( 'assets_path' );

		return {
			assets_path
		};
	},
	computed: {
		howWikipediaWorksAltText() {
			return this.$i18n( 'donorportal-aside-faq-how-wikipedia-works' ).text();
		}
	}
} );
</script>
