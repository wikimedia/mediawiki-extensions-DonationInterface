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
				<a
					href="https://donate.wikimedia.org/"
					target="_blank"
					class="link text--body-small">{{ $i18n( "donorportal-login-problems" ).text() }}</a>
			</div>
		</section>
		<section class="auth__display">
			<figure>
				<img
					src="https://upload.wikimedia.org/wikipedia/commons/thumb/d/d1/Son_kanat_%C3%A7%C4%B1rp%C4%B1%C5%9F.jpg/2560px-Son_kanat_%C3%A7%C4%B1rp%C4%B1%C5%9F.jpg"
					alt="Wiki Loves Folklore">
				<figcaption v-html="figureCaption"></figcaption>
			</figure>
		</section>
	</main>
</template>

<script>
const { defineComponent } = require( 'vue' );
module.exports = exports = defineComponent( {
	data() {
		return {
			newLinkRequest: this.$i18n( 'emailpreferences-send-new-link' ).text(),
			emailPlaceholder: this.$i18n( 'donorportal-login-email-placeholder' ).text(),
			figureCaption: this.$i18n( 'donorportal-loginpage-figure-caption' ).text(),
			donorEmail: '',
			api_error: '',
			checksum_link_sent: false
		};
	},
	computed: {
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
