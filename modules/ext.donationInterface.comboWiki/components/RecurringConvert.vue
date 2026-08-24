<template>
	<div
		v-if="isVisible"
		class="mc-modal-backdrop"
		@click.self="handleDonate( presetAmount, true )"
	>
		<div
			class="mc-modal"
			tabindex="-1"
			role="dialog"
			aria-labelledby="mcModalTitle"
			aria-modal="true"
		>
			<!-- Close Button -->
			<cdx-button
				class="mc-close"
				action="progressive"
				aria-label="Close"
				@click="handleDonate( presetAmount, true )"
			>
				&times;
			</cdx-button>

			<!-- Standard Choice View -->
			<div v-if="!isEditing" class="mc-choice">
				<h2
					id="mcModalTitle"
					class="mc-title"
					tabindex="-1"
					v-html="formattedConvertTitle"
				>
				</h2>

				<p class="mc-subtitle">
					{{ $i18n( 'donate_interface-monthly-convert-text' ).text() }}
				</p>

				<div :class="isPreModal ? 'mc-actions-vertical' : 'mc-actions'">
					<cdx-button
						weight="primary"
						action="progressive"
						class="mc-yes-btn"
						@click="handleDonate( presetAmount )"
					>
						{{ acceptRecurringDonation }}
					</cdx-button>
					<cdx-button
						action="progressive"
						class="mc-no-btn"
						@click="handleDonate( presetAmount, true )"
					>
						{{ declineRecurringDonation }}
					</cdx-button>
				</div>

				<cdx-button
					action="progressive"
					weight="quiet"
					class="mc-diff-amount-link"
					@click="isEditing = true"
				>
					{{ $i18n( 'donate_interface-monthly-convert-change-amount' ).text() }}
				</cdx-button>
			</div>

			<!-- Edit Amount View -->
			<div v-else class="mc-edit-amount">
				<h2
					id="mcEditTitle"
					class="mc-title"
					tabindex="-1"
				>
					{{ newAmountTitle }}
				</h2>
				<p v-if="isPreModal">
					{{ $i18n( 'donate_interface-monthly-convert-enter-amount-short' ).text() }}
				</p>
				<div class="mc-actions-horizontal">
					<cdx-text-input
						v-model="otherAmount"
						input-type="number"
						class="mc-other-amount-input"
						:class="{ 'combo-wiki__errorHighlight': isSmallAmountError }"
					>
					</cdx-text-input>
					<span> {{ donation.currency }}</span>
				</div>

				<p
					v-if="isSmallAmountError"
					id="mc-error-smallamount"
					class="combo-wiki__error"
				>
					{{ $i18n( 'donate_interface-monthly-convert-minimun-hint' ).text() }} {{ formattedMinLocal }}.
				</p>

				<div class="mc-actions mc-edit-actions">
					<cdx-button
						action="progressive"
						weight="primary"
						class="mc-btn mc-donate-monthly-button"
						@click="handleCustomDonate"
					>
						{{ $i18n( 'donate_interface-monthly-convert-action-button' ).text() }}
					</cdx-button>
					<cdx-button
						action="progressive"
						weight="quiet"
						class="mc-btn mc-back"
						@click="isEditing = false"
					>
						{{ $i18n( 'donate_interface-back-button' ).text() }}
					</cdx-button>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxButton, CdxTextInput } = require( '@wikimedia/codex' );
const { useAppState } = require( '../composables/useAppState.js' );

module.exports = exports = defineComponent( {
	name: 'RecurringConvert',

	components: {
		'cdx-button': CdxButton,
		'cdx-text-input': CdxTextInput
	},

	props: {
		donation: { type: Object, required: true },
		language: { type: String, default: 'en' },
		gateway: { type: String, required: true },
		utmToken: { type: String, default: '' },
		thankYouUrl: { type: String, required: true },
		convertAmounts: {
			type: Array,
			default: () => [
				[ 2.75, 1 ],
				[ 5, 2 ]
			]
		},
		currencyRates: { type: Object, default: () => ( {} ) },
		amountRules: {
			type: Object,
			default: () => ( { currency: 'USD', min: 1 } )
		}
	},

	emits: [ 'close', 'update:modelValue', 'recurring-convert-submit' ],

	data() {
		return {
			isVisible: false,
			isEditing: false,
			otherAmount: null,
			isSmallAmountError: false,
			originalAmount: Number( this.donation.amount ) || 0,
			currency: this.donation.currency || 'USD',
			country: this.donation.country || 'US'
		};
	},

	computed: {
		locale() {
			return `${ this.language }-${ this.country }`;
		},
		isPreModal() {
			return [ 'paypal', 'venmo' ].includes( this.donation.paymentMethod );
		},
		presetAmount() {
			const numericAmount = Number( this.originalAmount ) || 1;
			return this.getConvertAsk( numericAmount );
		},
		minLocal() {
			const { currency: baseCurrency, min } = this.amountRules || { currency: 'USD', min: 1 };
			if ( this.currency === baseCurrency || !this.currencyRates[ this.currency ] ) {
				return min;
			}
			return ( min / this.currencyRates[ baseCurrency ] ) * this.currencyRates[ this.currency ];
		},
		acceptRecurringDonation() {
			if ( this.isPreModal ) {
				const rawTitle = this.$i18n( 'donate_interface-monthly-convert-accept' ).text();
				return rawTitle.replace(
					'$1',
					`${ this.formattedPresetAmount }`
				);
			} else {
				return this.$i18n( 'donate_interface-confirmation-yes' ).text();
			}
		},
		newAmountTitle() {
			if ( this.isPreModal ) {
				return this.$i18n( 'donate_interface-donate-error-thank-you-for-your-support' ).text();
			} else {
				return this.$i18n( 'donate_interface-monthly-convert-enter-amount' ).text();
			}
		},
		declineRecurringDonation() {
			if ( this.isPreModal ) {
				const rawTitle = this.$i18n( 'donate_interface-monthly-convert-decline' ).text();
				return rawTitle.replace(
					'$1',
					`${ this.formattedOriginalOneTimeAmount }`
				);
			} else {
				return this.$i18n( 'donate_interface-confirmation-no' ).text();
			}
		},
		formattedPresetAmount() {
			return this.formatAmount( this.presetAmount, this.currency, this.locale );
		},
		formattedOriginalOneTimeAmount() {
			return this.formatAmount( Number( this.originalAmount ), this.currency, this.locale );
		},
		formattedMinLocal() {
			return this.formatAmount( this.minLocal, this.currency, this.locale );
		},
		formattedConvertTitle() {
			if ( this.isPreModal ) {
				return this.$i18n( 'donate_interface-monthly-convert-title-premodal' ).text();
			} else {
				const rawTitle = this.$i18n( 'donate_interface-monthly-convert-title' ).text();
				return rawTitle.replace(
					'$1',
					`<span class="mc-convert-ask">${ this.formattedPresetAmount }</span>`
				);
			}
		}
	},

	methods: {
		formatAmount( amount, curr, loc ) {
			try {
				return amount.toLocaleString( loc, { currency: curr, style: 'currency' } );
			} catch ( e ) {
				return `${ curr } ${ amount.toFixed( 2 ) }`;
			}
		},
		getConvertAsk( amount ) {
			if ( !this.convertAmounts || this.convertAmounts.length === 0 ) {
				return Math.round( amount * 0.3 ); // Fallback calculation
			}
			for ( let i = 0; i < this.convertAmounts.length; i++ ) {
				if ( amount <= this.convertAmounts[ i ][ 0 ] ) {
					return this.convertAmounts[ i ][ 1 ];
				}
			}
			// Fallback if original amount exceeds all defined thresholds:
			const lastItem = this.convertAmounts[ this.convertAmounts.length - 1 ];
			return lastItem[ 1 ] || Math.round( amount * 0.3 );
		},

		handleCustomDonate() {
			const val = Number( this.otherAmount );
			if ( val < this.minLocal ) {
				this.isSmallAmountError = true;
			} else {
				this.isSmallAmountError = false;
				this.handleDonate( val );
			}
		},
		async handleDonate( amount, declineMonthlyConvert = false ) {
			const appState = useAppState();
			if ( this.isPreModal ) {
				const updatedDonation = Object.assign( {}, this.donation );
				if ( !declineMonthlyConvert ) {
					updatedDonation.frequency = 'monthly';
					updatedDonation.amount = amount;
				}

				appState.setShowRecurringConvert( false );
				this.$emit( 'recurring-convert-submit', updatedDonation );
			} else {
				const api = new mw.Api();
				const payload = {
					action: 'di_recurring_convert',
					gateway: this.gateway,
					utm_token: this.utmToken,
					amount: amount
				};

				if ( declineMonthlyConvert ) {
					payload.declineMonthlyConvert = true;
				}
				appState.setLoading( true );
				try {
					const response = await api.post( payload );
					const url = new URL( this.thankYouUrl, window.location.href );

					if ( response && !response.error && response.result && !response.result.errors ) {
						if ( !declineMonthlyConvert ) {
							url.searchParams.set( 'recurringConversion', '1' );
						}
					} else if ( !declineMonthlyConvert ) {
						alert( 'An error occurred during monthly conversion.' );
					}
					appState.setLoading( false );
					this.$emit( 'close', url.toString() );
				} catch ( err ) {
					if ( !declineMonthlyConvert ) {
						alert( 'An error occurred during monthly conversion.' );
					}
					appState.setLoading( false );
					this.$emit( 'close', this.thankYouUrl );
				}
			}
		}
	},

	mounted() {
		if ( this.presetAmount === 0 && this.thankYouUrl ) {
			this.$emit( 'close', this.thankYouUrl );
		} else {
			this.isVisible = true;
			this.isEditing = false;
		}
	}
} );
</script>
