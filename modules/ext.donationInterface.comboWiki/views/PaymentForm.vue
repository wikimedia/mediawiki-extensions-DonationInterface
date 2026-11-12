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
				<div class="fieldset">
					<frequency-selector v-model="donation.frequency"></frequency-selector>
				</div>

				<!-- Amount Selector -->
				<amount-selector
					v-model="donation.amount"
					:country="donation.country"
					:pay-fee="donation.payFee"
					:frequency="donation.frequency"
					:currency="donation.currency"
					:countries="countries"
					:messages="messages.amount"
					:status="status.amount"
					@on-change="( val ) => onInput( 'amount' )"
					@update:pay-fee="donation.payFee = $event"
					@update:country="onCountryChange"
				></amount-selector>
				<!-- Email opt-in -->
				<optin-fieldset
					v-if="showField( 'opt_in' )"
					v-model="donation.optIn"
					:messages="messages.optIn"
					:status="status.optIn"
					@on-change="( val ) => onInput( 'optIn' )"
				></optin-fieldset>
				<!-- Employer -->
				<employer-field
					v-if="showField( 'employer' )"
					v-model:employer="donation.employer"
					v-model:employer-id="donation.employerId"
					:is-required="isFieldRequired( 'employer' )"
				></employer-field>

				<!-- Sms Optin: rendered with :is so Vue never looks up an unregistered
					sms-optin tag on forms without the smsOptin variant -->
				<component
					:is="variantComponents.smsOptin"
					v-if="showSmsOptin && variantComponents.smsOptin"
					v-model:phone="donation.phone"
					v-model:sms-optin="donation.smsOptin"
					:is-required="isFieldRequired( 'sms_opt_in' ) && isFieldRequired( 'phone' )"
				></component>

				<!-- Hidden when returning to offer monthly convert: mounting the card form
					would create a new checkout session for a donation that is already made -->
				<payment-method-form
					v-if="!params.monthlyConvertReturn"
					:donation="chargedDonation"
					:before-select="() => ( validateAll() ).length === 0"
					@donation-success="handleDonateResult"
					@donation-error="handleDonateError"
					@on-payment-method-change="( method ) => {
						donation.paymentMethod = method
					}"
					@on-donor-details-change="updateDonorDetails"
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
			<component :is="appeal" v-if="appeal"></component>
		</div>
	</main>
	<div v-if="showDebug" class="combo-wiki__debug">
		<pre>
Debug -
Frequency: {{ donation.frequency || "nothing yet" }} / {{ donation.currency }} {{ donation.amount || "no amount" }} /
Fee: {{ feeAmount }} /
Email Opt-in:{{ donation.optIn }} /
Payment Method: {{ donation.paymentMethod }} /
Employer: {{ donation.employer }}</pre>
		<pre> Debug - Donation: {{ donation }}</pre>
		<pre> Debug Request Params - {{ params }} </pre>
	</div>
</template>

<script>
const { defineComponent, computed, toRaw, inject, reactive } = require( 'vue' );
const { useFieldValidation } = require( '../composables/useFieldValidation.js' );
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
const AmountSelector = require( '../components/AmountSelector.vue' );
const { getAppeal } = require( '../appeals.js' );
const { getFeeAmount, minAmountFunction, getFormattedAmount, getPresetAmounts } = require( '../amountHelper.js' );
const { useAppState } = require( '../composables/useAppState.js' );
const { paymentMethodMap, donateParamMap, getGateway } = require( '../api.js' );

module.exports = exports = defineComponent( {
	name: 'PaymentForm',

	components: {
		'error-display': ErrorDisplay,
		'frequency-selector': FrequencySelector,
		'amount-selector': AmountSelector,
		'payment-method-form': PaymentMethodForm,
		'we-do-not-sell-text': WeDoNotSellText,
		'tax-message': TaxMessage,
		'optin-fieldset': OptInFieldset,
		'more-info-links': MoreInfoLinks,
		'employer-field': EmployerField,
		'loading-spinner': LoadingSpinner,
		'recurring-convert': RecurringConvert
	},
	inject: {
		params: { from: 'params' },
		variantComponents: { from: 'variantComponents', default: () => ( {} ) }
	},
	setup() {
		const params = inject( 'params' );
		const initialCurrency = params.currency || 'USD';
		const countries = params.wgDonationInterfaceCountries || {};
		const defaultCountry = params.country || 'US';
		function showField( name ) {
			return [ true, 'optional' ].includes( ( ( params.DonationInterfaceFormFields || {} ).shared || {} )[ name ] );
		}

		function getFrequencyUnit() {
			if ( !params.recurring ) {
				return 'once';
			}
			return params.frequency_unit;
		}
		/**
		 * Maps a WMF payment method code (cc, dd, apple, ...) to the PaymentMethodForm config key
		 * (card, adyen_card, ach, applepay, ...), but only when the server offers it on the
		 * gateway of that key.
		 *
		 * @param {string|null} wmfMethod WMF payment method code, e.g. from the payment_method param.
		 * @return {string|null} The PaymentMethodForm key, or null if the method isn't active.
		 */
		function toFormPaymentMethod( wmfMethod ) {
			const offeredGateways = ( params.paymentMethods || [] )
				.filter( ( entry ) => entry.method === wmfMethod )
				.map( ( entry ) => entry.gateway );
			if ( !wmfMethod || offeredGateways.length === 0 ) {
				return null;
			}
			return Object.keys( paymentMethodMap ).find( ( key ) => {
				const mapping = paymentMethodMap[ key ];
				return mapping.method === wmfMethod && offeredGateways.includes( mapping.gateway );
			} ) || null;
		}

		const donation = reactive( {
			firstName: null,
			lastName: null,
			email: null,
			frequency: getFrequencyUnit(),
			amount: params.amount || null,
			currency: initialCurrency,
			payFee: params.pay_the_fee === '1' || false,
			phone: null,
			country: defaultCountry,
			paymentMethod: toFormPaymentMethod( params.payment_method ),
			optIn: params.opt_in,
			employer: null,
			streetAddress: null,
			streetNumber: null,
			stateProvince: null,
			city: null,
			postalCode: null,
			fiscalNumber: null,
			employerId: 0,
			smsOptin: null,
			variant: params.variant || null,
			gateway_session_id: params.gateway_session_id || null
		} );
		const presetAmounts = computed( () => {
			const rates = params.wgDonationInterfaceCurrencyRates || {};
			const currency = donation.currency;
			return getPresetAmounts( currency, rates );
		} );
		const minAmount = computed( () => {
			const rules = params.wgDonationInterfaceAmountRules;
			const rates = params.wgDonationInterfaceCurrencyRates || {};
			return minAmountFunction( rules, rates, donation.currency, presetAmounts.value );
		} );
		const formattedAmount = computed( () => getFormattedAmount( minAmount.value, params.language, donation.currency, donation.country ) );

		const appState = useAppState();
		const validationRules = {
			amount: [
				{
					required: true,
					message: mw.msg( 'combowiki-error-select-amount', formattedAmount.value )
				},
				{
					callback: () => !( Number( donation.amount ) > 0 ) || Number( donation.amount ) < Number( minAmount.value ),
					message: mw.msg( 'combowiki-error-select-amount', formattedAmount.value )
				}
			]
		};
		if ( showField( 'opt_in' ) ) {
			validationRules.optIn = [
				{
					required: true,
					message: mw.message( 'combowiki-error-select-email-option' ).text()
				}
			];
		}
		const { validateAll, status, messages, onInput } = useFieldValidation( validationRules, donation );
		return {
			appState,
			validateAll,
			countries,
			donation,
			messages,
			status,
			onInput,
			showField,
			toFormPaymentMethod
		};
	},
	data() {
		return {
			thankYouUrl: null,
			showDebug: false
		};
	},
	computed: {
		appeal() {
			return getAppeal( this.params.appeal );
		},
		showSmsOptin() {
			return this.showField( 'sms_opt_in' ) && this.showField( 'phone' );
		},
		feeAmount() {
			return getFeeAmount( this.donation, this.params );
		},
		chargedDonation() {
			// What the payment methods charge, which includes the fee when the donor opts in.
			// Computed from the donation itself, so a change made just before submitting
			// (e.g. the monthly convert modal) is charged as shown.
			// Don't recompute if fee already computed from source (e.g banner)
			if ( !this.feeAmount || this.params.pay_the_fee ) {
				return this.donation;
			}
			const total = Math.round( ( Number( this.donation.amount ) + this.feeAmount ) * 100 ) / 100;
			return Object.assign( {}, this.donation, { amount: total } );
		}
	},

	methods: {
		isFieldRequired( name ) {
			return ( ( this.params.DonationInterfaceFormFields || {} ).shared || {} )[ name ] === true;
		},
		/**
		 * Copies what the donor typed in the details form into the donation.
		 *
		 * @param {Object} details Keyed by server field name, e.g. state_province
		 */
		updateDonorDetails( details ) {
			for ( const [ fieldName, value ] of Object.entries( details ) ) {
				this.donation[ donateParamMap[ fieldName ] ] = value;
			}
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

			const previousCurrency = this.donation.currency;
			this.donation.currency = countryConfig.currency || 'USD';

			const url = new URL( window.location.href );
			url.searchParams.set( 'country', this.countries[ country ].value );
			url.searchParams.set( 'currency', this.donation.currency );
			this.keepFrequencyInUrl( url );
			this.keepAmountInUrl( url, previousCurrency );
			this.redirectTargetUrl( url.toString() );
		},
		/**
		 * Puts the chosen frequency in the URL so the reloaded page selects it again.
		 * A one time gift sets recurring=0 rather than removing it, because Special:Donate
		 * fills a missing recurring from the session, e.g. from an earlier monthly banner.
		 *
		 * @param {URL} url
		 */
		keepFrequencyInUrl( url ) {
			const frequency = this.donation.frequency;
			if ( frequency === 'once' ) {
				url.searchParams.set( 'recurring', '0' );
				url.searchParams.delete( 'frequency_unit' );
			} else if ( frequency ) {
				url.searchParams.set( 'recurring', '1' );
				url.searchParams.set( 'frequency_unit', frequency );
			}
		},
		/**
		 * Keeps the amount when the new country uses the same currency, e.g. Italy to Spain.
		 * In a different currency the old number means a different gift, so the donor picks again.
		 *
		 * @param {URL} url
		 * @param {string} previousCurrency
		 */
		keepAmountInUrl( url, previousCurrency ) {
			if ( this.donation.currency === previousCurrency && Number( this.donation.amount ) > 0 ) {
				url.searchParams.set( 'amount', this.donation.amount );
			} else {
				url.searchParams.delete( 'amount' );
			}
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
				// Payment method components should handle responses with formData
				// To prevent two redirects racing each other
				const hasFormData = response.formData && Object.keys( response.formData ).length > 0;
				if ( !hasFormData ) {
					this.redirectTargetUrl( response.redirect );
				}

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
			let errorMessage = this.$i18n( 'combowiki-payment-failed' ).text();
			if ( code && code.type && code.message ) {
				errorMessage = code.type + ': ' + code.message;
			}
			this.appState.setError( mw.html.escape( this.params.order_id ) + ' ' + errorMessage );
			mw.log.error( 'di_donate_' + getGateway( this.donation.paymentMethod ) + ' failed', code, failure );
		},
		submitPreModalDonation( updatedDonation ) {
			const api = require( '../api.js' );

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
				paymentMethod: this.toFormPaymentMethod( this.params.payment_method )
			} );
			this.thankYouUrl = this.params.DonationInterfaceThankYouPage;
			this.appState.setShowRecurringConvert( true );
		}
		// for debugging errors
		if ( urlParams.get( 'debugError' ) ) {
			this.appState.setError( mw.html.escape( this.params.order_id ) + ' ' + this.$i18n( 'combowiki-payment-failed' ).text() );
		}
		// for debugging the loading spinner
		if ( urlParams.get( 'debugLoading' ) ) {
			this.appState.setLoading( true );
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
	}
} );
</script>
