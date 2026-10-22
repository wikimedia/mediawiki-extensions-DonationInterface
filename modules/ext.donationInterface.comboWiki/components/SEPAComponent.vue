<template>
	<div class="combo-wiki__sepa payment-method-form">
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
			class="combo-wiki__button-submit"
			:disabled="isSubmitting"
			@click="submit"
		>
			{{ donateBtn }}
		</cdx-button>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxButton } = require( '@wikimedia/codex' );

module.exports = exports = defineComponent( {
	name: 'SEPAComponent',

	components: {
		CdxButton
	},

	props: {
		donation: {
			type: Object,
			required: true
		},
		// The details form above this one, see PaymentMethodForm
		donorDetails: {
			type: Object,
			default: null
		}
	},

	emits: [ 'submit', 'error' ],

	data() {
		return {
			isSubmitting: false
		};
	},

	computed: {
		donateBtn() {
			if ( this.isSubmitting ) {
				return '...';
			}
			return this.$i18n( 'donate_interface-submit-button' ).text();
		}
	},

	methods: {
		handleSubmitFailure() {
			this.isSubmitting = false;
		},
		submit() {
			if ( this.isSubmitting || ( this.donorDetails && !this.donorDetails.validate() ) ) {
				return;
			}

			this.isSubmitting = true;
			this.$emit(
				'submit',
				{},
				null,
				this.handleSubmitFailure
			);
		}
	}
} );
</script>
