<template>
	<div class="combo-wiki__sms_opt_in">
		<cdx-field :is-required="false">
			<cdx-label input-id="phone">
				{{ phoneLabel }}
			</cdx-label>
			<cdx-text-input
				id="phone"
				:model-value="phone"
				@update:model-value="updatePhone"
			>
			</cdx-text-input>
		</cdx-field>
		<div>
			<cdx-checkbox
				id="sms_opt_in"
				:model-value="smsOptin"
				@update:model-value="updateSmsOptin"
			>
				{{ smsOptinLabel }}
				<template #description>
					By participating, you consent to receive recurring updates through automated text messages from Wikimedia to the phone number you provide.
					Message frequency varies. For text messages, Msg&Data rates may apply. Text STOP to cancel or HELP for help. <a href="https://foundation.wikimedia.org/wiki/Policy:Wikimedia_Foundation_Donor_SMS_Supplementary_Terms">Terms of Service and Privacy Policy</a>
				</template>
			</cdx-checkbox>
		</div>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxField, CdxLabel, CdxTextInput, CdxCheckbox } = require( '@wikimedia/codex' );
const { stripLeadingWhitespace } = require( '../normalizeInput.js' );

module.exports = defineComponent( {
	name: 'SmsOptin',
	components: {
		'cdx-field': CdxField,
		'cdx-label': CdxLabel,
		'cdx-text-input': CdxTextInput,
		'cdx-checkbox': CdxCheckbox
	},
	props: {
		phone: {
			type: String,
			default: ''
		},
		smsOptin: {
			type: Boolean,
			default: false
		}
	},
	emits: [ 'update:phone','update:smsOptin' ],
	computed: {
		phoneLabel() {
			return this.$i18n( 'donate_interface-donor-phone' ).text();
		},
		smsOptinLabel() {
			return this.$i18n( 'donate_interface-donor-sms_opt_in' ).text();
		}
	},
	methods: {
		updatePhone( value ) {
			this.$emit( 'update:phone', stripLeadingWhitespace( value ) );
		},
		updateSmsOptin( value ) {
			this.$emit( 'update:smsOptin', value );
		}
	}
} );
</script>
