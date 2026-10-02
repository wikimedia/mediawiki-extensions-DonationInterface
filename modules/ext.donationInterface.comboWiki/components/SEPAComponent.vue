<template>
	<div class="combo-wiki__sepa">
		<div class="combo-wiki__sepa-fields">
			<div class="combo-wiki__sepa-fields-full-name">
				<cdx-field :is-required="true">
					<cdx-label input-id="sepa-first-name-field">
						{{ $i18n( 'donate_interface-donor-first_name' ).text() }}
					</cdx-label>
					<cdx-text-input
						id="sepa-first-name-field"
						v-model="firstName"
						data-autoscroll
						:placeholder="$i18n( 'donate_interface-donor-first_name' ).text()"
					>
					</cdx-text-input>
				</cdx-field>
				<cdx-field :is-required="true">
					<cdx-label input-id="sepa-last-name-field">
						{{ $i18n( 'donate_interface-donor-last_name' ).text() }}
					</cdx-label>
					<cdx-text-input
						id="sepa-last-name-field"
						v-model="lastName"
						data-autoscroll
						:placeholder="$i18n( 'donate_interface-donor-last_name' ).text()"
					>
					</cdx-text-input>
				</cdx-field>
			</div>
			<cdx-field :is-required="true">
				<cdx-label input-id="sepa-email-field">
					{{ $i18n( 'donate_interface-donor-email' ).text() }}
				</cdx-label>
				<cdx-text-input
					id="sepa-email-field"
					v-model="email"
					data-autoscroll
					type="email"
					autocomplete="email"
				></cdx-text-input>
			</cdx-field>
		</div>

		<p v-if="donation.frequency === 'once'">
			{{ $i18n( 'donate_interface-sepa-mandate' ).text() }}
		</p>
		<p v-else-if="donation.frequency === 'monthly'">
			{{ $i18n( 'donate_interface-sepa-mandate-monthly' ).text() }}
		</p>
		<p v-else-if="donation.frequency === 'annual'">
			{{ $i18n( 'donate_interface-sepa-mandate-yearly' ).text() }}
		</p>

		<cdx-button
			action="progressive"
			weight="primary"
			:disabled="isSubmitting || !canSubmit"
			@click="submit"
		>
			{{ donateBtn }}
		</cdx-button>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxButton, CdxField, CdxLabel, CdxTextInput } = require( '@wikimedia/codex' );

module.exports = exports = defineComponent( {
	name: 'SEPAComponent',

	components: {
		CdxButton,
		CdxField,
		CdxLabel,
		CdxTextInput
	},

	props: {
		donation: {
			type: Object,
			required: true
		}
	},

	emits: [ 'submit', 'error' ],

	data() {
		return {
			email: this.donation.email,
			lastName: this.donation.lastName,
			firstName: this.donation.firstName,
			isSubmitting: false
		};
	},

	computed: {
		canSubmit() {
			return this.detailsComplete;
		},
		detailsComplete() {
			const hasFirstName = Boolean( this.firstName && this.firstName.trim() );
			const hasLastName = Boolean( this.lastName && this.lastName.trim() );
			const hasEmail = this.isValidEmail( this.email );

			return hasFirstName && hasLastName && hasEmail;
		},
		donateBtn() {
			if ( this.isSubmitting ) {
				return '...';
			}
			return this.$i18n( 'donate_interface-submit-button' ).text();
		}
	},

	methods: {
		isValidEmail( email ) {
			return typeof email === 'string' && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( email.trim() );
		},
		handleSubmitFailure() {
			this.isSubmitting = false;
		},
		submit() {
			if ( this.isSubmitting || !this.canSubmit ) {
				return;
			}

			this.isSubmitting = true;
			this.$emit(
				'submit',
				{
					email: this.email,
					first_name: this.firstName,
					last_name: this.lastName
				},
				null,
				this.handleSubmitFailure
			);
		}
	}
} );
</script>
