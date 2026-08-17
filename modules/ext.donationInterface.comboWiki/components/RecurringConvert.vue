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

				<div class="mc-actions">
					<cdx-button
						weight="primary"
						action="progressive"
						class="mc-yes-btn"
						@click="handleDonate( presetAmount )"
					>
						{{ $i18n( 'donate_interface-confirmation-yes' ).text() }}
					</cdx-button>
					<cdx-button
						action="progressive"
						class="mc-no-btn"
						@click="handleDonate( presetAmount, true )"
					>
						{{ $i18n( 'donate_interface-confirmation-no' ).text() }}
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
					{{ $i18n( 'donate_interface-monthly-convert-enter-amount' ).text() }}
				</h2>

				<cdx-text-input
					v-model="otherAmount"
					input-type="number"
					class="mc-other-amount-input"
					:class="{ 'combo-wiki__errorHighlight': isSmallAmountError }"
				>
				</cdx-text-input>

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
		originalAmount: { type: Number, required: true },
		currency: { type: String, required: true },
		language: { type: String, default: 'en' },
		country: { type: String, default: 'US' },
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

	emits: [ 'close' ],

	data() {
		return {
			isVisible: false,
			isEditing: false,
			otherAmount: null,
			isSmallAmountError: false
		};
	},

	computed: {
		locale() {
			return `${ this.language }-${ this.country }`;
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
		formattedPresetAmount() {
			return this.formatAmount( this.presetAmount, this.currency, this.locale );
		},
		formattedMinLocal() {
			return this.formatAmount( this.minLocal, this.currency, this.locale );
		},
		formattedConvertTitle() {
			const rawTitle = this.$i18n( 'donate_interface-monthly-convert-title' ).text();
			return rawTitle.replace(
				'<span class="mc-convert-ask"></span>',
				`<span class="mc-convert-ask">${ this.formattedPresetAmount }</span>`
			);
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
