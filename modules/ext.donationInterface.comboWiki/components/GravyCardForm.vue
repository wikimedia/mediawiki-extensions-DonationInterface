<template>
	<div class="combo-wiki__card" :class="{ 'combo-wiki__card--loading': !fieldsReady }">
		<div>
			<h2>{{ $i18n( 'combowiki-your-details-heading' ).text() }}</h2>

			<cdx-text-input
				v-model="firstName"
				:placeholder="$i18n( 'donate_interface-donor-first_name' ).text()"
			>
			</cdx-text-input>

			<cdx-text-input
				v-model="lastName"
				:placeholder="$i18n( 'donate_interface-donor-last_name' ).text()"
			>
			</cdx-text-input>

			<cdx-text-input
				v-model="email"
				:placeholder="$i18n( 'donate_interface-donor-email' ).text()"
			>
			</cdx-text-input>
		</div>

		<label for="combo-cc-number">{{ $i18n( 'donate_interface-donor-card-num' ).text() }}</label>
		<input id="combo-cc-number">

		<div class="combo-wiki__card-row">
			<div>
				<label for="combo-cc-expiry">{{ $i18n( 'donate_interface-donor-expiration' ).text() }}</label>
				<input id="combo-cc-expiry">
			</div>
			<div>
				<label for="combo-cc-cvv">{{ $i18n( 'donate_interface-donor-security' ).text() }}</label>
				<input id="combo-cc-cvv">
			</div>
		</div>

		<cdx-button
			action="progressive"
			weight="primary"
			:disabled="!canSubmit"
			@click="submit"
		>
			{{ $i18n( 'donate_interface-submit-button' ).text() }}
		</cdx-button>
	</div>
</template>

<script>
/* global SecureFields */
const { defineComponent } = require( 'vue' );
const { CdxButton, CdxTextInput } = require( '@wikimedia/codex' );

module.exports = exports = defineComponent( {
	name: 'GravyCardForm',

	components: {
		'cdx-button': CdxButton,
		'cdx-text-input': CdxTextInput
	},

	props: {
		donation: {
			type: Object,
			required: true
		}
	},

	emits: [ 'presubmit', 'submit', 'error' ],

	data() {
		return {
			email: this.donation.email,
			lastName: this.donation.lastName,
			firstName: this.donation.firstName,
			secureFields: null,
			fieldsReady: false,
			formValid: false
		};
	},

	computed: {
		canSubmit() {
			return this.formValid && this.detailsComplete;
		},
		detailsComplete() {
			const hasFirstName = Boolean( this.firstName && this.firstName.trim() );
			const hasLastName = Boolean( this.lastName && this.lastName.trim() );
			const hasEmail = this.isValidEmail( this.email );

			return hasFirstName && hasLastName && hasEmail;
		}
	},

	methods: {
		loadScript( src ) {
			return new Promise( ( resolve , reject )  => {
				const node = document.createElement( 'script' );
				node.src = src;
				node.onload = resolve;
				node.onerror = reject;
				document.body.append( node );
			} );
		},
		isValidEmail( email ) {
			return typeof email === 'string' && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( email.trim() );
		},
		setupSecureFields( config, sessionId ) {
			this.secureFields = new SecureFields( {
				gr4vyId: config.gravyID,
				environment: config.environment,
				sessionId: sessionId
			} );

			this.secureFields.addCardNumberField( '#combo-cc-number' );
			this.secureFields.addSecurityCodeField( '#combo-cc-cvv' );
			this.secureFields.addExpiryDateField( '#combo-cc-expiry' );
			this.fieldsReady = true;

			this.secureFields.addEventListener( SecureFields.Events.FORM_CHANGE, ( data ) => {
				if ( data ) {
					this.formValid = data.complete;
				}
			} );

			this.secureFields.addEventListener( SecureFields.Events.CARD_VAULT_SUCCESS, ( data ) => {
				this.$emit( 'submit', {
					email: this.email,
					first_name: this.firstName,
					last_name: this.lastName,
					gateway_session_id: sessionId,
					card_scheme: data.scheme,
					color_depth: screen.colorDepth || 24,
					screen_height: screen.height || 0,
					screen_width: screen.width || 0,
					time_zone_offset: Math.floor( new Date().getTimezoneOffset() ) || 0
				} );
			} );

			this.secureFields.addEventListener( SecureFields.Events.CARD_VAULT_FAILURE, ( data ) => {
				console.log( 'card vault failure', data );
				this.$emit( 'error', 'card-vault-failure' );
			} );
		},
		submit() {
			this.secureFields.submit();
		}

	},

	mounted() {
		const config = mw.config.get( 'gravyConfiguration' );
		const onSuccessfulSessionCreated = ( sessionId ) => {
			this.setupSecureFields( config, sessionId );
		};
		const onError = () => {
			this.$emit( 'error', 'card-session-setup-failed' );
		};
		this.loadScript( config.secureFieldsJsScript )
			.then( () => {
				this.$emit( 'presubmit', this.donation, onSuccessfulSessionCreated, onError );
			} )
			.catch( () => {
				onError();
			} );

	},

	beforeUnmount() {
		// Drop the SDK instance so it doesn't stay in browser memory when not needed
		this.secureFields = null;
	}
} );

</script>
