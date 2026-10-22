<template>
	<div class="combo-wiki__card payment-method-form" :class="{ 'combo-wiki__card--loading': !fieldsReady }">
		<div id="secure-fields-section">
			<div>
				<cdx-label class="secure-field-label">
					{{ $i18n( 'donate_interface-donor-card-num' ).text() }}
				</cdx-label>
				<input id="combo-cc-number" data-autoscroll>
			</div>

			<div class="combo-wiki__card-fields-row">
				<div>
					<cdx-label class="secure-field-label">
						{{ $i18n( 'donate_interface-donor-expiration' ).text() }}
					</cdx-label>
					<input id="combo-cc-expiry" data-autoscroll>
				</div>
				<div>
					<cdx-label class="secure-field-label">
						{{ $i18n( 'donate_interface-donor-security' ).text() }}
					</cdx-label>
					<input id="combo-cc-cvv" data-autoscroll>
				</div>
			</div>
		</div>

		<cdx-message
			v-if="showErrorSummary"
			type="error"
			class="combo-wiki__error-summary"
		>
			{{ $i18n( 'donate_interface-error-msg-validation' ).text() }}
		</cdx-message>

		<cdx-button
			action="progressive"
			weight="primary"
			class="combo-wiki__button-submit"
			@click="submit"
		>
			{{ $i18n( 'donate_interface-submit-button' ).text() }}
		</cdx-button>
	</div>
</template>

<script>
/* global SecureFields */
const { defineComponent } = require( 'vue' );
const { CdxButton, CdxLabel, CdxMessage } = require( '@wikimedia/codex' );
const { loadScript } = require( '../utils.js' );

module.exports = exports = defineComponent( {
	name: 'GravyCardForm',

	components: {
		'cdx-button': CdxButton,
		'cdx-label': CdxLabel,
		'cdx-message': CdxMessage
	},

	inject: [ 'params' ],

	props: {
		donation: {
			type: Object,
			required: true
		},
		// The details form above the card fields, see PaymentMethodForm
		donorDetails: {
			type: Object,
			default: null
		}
	},

	emits: [ 'presubmit', 'submit', 'error' ],

	data() {
		return {
			secureFields: null,
			fieldsReady: false,
			formValid: false,
			submitAttempted: false
		};
	},

	computed: {
		showErrorSummary() {
			const detailsValid = !this.donorDetails || this.donorDetails.valid;
			return this.submitAttempted && !( detailsValid && this.formValid );
		}
	},

	methods: {
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
			this.submitAttempted = true;
			if ( this.donorDetails && !this.donorDetails.validate() ) {
				return;
			}
			// SecureFields only says whether all card fields are complete, so the summary covers them
			if ( !this.formValid ) {
				return;
			}
			this.secureFields.submit();
		}

	},

	mounted() {
		const config = this.params.gravyConfiguration;
		const onSuccessfulSessionCreated = ( sessionId ) => {
			this.setupSecureFields( config, sessionId );
		};
		const onError = () => {
			this.$emit( 'error', 'card-session-setup-failed' );
		};
		loadScript( config.secureFieldsJsScript )
			.then( () => {
				if ( this.donation.gateway_session_id ) {
					onSuccessfulSessionCreated( this.donation.gateway_session_id );
					return;
				}
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
