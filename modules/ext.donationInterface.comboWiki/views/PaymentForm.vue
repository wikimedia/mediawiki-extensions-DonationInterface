<template>
	<main class="combo-wiki__home">
		<h2>{{ $i18n( 'combowiki-frequency-heading' ).text() }}</h2>

		<frequency-selector v-model="donation.frequency"></frequency-selector>

		<div>
			<!-- Country selection -->
			<cdx-select
				v-model:selected="donation.country"
				:menu-items="countryOptions"
				:default-label="$i18n( 'combowiki-country-placeholder' ).text()"
				@update:selected="onCountryChange"
			>
			</cdx-select>

			<!-- Amount -->
			<cdx-button
				v-for="amount in presetAmounts"
				:key="amount"
				:weight="donation.amount === amount ? 'primary' : 'normal'"
				:class="{ 'combo-wiki__option--selected': Number( donation.amount ) === amount }"
				@click="selectAmount( amount )"
			>
				{{ formattedAmount( amount ) }}
			</cdx-button>

			<!-- Custom Amount -->
			<cdx-text-input
				v-model="donation.amount"
				input-type="number"
				:placeholder="$i18n( 'donate_interface-other-amount' ).text()"
			>
			</cdx-text-input>

			<!-- Pay the fee -->
			<cdx-checkbox v-model="donation.payFee">
				{{ $i18n( 'combowiki-cover-fees' ).text() }}
			</cdx-checkbox>
		</div>

		<!-- Email opt-in -->
		<div>
			<h2>{{ $i18n( 'combowiki-stay-in-touch-heading' ).text() }}</h2>

			<cdx-radio
				v-model="donation.optIn"
				input-value="yes"
				name="email-optin"
			>
				{{ $i18n( 'combowiki-optin-yes' ).text() }}
			</cdx-radio>

			<cdx-radio
				v-model="donation.optIn"
				input-value="no"
				name="email-optin"
			>
				{{ $i18n( 'combowiki-optin-no' ).text() }}
			</cdx-radio>
		</div>

		<!-- Employer -->
		<employer-field v-model="donation.employer"></employer-field>

		<!-- Variants -->
		<variant-fields v-model="donation.smsOptin"></variant-fields>

		<!-- Payment methods -->
		<payment-method-form
			:donation="donation"
			:disabled="!giftComplete"
			@donation-success="handleDonateResult"
			@donation-error="handleDonateError"
			@on-payment-method-change="( method ) => {
				donation.paymentMethod = method
			}"
		></payment-method-form>
		<br>
		<p>
			Debug - Frequency: {{ donation.frequency || "nothing yet" }} / {{ donation.currency }} {{ donation.amount || "no amount" }} / Fee:
			{{ feeAmount }} / Email Opt-in:{{ donation.optIn }} / Payment Method:
			{{ donation.paymentMethod }} / Employer: {{ donation.employer }} / Gateway: {{ selectedGateway }}
		</p>
		<p> Debug Request Params - {{ params }} </p>
		<p v-if="donateError" class="combo-wiki__error">
			{{ donateError }}
		</p>
		<we-do-not-sell-text></we-do-not-sell-text>
		<more-info-links text-class="combo-wiki__link-container"></more-info-links>
		<loading-spinner></loading-spinner>

		<!-- Recurring Convert Modal -->
		<recurring-convert
			v-if="appState.showRecurringConvert.value"
			:donation="donation"
			:language="params.language || 'en'"
			:gateway="selectedGateway"
			:utm-token="params.utm_token || ''"
			:thank-you-url="thankYouUrl"
			@close="redirectTargetUrl"
			@recurring-convert-submit="submitPreModalDonation"
		></recurring-convert>
	</main>
</template>

<script>
const { defineComponent } = require( 'vue' );
const {
	CdxButton,
	CdxTextInput,
	CdxSelect,
	CdxCheckbox,
	CdxRadio
} = require( '@wikimedia/codex' );
const FrequencySelector = require( '../components/FrequencySelector.vue' );
const PaymentMethodForm = require( '../components/PaymentMethodForm.vue' );
const WeDoNotSellText = require( '../components/WeDoNotSellText.vue' );
const MoreInfoLinks = require( '../components/MoreInfoLinks.vue' );
const EmployerField = require( '../components/EmployerField.vue' );
const LoadingSpinner = require( '../components/LoadingSpinner.vue' );
const RecurringConvert = require( '../components/RecurringConvert.vue' );
const VariantFieldsComponent = require( '../components/VariantFieldsComponent.vue' );
// if these are only sometimes loaded, is there a better way to if include them
const { useAppState } = require( '../composables/useAppState.js' );

const BASE_USD_PRESETS = [ 2.75, 5, 10, 20, 30, 50, 100 ];

module.exports = exports = defineComponent( {
	name: 'PaymentForm',

	components: {
		'cdx-button': CdxButton,
		'cdx-text-input': CdxTextInput,
		'cdx-select': CdxSelect,
		'cdx-checkbox': CdxCheckbox,
		'cdx-radio': CdxRadio,
		'frequency-selector': FrequencySelector,
		'payment-method-form': PaymentMethodForm,
		'we-do-not-sell-text': WeDoNotSellText,
		'more-info-links': MoreInfoLinks,
		'employer-field': EmployerField,
		'loading-spinner': LoadingSpinner,
		'recurring-convert': RecurringConvert,
		'variant-fields': VariantFieldsComponent
	},
	inject: [ 'params' ],
	setup() {
		const appState = useAppState();
		return { appState };
	},
	data() {
		const urlParams = new URLSearchParams( window.location.search );
		const country = urlParams.get( 'country' ) || 'US';
		const comboWikiConfig = mw.config.get( 'comboWiki', {} );
		const initialCurrency = comboWikiConfig.params.currency || 'USD';
		const countries = mw.config.get( 'wgDonationInterfaceCountries', {} );
		return {
			countries,
			donation: {
				firstName: null,
				lastName: null,
				email: null,
				frequency: 'once',
				amount: null,
				currency: initialCurrency,
				payFee: false,
				country: country,
				paymentMethod: null,
				optIn: null,
				employer: null,
				smsOptin: null
			},
			selectedGateway: comboWikiConfig.gateway || null,
			donateError: null,
			thankYouUrl: null
		};
	},
	computed: {
		presetAmounts() {
			const rates = mw.config.get( 'wgDonationInterfaceCurrencyRates', {} );
			const currency = this.donation.currency;

			if ( !currency || currency === 'USD' || !rates[ currency ] ) {
				return BASE_USD_PRESETS;
			}

			const rate = rates[ currency ];

			return BASE_USD_PRESETS.map( ( usdAmount ) => {
				const converted = usdAmount * rate;
				// Ensure the converted amount meets or exceeds 1 USD equivalent in value
				const minAmount = rates[ currency ];

				if ( converted < minAmount ) {
					return Math.ceil( minAmount );
				}

				// Round clean values based on scale (e.g. round to nearest integer or 5)
				if ( converted > 100 ) {
					return Math.ceil( converted / 5 ) * 5;
				}
				return Math.ceil( converted );
			} );
		},
		feeAmount() {
			if ( !this.donation.payFee || !this.donation.amount ) {
				return 0;
			}

			return Math.round( this.donation.amount * 0.035 * 100 ) / 100;
		},
		countryOptions() {
			return Object.entries( this.countries ).map( ( [ country, config ] ) => ( {
				label: config.label + ' (' + config.currency + ')',
				value: country
			} ) );
		},
		giftComplete() {
			return this.donation.frequency !== null && this.donation.amount !== null;
		},
		formattedAmount() {
			return ( amount ) => {
				if ( !amount ) {
					return '';
				}

				const lang = ( this.params.language || 'en' ).split( '-' )[ 0 ];
				const country = this.donation.country || 'US';
				const locale = `${ lang }_${ country }`;

				try {
					return new Intl.NumberFormat( locale.replace( '_', '-' ), {
						style: 'currency',
						currency: this.donation.currency
					} ).format( amount );
				} catch ( e ) {

					return `${ this.donation.currency }${ amount }`;
				}
			};
		}
	},

	methods: {
		selectAmount( value ) {
			this.donation.amount = value;
		},
		redirectTargetUrl( targetUrl ) {
			window.location.assign(
				targetUrl ||
					this.thankYouUrl ||
					mw.config.get( 'DonationInterfaceThankYouPage' )
			);
		},
		onCountryChange( country ) {
			const countryConfig = this.countries[ country ];
			if ( !countryConfig ) {
				return;
			}
			this.donation.currency = countryConfig.currency || 'USD';

			const url = new URL( window.location.href );
			url.searchParams.set( 'country', country );
			window.location.assign( url.toString() );
		},
		handleDonateResult( result ) {
			const response = result.result;
			if ( response.isFailed ) {
				this.donateError = this.$i18n( 'combowiki-payment-failed' ).text();
				this.appState.setLoading( false );
				return;
			}
			if ( response.errors ) {
				this.donateError = this.$i18n( 'combowiki-payment-incomplete' ).text();
				this.appState.setLoading( false );
				return;
			}
			if ( this.donation.frequency === 'once' && !response.redirect ) {
				this.thankYouUrl = mw.config.get( 'DonationInterfaceThankYouPage' );
				this.appState.setShowRecurringConvert( true );
			} else {
				this.redirectTargetUrl( response.redirect );
			}
		},
		handleDonateError( code, failure ) {
			this.donateError = this.$i18n( 'combowiki-payment-failed' ).text();
			this.appState.setLoading( false );
			mw.log.error( 'di_donate_gravy failed', code, failure );
		},
		submitPreModalDonation( updatedDonation ) {
			const api = require( '../api.js' );
			const { toRaw } = require( 'vue' );

			if ( updatedDonation ) {
				Object.assign( this.donation, updatedDonation );
			}

			this.appState.setLoading( true );
			api.submitDonation( toRaw( this.donation ) )
				.then( ( result ) => {
					this.handleDonateResult( result );
				} )
				.catch( ( code, failure ) => {
					this.handleDonateError( code, failure );
				} );
		}
	},
	mounted() {
		const urlParams = new URLSearchParams( window.location.search );
		if ( urlParams.get( 'debugMonthlyConvert' ) === '1' ) {
			this.appState.setShowRecurringConvert( true );
		}
	}
} );
</script>
