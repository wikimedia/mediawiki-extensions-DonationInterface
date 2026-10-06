<template>
	<main class="combo-wiki__wrapper">
		<div class="combo-wiki__container">
			<section class="combo-wiki__form">
				<!-- Intro -->
				<div class="gap--2">
					<h1 class="heading heading--2">
						{{ $i18n( 'combowiki-intro-heading' ).text() }}
					</h1>
					<p class="text text--base">
						{{ $i18n( 'combowiki-intro-text' ).text() }}
					</p>
				</div>

				<!-- Frequency Selector -->
				<div class="fieldset gap--3">
					<frequency-selector v-model="donation.frequency"></frequency-selector>
				</div>

				<!-- Amount Selector -->
				<div class="fieldset gap--3">
					<!-- Label -->
					<div class="fieldset__label">
						<p class="text text--base">
							<strong>{{ amountHeading }}</strong>
						</p>
						<!-- Country>Currency selection -->
						<cdx-select
							v-model:selected="donation.country"
							:menu-items="countryOptions"
							:default-label="$i18n( 'combowiki-country-placeholder' ).text()"
							@update:selected="onCountryChange"
						>
						</cdx-select>
					</div>
					<!-- Button Stack -->
					<div class="fiedlset__button-grid">
						<!-- Fixed Amounts -->
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
					</div>
					<!-- Pay the fee, shown once there is an amount to base the fee on -->
					<cdx-checkbox v-if="canCoverFee" v-model="donation.payFee">
						{{ $i18n( 'combowiki-cover-fees', formattedAmount( suggestedFee ) ).text() }}
					</cdx-checkbox>
				</div>

				<!-- Email opt-in -->
				<optin-fieldset
					v-if="showField( 'opt_in' )"
					v-model="donation.optIn"
				></optin-fieldset>

				<!-- Employer -->
				<employer-field
					v-if="showField( 'employer' )"
					v-model:employer="donation.employer"
					v-model:employer-id="donation.employerId"
					:is-required="isFieldRequired( 'employer' )"
				></employer-field>

				<!-- Sms Optin -->
				<sms-optin
					v-if="showSmsOptin"
					v-model:phone="donation.phone"
					v-model:sms-optin="donation.smsOptin"
					:is-required="isFieldRequired( 'sms_opt_in' ) && isFieldRequired( 'phone' )"
					@ready="scrollToFirstEmptyField"
				></sms-optin>

				<!-- Hidden when returning to offer monthly convert: mounting the card form
					would create a new checkout session for a donation that is already made -->
				<payment-method-form
					v-if="!params.monthlyConvertReturn"
					:donation="chargedDonation"
					:disabled="!giftComplete"
					@donation-success="handleDonateResult"
					@donation-error="handleDonateError"
					@on-payment-method-change="( method ) => {
						donation.paymentMethod = method
					}"
				></payment-method-form>

				<tax-message :country-code="donation.country"></tax-message>
				<we-do-not-sell-text></we-do-not-sell-text>
				<more-info-links text-class="combo-wiki__link-container"></more-info-links>
				<loading-spinner></loading-spinner>
				<error-display></error-display>

				<!-- Recurring Convert Modal -->
				<recurring-convert
					v-if="appState.showRecurringConvert.value"
					:donation="donation"
					:language="params.language || 'en'"
					:order-id="params.order_id || ''"
					:utm-token="params.utm_token || ''"
					:charged-amount="Number( chargedDonation.amount ) || null"
					:thank-you-url="thankYouUrl"
					@close="redirectTargetUrl"
					@recurring-convert-submit="submitPreModalDonation"
				></recurring-convert>
			</section>
			<aside class="combo-wiki__appeal">
				<template v-if="showDebug">
					<p>
						Debug - Frequency: {{ donation.frequency || "nothing yet" }} / {{ donation.currency }} {{ donation.amount || "no amount" }} / Fee:
						{{ feeAmount }} / Email Opt-in:{{ donation.optIn }} / Payment Method:
						{{ donation.paymentMethod }} / Employer: {{ donation.employer }} / Gateway: {{ donation.gateway }}
					</p>
					<p> Debug - Donation: {{ donation }}</p>
					<p> Debug Request Params - {{ params }} </p>
				</template>
			</aside>
		</div>
	</main>
</template>

<script>
// eslint-disable-next-line no-unused-vars
/* global sms-optin */
const { defineComponent } = require( 'vue' );
const {
	CdxButton,
	CdxTextInput,
	CdxSelect,
	CdxCheckbox
} = require( '@wikimedia/codex' );
const ErrorDisplay = require( '../components/ErrorDisplay.vue' );
const FrequencySelector = require( '../components/FrequencySelector.vue' );
const PaymentMethodForm = require( '../components/PaymentMethodForm.vue' );
const OptInFieldset = require( '../components/OptIn.vue' );
const WeDoNotSellText = require( '../components/WeDoNotSellText.vue' );
const TaxMessage = require( '../components/TaxMessage.vue' );
const MoreInfoLinks = require( '../components/MoreInfoLinks.vue' );
const EmployerField = require( '../components/EmployerField.vue' );
const LoadingSpinner = require( '../components/LoadingSpinner.vue' );
const RecurringConvert = require( '../components/RecurringConvert.vue' );
const { useAppState } = require( '../composables/useAppState.js' );
const { getAmountHeading } = require( '../frequencyOptions.js' );
const { paymentMethodMap } = require( '../api.js' );

const BASE_USD_PRESETS = [ 2.75, 5, 10, 20, 30, 50, 100 ];

// Fee calculation ported from calculateFee() in the donate wiki's MediaWiki:DonationForm.js,
// so both forms suggest the same amount. Minimums are about 0.35 USD in each currency.
const FEE_MULTIPLIER = 0.04;
const DEFAULT_FEE_MINIMUM = 0.35;
const FEE_MINIMUMS = {
	DKK: 2,
	HUF: 100,
	ILS: 1.2,
	INR: 4,
	JPY: 35,
	KHR: 1000,
	MYR: 1,
	NOK: 3,
	PLN: 1.35,
	CZK: 7.5,
	RON: 1.5,
	SEK: 3,
	UAH: 10,
	ZAR: 5,
	BRL: 2,
	ARS: 415,
	CLP: 325,
	COP: 1450,
	MXN: 6.75,
	PEN: 1.28,
	UYU: 14.5
};

module.exports = exports = defineComponent( {
	name: 'PaymentForm',

	components: {
		'cdx-button': CdxButton,
		'cdx-text-input': CdxTextInput,
		'cdx-select': CdxSelect,
		'cdx-checkbox': CdxCheckbox,
		'error-display': ErrorDisplay,
		'frequency-selector': FrequencySelector,
		'payment-method-form': PaymentMethodForm,
		'we-do-not-sell-text': WeDoNotSellText,
		'tax-message': TaxMessage,
		'optin-fieldset': OptInFieldset,
		'more-info-links': MoreInfoLinks,
		'employer-field': EmployerField,
		'loading-spinner': LoadingSpinner,
		'recurring-convert': RecurringConvert
	},
	inject: [ 'params' ],
	setup() {
		const appState = useAppState();
		return { appState };
	},
	data() {
		const initialCurrency = this.params.currency || 'USD';
		const countries = this.params.wgDonationInterfaceCountries || {};
		return {
			countries,
			donation: {
				firstName: null,
				lastName: null,
				email: null,
				frequency: this.getFrequencyUnit(),
				amount: this.params.amount || null,
				currency: initialCurrency,
				payFee: this.params.pay_the_fee === '1' || false,
				phone: null,
				country: this.params.country,
				paymentMethod: this.toFormPaymentMethod( this.params.payment_method ),
				optIn: this.params.opt_in,
				employer: null,
				streetAddress: null,
				streetNumber: null,
				stateProvince: null,
				city: null,
				postalCode: null,
				fiscalNumber: null,
				employerId: 0,
				smsOptin: null,
				gateway: this.params.gateway || null,
				variant: this.params.variant || null,
				gateway_session_id: this.params.gateway_session_id || null
			},
			thankYouUrl: null,
			showDebug: false
		};
	},
	computed: {
		amountHeading() {
			return getAmountHeading( this.donation.frequency );
		},
		showSmsOptin() {
			return this.showField( 'sms_opt_in' ) && this.showField( 'phone' );
		},
		presetAmounts() {
			const rates = this.params.wgDonationInterfaceCurrencyRates || {};
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
		suggestedFee() {
			const amount = Number( this.donation.amount );
			if ( !( amount > 0 ) ) {
				return null;
			}

			const minimum = FEE_MINIMUMS[ this.donation.currency ] || DEFAULT_FEE_MINIMUM;
			const fee = Math.max( amount * FEE_MULTIPLIER, minimum );
			// Currencies with no minor unit, from SmashPig's CurrencyRoundingHelper.
			const noDecimalCurrencies = this.params.DonationInterfaceNoDecimalCurrencies || [];
			if ( noDecimalCurrencies.includes( this.donation.currency ) ) {
				return Math.round( fee );
			}
			return Math.round( fee * 100 ) / 100;
		},
		maxLocal() {
			const rules = this.params.wgDonationInterfaceAmountRules;
			if ( !rules || !rules.max ) {
				return Infinity;
			}
			const rates = this.params.wgDonationInterfaceCurrencyRates || {};
			if ( this.donation.currency === rules.currency || !rates[ this.donation.currency ] ) {
				return rules.max;
			}
			return ( rules.max / rates[ rules.currency ] ) * rates[ this.donation.currency ];
		},
		canCoverFee() {
			// Like the donate wiki, don't offer the fee when it would push the donation over the maximum
			return !!this.suggestedFee &&
				Number( this.donation.amount ) + this.suggestedFee <= this.maxLocal;
		},
		feeAmount() {
			if ( !this.donation.payFee || !this.canCoverFee ) {
				return 0;
			}

			return this.suggestedFee;
		},
		chargedDonation() {
			// What the payment methods charge, which includes the fee when the donor opts in
			// Don't recompute if fee already computed from source (e.g banner)
			if ( !this.feeAmount || this.params.pay_the_fee ) {
				return this.donation;
			}
			const total = Math.round( ( Number( this.donation.amount ) + this.feeAmount ) * 100 ) / 100;
			return Object.assign( {}, this.donation, { amount: total } );
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
		/**
		 * Maps a WMF payment method code (cc, dd, apple, ...) to the PaymentMethodForm config key
		 * (card, ach, applepay, ...), but only when the server offers it on the selected gateway.
		 *
		 * @param {string|null} wmfMethod WMF payment method code, e.g. from the payment_method param.
		 * @return {string|null} The PaymentMethodForm key, or null if the method isn't active.
		 */
		toFormPaymentMethod( wmfMethod ) {
			const gateway = this.params.gateway;
			const isActive = ( this.params.paymentMethods || [] ).some(
				( entry ) => entry.method === wmfMethod && entry.gateway === gateway
			);
			if ( !wmfMethod || !isActive ) {
				return null;
			}

			const candidates = Object.keys( paymentMethodMap )
				.filter( ( key ) => paymentMethodMap[ key ].method === wmfMethod );
			// Gateway-specific keys are prefixed (adyen_card), the gravy ones are not (card)
			return candidates.find( ( key ) => key.startsWith( gateway + '_' ) ) ||
				candidates.find( ( key ) => !key.includes( '_' ) ) ||
				null;
		},
		getFrequencyUnit() {
			if ( !this.params.recurring ) {
				return 'once';
			}
			return this.params.frequency_unit;
		},
		showField( name ) {
			return [ true, 'optional' ].includes( ( ( this.params.DonationInterfaceFormFields || {} ).shared || {} )[ name ] );
		},
		isFieldRequired( name ) {
			return ( ( this.params.DonationInterfaceFormFields || {} ).shared || {} )[ name ] === true;
		},
		selectAmount( value ) {
			this.donation.amount = value;
		},
		redirectTargetUrl( targetUrl ) {
			window.location.assign(
				targetUrl ||
					this.thankYouUrl ||
					this.params.DonationInterfaceThankYouPage
			);
		},
		onCountryChange( country ) {
			const countryConfig = this.countries[ country ];
			if ( !countryConfig ) {
				return;
			}
			this.donation.currency = countryConfig.currency || 'USD';

			const url = new URL( window.location.href );
			url.searchParams.set( 'country', this.countries[ country ].value );
			window.location.assign( url.toString() );
		},
		handleDonateResult( result ) {
			const response = result.result;
			if ( response.isFailed ) {
				// do we want this to appear here or to pass it through
				this.appState.setError( mw.html.escape( this.params.order_id ) + ' ' + this.$i18n( 'combowiki-payment-failed' ).text() );
				this.appState.setLoading( false );
				return;
			}
			if ( response.errors ) {
				// do we want this to appear here or is this mid flow
				this.appState.setError( mw.html.escape( this.params.order_id ) + ' ' + this.$i18n( 'combowiki-payment-incomplete' ).text() );
				this.appState.setLoading( false );
				return;
			}

			// Store backend-generated Thank-You page URL for modal usage
			this.thankYouUrl = response.thankYouPage || response.redirect;

			if ( this.donation.frequency === 'once' && !response.redirect ) {
				this.appState.setShowRecurringConvert( true );
			} else {
				this.redirectTargetUrl( response.redirect );
			}
		},
		handleDonateError( code, failure ) {
			this.appState.setLoading( false );
			// Override the default error message if the type of error is fixable by donors taking
			// a specific action. These errors should be specific and leave zero ambiguity.
			if ( code && code.type === 'validation' ) {
				this.appState.setError( code.messages.join( ' ' ) );
				return;
			}
			this.appState.setError( mw.html.escape( this.params.order_id ) + ' ' + this.$i18n( 'combowiki-payment-failed' ).text() );
			mw.log.error( 'di_donate_' + this.donation.gateway + ' failed', code, failure );
		},
		/**
		 * When the amount, frequency and payment method all came prefilled (e.g. from the banner),
		 * scroll to and focus the first element marked data-autoscroll that still needs the donor.
		 * A marked input, select or textarea counts while it is empty. Any other marked element,
		 * e.g. a wallet button or a container holding iframe card fields, always counts.
		 */
		scrollToFirstEmptyField() {
			const { amount, frequency, paymentMethod } = this.donation;
			if ( !amount || !frequency || !paymentMethod ) {
				return;
			}

			const isFormField = ( el ) => [ 'INPUT', 'SELECT', 'TEXTAREA' ].includes( el.tagName );
			// Elements come back in page order, so the first match is the highest one on the page
			const target = Array.from( this.$el.querySelectorAll( '[data-autoscroll]' ) )
				.find( ( el ) => ( !isFormField( el ) || el.value === '' ) &&
					!el.disabled &&
					// Ignore elements that are not rendered (display: none or inside a hidden parent)
					el.getClientRects().length > 0
				);
			console.log( target );
			if ( !target ) {
				return;
			}
			target.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			// The scroll above already brings it into view, don't let focus jump the page.
			// Containers can't take focus, so this only does something for inputs and buttons.
			target.focus( { preventScroll: true } );
		},
		submitPreModalDonation( updatedDonation ) {
			const api = require( '../api.js' );
			const { toRaw } = require( 'vue' );

			if ( updatedDonation ) {
				if ( updatedDonation.frequency !== this.donation.frequency ) {
					// A monthly ask is charged exactly as shown in the modal, like the post donation convert
					updatedDonation.payFee = false;
				}
				Object.assign( this.donation, updatedDonation );
			}

			this.appState.setLoading( true );
			api.submitDonation( toRaw( this.chargedDonation ) )
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
		// ?debug=1 shows the donation and params dump below the form, for local testing
		this.showDebug = urlParams.get( 'debug' ) === '1';
		if ( urlParams.get( 'debugMonthlyConvert' ) === '1' ) {
			this.appState.setShowRecurringConvert( true );
		}
		if ( this.params.monthlyConvertReturn ) {
			// Back on Special:Donate after a one-time donation that went through a
			// redirect such as 3DS. The donation is complete, so offer monthly convert.
			Object.assign( this.donation, {
				amount: this.params.amount,
				currency: this.params.currency,
				country: this.params.country,
				paymentMethod: this.params.payment_method,
				gateway: this.params.gateway
			} );
			this.thankYouUrl = this.params.DonationInterfaceThankYouPage;
			this.appState.setShowRecurringConvert( true );
		}
		// for debugging errors
		if ( urlParams.get( 'debugError' ) ) {
			this.appState.setError( mw.html.escape( this.params.order_id ) + ' ' + this.$i18n( 'combowiki-payment-failed' ).text() );
		}
		// DonateGatewayResult sends the donor back here with paymentFailed=1 when a
		// payment that went through a redirect (such as 3DS) fails. Show the error
		// once and remove the flag, so a reload or Try Again gives a clean form.
		if ( urlParams.get( 'paymentFailed' ) === '1' ) {
			this.appState.setError( this.$i18n( 'combowiki-payment-failed' ).text() );
			const url = new URL( window.location.href );
			url.searchParams.delete( 'paymentFailed' );
			window.history.replaceState( {}, '', url );
		}

		// Wait for the prefilled payment method's form to render before looking for empty fields
		// The SMS opt-in fields load as an async component and call this once they're in
		// the page, so the scroll can't land on a field below them before they appear
		if ( !this.showSmsOptin ) {
			this.$nextTick( () => this.scrollToFirstEmptyField() );
		}
	}
} );
</script>
