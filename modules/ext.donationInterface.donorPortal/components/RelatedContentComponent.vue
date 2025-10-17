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
				:href="otherWaysUrl"
				target="_blank"
				class="cdx-button cdx-button--fake-button cdx-button--fake-button--enabled cdx-button--size-large">
				{{ $i18n( "donorportal-aside-faq-giving-other-ways" ).text() }}
			</a>
			<a
				:href="legacyUrl"
				target="_blank"
				class="cdx-button cdx-button--fake-button cdx-button--fake-button--enabled cdx-button--size-large">
				{{ $i18n( "donorportal-aside-faq-giving-legacy" ).text() }}
			</a>
			<a
				:href="newDonationUrl"
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
						:alt="howWikipediaWorksAltText"
						@click="launchWikipediaVideo">
				</div>
			</div>
		</section>
		<!-- End of Widget -->
	</aside>
	<!-- End of Related Content -->
	<div
		v-if="showWikipediaVideo"
		class="modal-overlay"
		@click="dismissModal">
		<video
			id="wikipediaVideo"
			:poster="`${ assets_path }/images/wikipedia-video-poster.jpg`">
			<source
				v-for="source in wikipediaVideoSources"
				:key="source.url"
				:src="source.url"
				:type="source.type">
		</video>
	</div>
</template>

<script>
const { defineComponent, ref, nextTick } = require( 'vue' );

module.exports = exports = defineComponent( {
	name: 'RelatedContentComponent',

	setup() {
		const wikipediaVideoSources = mw.config.get( 'wikipediaVideoSources' ),
			showWikipediaVideo = ref( false );

		return {
			assets_path: mw.config.get( 'assets_path' ),
			donorFaqUrl: mw.config.get( 'donorFaqUrl' ),
			otherWaysUrl: mw.config.get( 'otherWaysUrl' ),
			legacyUrl: mw.config.get( 'legacyUrl' ),
			newDonationUrl: mw.config.get( 'newDonationUrl' ),
			showWikipediaVideo,
			wikipediaVideoSources,
			launchWikipediaVideo: async () => {
				showWikipediaVideo.value = true;
				await nextTick();
				document.getElementById( 'wikipediaVideo' ).play();
			},
			dismissModal: () => {
				showWikipediaVideo.value = false;
			}
		};
	},
	computed: {
		howWikipediaWorksAltText() {
			return this.$i18n( 'donorportal-aside-faq-how-wikipedia-works' ).text();
		}
	}
} );
</script>
