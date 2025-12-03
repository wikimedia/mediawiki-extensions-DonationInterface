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
					<popup-link>
						<template #link-text>
							<img
								:src="`${ assets_path }/images/how-wikipedia-works.jpg`"
								:alt="$i18n( 'donorportal-aside-faq-how-wikipedia-works' ).text()"
							>
						</template>
						<template #popup-body>
							<div class="modal-overlay">
								<video
									id="wikipediaVideo"
									autoplay
									controls
									:poster="`${ assets_path }/images/wikipedia-video-poster.jpg`">
									<source
										v-for="source in wikipediaVideoSources"
										:key="source.url"
										:src="source.url"
										:type="source.type">
								</video>
							</div>
						</template>
					</popup-link>
				</div>
				<div class="video-attribution">
					<a
						:href="wikipediaVideoCommonsUrl"
						target="_blank">[&nbsp;{{ $i18n( "upload-source" ).text() }}&nbsp;]</a>
				</div>
			</div>
		</section>
		<!-- End of Widget -->
	</aside>
	<!-- End of Related Content -->
</template>

<script>
const { defineComponent } = require( 'vue' );
const PopupLink = require( './PopupLink.vue' );

module.exports = exports = defineComponent( {
	name: 'RelatedContentComponent',
	components: {
		'popup-link': PopupLink
	},
	setup() {

		return {
			assets_path: mw.config.get( 'assets_path' ),
			donorFaqUrl: mw.config.get( 'donorFaqUrl' ),
			otherWaysUrl: mw.config.get( 'otherWaysUrl' ),
			legacyUrl: mw.config.get( 'legacyUrl' ),
			newDonationUrl: mw.config.get( 'newDonationUrl' ),
			wikipediaVideoSources: mw.config.get( 'wikipediaVideoSources' ),
			wikipediaVideoCommonsUrl: mw.config.get( 'wikipediaVideoCommonsUrl' )
		};
	}
} );
</script>
