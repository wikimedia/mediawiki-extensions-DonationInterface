<template>
	<div class="fieldset gap--3">
		<div class="fieldset__label">
			<p class="text text--base">
				<strong>{{ $i18n( 'combowiki-stay-in-touch-heading' ).text() }}</strong>
			</p>
		</div>
		<div>
			<cdx-radio
				:model-value="modelValue"
				input-value="yes"
				name="email-optin"
				@update:model-value="handleSelection"
			>
				{{ $i18n( 'combowiki-optin-yes' ).text() }}
			</cdx-radio>

			<cdx-radio
				:model-value="modelValue"
				input-value="no"
				name="email-optin"
				@update:model-value="handleSelection"
			>
				{{ $i18n( 'combowiki-optin-no' ).text() }}
			</cdx-radio>

			<!-- Dynamic Feedback Box -->
			<div
				v-if="feedbackText"
				class="optin-feedback-box"
				:class="feedbackType"
			>
				{{ feedbackText }}
			</div>
			<div v-html="optInDescription">
			</div>
		</div>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxRadio } = require( '@wikimedia/codex' );

module.exports = exports = defineComponent( {
	name: 'OptinFieldset',

	components: {
		'cdx-radio': CdxRadio
	},
	props: {
		modelValue: {
			type: String,
			default: ''
		}
	},
	emits: [ 'update:modelValue' ],
	data() {
		return {
			hasSelectedNo: false,
			feedbackText: '',
			feedbackType: '', // 'red-border' or 'green-border'
			policyUrl: 'https://foundation.wikimedia.org/wiki/Policy:Donor_privacy_policy'
		};
	},

	computed: {
		sorryToHearMsg() {
			return this.$i18n( 'combowiki-optin-no-text' ).text();
		},
		thanksForChangingMsg() {
			return this.$i18n( 'combowiki-optin-no-to-yes-text' ).text();
		},
		optInDescription() {
			return this.$i18n(
				'combowiki-optin-description',
				this.policyUrl,
				this.$i18n( 'combowiki-donor-privacy-policy' ).text()
			).parse();
		}
	},

	methods: {
		handleSelection( newValue ) {
			// Emit update to parent component
			this.$emit( 'update:modelValue', newValue );

			// Dynamic border state management
			if ( newValue === 'no' ) {
				this.hasSelectedNo = true;
				this.feedbackText = this.sorryToHearMsg;
				this.feedbackType = 'red-border';
			} else if ( newValue === 'yes' ) {
				if ( this.hasSelectedNo ) {
					this.feedbackText = this.thanksForChangingMsg;
					this.feedbackType = 'green-border';
				} else {
					this.feedbackText = '';
					this.feedbackType = '';
				}
			}
		}
	}
} );
</script>
