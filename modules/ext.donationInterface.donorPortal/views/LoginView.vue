<template>
	<main class="auth">
		<section class="auth__infobox">
			<div class="auth__infobox-inner">
				<div class="auth__intro">
					<h1 class="heading heading--h1">
						{{ $i18n( "donorportal-login-header" ).text() }}
					</h1>
					<p class="text text--body-small">
						{{ $i18n( "donorportal-login-text" ).text() }}
					</p>
				</div>
				<form class="auth__form send-new-link">
					<div class="cdx-text-input">
						<input
							id="new-checksum-link-email"
							ref="new-checksum-link-email"
							class="cdx-text-input__input"
							type="text"
							:value="donorEmail"
							:placeholder="emailPlaceholder"
							required
							@input="handleInputChange">
					</div>
					<button
						id="request-link-button"
						type="submit"
						class="cdx-button cdx-button--action-progressive cdx-button--weight-primary"
						@click="handleSubmitButtonClick"
						v-html="newLinkRequest">
					</button>
				</form>
				<p
					id="link-sent-text"
					class="link-sent"
					:style="`display: ${checksum_link_sent ? 'block' : 'none'};`">
					{{ $i18n(
						"emailpreferences-new-link-sent" ).text() }}
				</p>
				<p
					id="error-message-text"
					class="error-message-text"
					:style="`display: ${error_message ? 'block' : 'none'};`">
					{{ error_message }}
				</p>
				<popup-link>
					<template #link-text>
						{{ $i18n( 'donorportal-login-problems' ).text() }}
					</template>
					<template #popup-body>
						<h2 id="popup-title">
							{{ $i18n( 'donorportal-login-problems' ).text() }}
						</h2>
						<p class="popup-body">
							{{ $i18n( 'donorportal-update-donation-problem-log-in' ).text() }}
						</p>
						<p
							class="popup-body"
							v-html="problemLoginLink"></p>
					</template>
				</popup-link>
			</div>
		</section>
		<section class="auth__display">
			<figure>
				<img
					:src="`${ assets_path }/images/login-page-bg.jpg`"
					:alt="figureAltText">
				<figcaption v-html="figureCaption"></figcaption>
			</figure>
		</section>
	</main>
</template>

<script>
const { defineComponent } = require( 'vue' );
const PopupLink = require( '../components/PopupLink.vue' );

module.exports = exports = defineComponent( {
	components: {
		'popup-link': PopupLink
	},
	setup() {
		return {
			assets_path: mw.config.get( 'assets_path' ),
			helpEmail: mw.config.get( 'help_email' )
		};
	},
	data() {
		return {
			donorEmail: '',
			api_error: '',
			checksum_link_sent: false
		};
	},
	computed: {
		figureTitle() {
			return this.$i18n( 'donorportal-loginpage-figure-title' ).text();
		},
		newLinkRequest() {
			return this.$i18n( 'emailpreferences-send-new-link' ).text();
		},
		emailPlaceholder() {
			return this.$i18n( 'donorportal-login-email-placeholder' ).text();
		},
		problemLoginLink() {
			const donorRelationTeam = this.$i18n( 'donorportal-update-donation-donor-relations-team' ).text();
			const problemLogin = this.$i18n( 'donorportal-login-problems' ).text();
			return this.$i18n( 'donorportal-update-donation-problem-log-in-contact-us', `<a href="mailto:${ this.helpEmail }?subject=${ problemLogin }">${ donorRelationTeam }</a>` ).text();
		},
		figureCaption() {
			return this.$i18n( 'donorportal-loginpage-figure-caption', `<a href=\"https://commons.wikimedia.org/wiki/File:Sunrise_View_of_Inle_Lake.jpg\"
			 target=\"_blank\">${ this.figureTitle }</a>` ).text();
		},
		figureAltText() {
			return this.$i18n( 'donorportal-loginpage-figure-alt', this.figureTitle ).text();
		},
		error_message: function () {
			if ( this.api_error ) {
				switch ( this.api_error ) {
					case 'missingparam':
						return this.$i18n( 'donorportal-email-required' ).text();
					default:
						return this.$i18n( 'donorportal-something-wrong' ).text();
				}
			}
			return '';
		}
	},
	methods: {
		requestNewChecksumLink( email, page, subpage ) {
			const api = new mw.Api(),
				params = {
					email: email,
					action: 'requestNewChecksumLink',
					page: page
				};
			if ( subpage ) {
				params.subpage = subpage;
			}
			return api.post( params );
		},
		handleSubmitButtonClick( e ) {
			e.preventDefault();
			// Ensure no request is made after first successful request
			if ( this.$refs[ 'new-checksum-link-email' ].disabled ) {
				return;
			}

			this.$refs[ 'new-checksum-link-email' ].disabled = true;
			this.requestNewChecksumLink(
				this.donorEmail,
				mw.config.get( 'requestNewChecksumPage' ),
				mw.config.get( 'requestNewChecksumSubpage' )
			).then( () => {
				this.checksum_link_sent = true;
			} ).catch( ( error ) => {
				this.api_error = error;
				this.$refs[ 'new-checksum-link-email' ].disabled = false;
			} );
		},
		handleInputChange( e ) {
			this.donorEmail = e.target.value;
			this.api_error = '';
		}
	}
} );
</script>
