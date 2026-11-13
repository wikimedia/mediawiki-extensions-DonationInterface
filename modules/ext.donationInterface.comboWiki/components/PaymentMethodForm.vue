<template>
	<!-- Payment method form component: the method picker, or that method's form
	with a link back to the picker if a method is already selected -->
	<div ref="root" class="fieldset">
		<transition
			name="combo-wiki-swap"
			mode="out-in"
			@after-enter="onSwapped"
		>
			<div
				v-if="!isBoxed( paymentMethod )"
				key="picker"
				class="payment-form__methods"
			>
				<!-- Instant methods (wallets, PayPal) first, then the others (cards, bank) -->
				<div
					v-for="( methods, group ) in paymentMethodGroups"
					v-show="methods.length"
					:key="group"
					class="fieldset payment-form__method-group"
					:class="'payment-form__method-group--' + group"
				>
					<!-- "Or donate with" only makes sense after the instant methods -->
					<div
						v-if="group === 'instant' || paymentMethodGroups.instant.length"
						class="fieldset__label"
					>
						<p class="text text--base">
							<strong>{{ methodGroupLabels[group] }}</strong>
						</p>
					</div>
					<!-- No form fields to fill in: show the method's own button so one click submits.
						Hidden, but laid out so the SDKs can draw into it, until every button is ready. -->
					<div
						v-if="group === 'instant'"
						id="ready-to-submit-methods"
						class="payment-form__method-buttons combo-wiki__payment-method-buttons"
						:class="{
							'combo-wiki__direct-methods--disabled': disabled,
							'combo-wiki__payment-method-buttons--loading': !buttonsShown
						}"
						:inert="disabled || !buttonsShown"
						:aria-busy="!buttonsShown"
					>
						<!-- The click is captured to select the method before the button submits -->
						<div
							v-for="method in methods.filter( ( m ) => !isBoxed( m ) )"
							:key="method"
							:class="{ 'combo-wiki__option--selected': donation.paymentMethod === method }"
							@click.capture="selectPaymentMethod( method, $event )"
						>
							<component
								:is="paymentMethodConfig[method].component"
								:donation="donation"
								:payment-method="method"
								@submit="( ...args ) => submitDonation( method, ...args )"
								@error="( ...args ) => onButtonError( method, ...args )"
								@presubmit="paymentMethodConfig[method].presubmit"
								@ready="markButtonReady( method )"
							>
							</component>
						</div>
					</div>
					<!-- Methods with a form open it in the selected view -->
					<div class="payment-form__method-buttons">
						<cdx-button
							v-for="method in methods.filter( isBoxed )"
							:key="method"
							:data-method="method"
							:disabled="disabled"
							@click="chooseMethod( method )"
						>
							{{ paymentMethodConfig[method].label }}
						</cdx-button>
					</div>
				</div>
			</div>
			<div
				v-else
				key="selected"
				class="fieldset payment-form__selected"
			>
				<div class="fieldset__label">
					<p class="text text--base">
						<strong class="payment-form__selected-heading" tabindex="-1">
							{{ selectedHeading }}
						</strong>
					</p>
					<button
						type="button"
						class="combo-wiki__change-method"
						:disabled="disabled"
						@click="changeMethod"
					>
						{{ $i18n( 'combowiki-change-payment-method' ).text() }}
					</button>
				</div>
				<!-- Additional form fields for payment method -->
				<div id="select-method-and-fillout-form" class="combo-wiki__method-form">
					<div class="combo-wiki__method-form-body">
						<!-- Main shared payment method form fields -->
						<payment-method-form-fields
							v-if="hasFormFields( paymentMethod )"
							:key="paymentMethod"
							ref="donorFields"
							:donation="donation"
							:form-fields="paymentMethodConfig[paymentMethod].formFields"
							:validation-rules="paymentMethodConfig[paymentMethod].validationRules"
							@update:details="( details ) => $emit( 'onDonorDetailsChange', details )"
							@update:valid="( valid ) => { donorDetails.valid = valid; }"
						></payment-method-form-fields>

						<component
							:is="paymentMethodConfig[paymentMethod].component"
							v-if="submitSlot"
							:donation="donation"
							:payment-method="paymentMethod"
							:donor-details="hasFormFields( paymentMethod ) ? donorDetails : null"
							@submit="( ...args ) => submitDonation( paymentMethod, ...args )"
							@error="paymentMethodConfig[paymentMethod].error"
							@presubmit="paymentMethodConfig[paymentMethod].presubmit"
						></component>
					</div>
				</div>
				<!-- The method's submit button, outside the box -->
				<div
					ref="submitSlot"
					class="combo-wiki__method-submit"
				></div>
			</div>
		</transition>
	</div>
</template>

<script>
const { defineComponent, toRaw, computed, inject, onBeforeUnmount, onMounted, reactive, ref, provide, watch } = require( 'vue' );
const { CdxButton } = require( '@wikimedia/codex' );
const api = require( '../api.js' );
const { overrideWithFormEntries } = require( '../utils.js' );
const { isApplePayAvailable, isGooglePayAvailable, isAdyenApplePayAvailable } = require( '../walletAvailability.js' );
const PaymentMethodFormFields = require( './PaymentMethodFormFields.vue' );
const GravyCardForm = require( './GravyCardForm.vue' );
const GravyRedirectComponent = require( './GravyRedirectComponent.vue' );
const ApplePayComponent = require( './ApplePayComponent.vue' );
const ACHComponent = require( './ACHComponent.vue' );
const AdyenCardForm = require( './AdyenCardForm.vue' );
const GooglePayComponent = require( './GooglePayComponent.vue' );
const SEPAComponent = require( './SEPAComponent.vue' );
const AdyenSEPA = require( './AdyenSEPA.vue' );
const BraintreePayPal = require( './BraintreePayPal.vue' );
const BraintreeVenmo = require( './BraintreeVenmo.vue' );
const AdyenApplePayComponent = require( './AdyenApplePayComponent.vue' );
const { useAppState } = require( '../composables/useAppState.js' );

// How long the one-click buttons wait for the slowest wallet before showing anyway
const BUTTONS_SHOWN_TIMEOUT_MS = 3000;

module.exports = exports = defineComponent( {
	name: 'PaymentMethodSelector',
	components: {
		'cdx-button': CdxButton,
		'payment-method-form-fields': PaymentMethodFormFields,
		'card-form': GravyCardForm,
		'paypal-form': GravyRedirectComponent,
		'venmo-form': GravyRedirectComponent,
		'applepay-form': ApplePayComponent,
		'googlepay-form': GooglePayComponent,
		'ach-form': ACHComponent,
		'adyen-card-form': AdyenCardForm,
		'braintree-paypal-form': BraintreePayPal,
		'braintree-venmo-form': BraintreeVenmo,
		'sepa-form': SEPAComponent,
		'adyen-sepa-form': AdyenSEPA,
		'adyen-applepay-form': AdyenApplePayComponent
	},
	props: {
		donation: {
			type: Object,
			required: true
		},
		disabled: {
			type: Boolean,
			default: false
		},
		beforeSelect: {
			type: Function,
			default: null
		}
	},
	emits: [ 'donationSuccess', 'donationError', 'onPaymentMethodChange', 'onDonorDetailsChange' ],

	setup( props, ctx ) {
		const appState = useAppState();
		const params = inject( 'params' );
		const formFieldsForMethod = ( params.DonationInterfaceFormFields || {} ).method || {};
		const rulesForMethod = ( params.DonateValidationRules || {} ).method || {};

		/**
		 * @param {string} paymentMethod ComboWiki payment option as defined in paymentMethodConfig
		 * @return {Object} The form fields for that payment method, filtered by country
		 */
		const getFormFieldsFor = ( paymentMethod ) => {
			const mapping = api.paymentMethodMap[ paymentMethod ];
			return ( mapping && formFieldsForMethod[ mapping.submethod || mapping.method ] ) || {};
		};

		/**
		 * @param {string} paymentMethod ComboWiki payment option as defined in paymentMethodConfig
		 * @return {Object} The validation rules for that payment method's fields
		 */
		const getValidationRulesFor = ( paymentMethod ) => {
			const mapping = api.paymentMethodMap[ paymentMethod ];
			return ( mapping && rulesForMethod[ mapping.submethod || mapping.method ] ) || {};
		};

		// The selected form method's details form, rendered once above the method's component
		const donorFields = ref();
		/**
		 * What the selected form method's component knows of the details form, passed as its
		 * donorDetails prop. One-click methods don't get it, as they have no details form.
		 *
		 * @property {boolean} valid Whether the details are valid, kept up to date by the form
		 * @property {Function} validate Shows the errors and returns whether the details are valid;
		 *  called by the component before it submits or opens a payment sheet
		 * @property {Function} getValues The details keyed by server field name
		 */
		const donorDetails = reactive( {
			valid: true,
			validate: () => !donorFields.value || donorFields.value.validate(),
			getValues: () => ( donorFields.value ? donorFields.value.getValues() : {} )
		} );

		/**
		 * Submits the donation via the API and emits donationSuccess/donationError with the result.
		 *
		 * @param {string} paymentMethod The submitting method's key, as props.donation may not have it yet
		 * @param {Object} [payload] Extra payment-method-specific data to merge into the donation params.
		 * @param {Function} [successCallback] Called with the API result on success.
		 * @param {Function} [errorCallback] Called with the failure details on error.
		 */
		const submitDonation = ( paymentMethod, payload = {}, successCallback = null, errorCallback = null ) => {
			if ( props.beforeSelect && !props.beforeSelect() ) {
				// block the card payment
				return;
			}
			if ( hasFormFields( paymentMethod ) ) {
				const formEntries = donorDetails.getValues();
				payload = overrideWithFormEntries( payload, formEntries );
			}
			appState.setLoading( true );
			api.submitDonation( Object.assign( {}, toRaw( props.donation ), { paymentMethod } ), payload )
				.then( ( result ) => {
					let isFailed = false;
					// how do we want to display validation errors
					if ( result.result.isFailed || result.result.errors ) {
						isFailed = true;
						ctx.emit( 'donationError', result.result.errors );
						if ( errorCallback ) {
							errorCallback( { errors: result.result.errors } );
						}
					}
					if ( !isFailed ) {
						if ( successCallback ) {
							successCallback( result );
						}
						ctx.emit( 'donationSuccess', result );
					}
					appState.setLoading( false );
				} )
				.catch( ( code, failure ) => {
					ctx.emit( 'donationError', code, failure );
					if ( errorCallback ) {
						errorCallback( failure );
						// what should happen here, need to handle it properly
					}
					appState.setLoading( false );
				} );
		};

		/**
		 * Validates the Apple Pay merchant session with the API before the payment sheet can proceed.
		 *
		 * @param {Object} event The Apple Pay merchant validation event.
		 * @param {Function} successCallback Called with the validated session data.
		 * @param {Function} failureCallback Called with the error when validation fails.
		 */
		const validateApplePay = ( event, successCallback, failureCallback ) => {
			appState.setLoading( true );
			const payload = Object.assign( {}, props.donation );
			payload.validationURL = event.validationURL;
			api.validateApplePayPaymentSession( payload )
				.then( ( data ) => {
					if ( data.result && data.result.errors ) {
						failureCallback( data.result.errors );
						mw.log( 'Apple Pay validation failure: ' + JSON.stringify( data.result.errors ) );
					} else {
						successCallback( data );
					}
					appState.setLoading( false );
				} ).catch( ( e ) => {
					failureCallback( e );
					console.log( 'Error', e );
					mw.log( 'Apple Pay validation failure: ' + e );
					appState.setLoading( false );
				} );
		};

		/**
		 * Creates a Gravy checkout session for the card form before the SecureFields SDK is set up.
		 *
		 * @param {Object} parameters Parameters used to create the checkout session.
		 * @param {Function} successCallback Called with the new session ID.
		 * @param {Function} failureCallback Called when session creation fails.
		 */
		const getGravyCheckoutSession = ( parameters, successCallback, failureCallback ) => {
			appState.setLoading( true );
			api.createCheckoutSession( parameters )
				.then( ( sessionId ) => {
					successCallback( sessionId );
					appState.setLoading( false );
				} )
				.catch( () => {
					failureCallback();
					appState.setLoading( false );
				} );
		};

		const getAdyenCheckoutSession = ( parameters, successCallback, failureCallback ) => {
			appState.setLoading( true );
			api.createCheckoutSession( parameters )
				.then( ( sessionId ) => {
					successCallback( sessionId );
					appState.setLoading( false );
				} )
				.catch( () => {
					failureCallback();
					appState.setLoading( false );
				} );
		};

		/**
		 * Forwards a payment-method error up to the parent as a donationError event.
		 *
		 * @param {*} reason The error/reason to forward.
		 */
		const onError = ( reason ) => {
			ctx.emit( 'donationError', reason );
		};

		/**
		 * Per-method UI and behavior configuration, keyed by payment method.
		 *
		 * @property {string} label Display text for the payment method's selector button.
		 * @property {string} component Name of the registered component that renders the method's form.
		 * @property {string} gateway Gateway for the payment method.
		 * @property {boolean} supportsOneTime Flag to show support for one time transaction (default: True).
		 * @property {boolean} supportsMonthly Flag to show support for monthly recurring transaction (default: True).
		 * @property {boolean} supportsAnnual Flag to show support for Annual recurring transaction (default: True).
		 * @property {Function} [error] Called when the payment method reports an error.
		 * @property {Function} [presubmit] Called before submit to prepare the payment method (e.g. create a session or validate).
		 * @property {Function} [isAvailable] Resolves to whether the donor's browser can use the method
		 *  (e.g. a wallet); the method is only shown once it resolves to true. Without it, always available.
		 * @property {boolean} [waitForReady] The component draws its button once an SDK has loaded,
		 *  and emits ready (or error) when it's done. The one-click buttons stay hidden until then.
		 * @property {Object} [formFields] Extra form fields to include in the payment method form, already filtered by country.
		 * @property {string} [heading] Heading shown once the method is selected.
		 *  Without it, "Donate with <label>".
		 * @property {boolean} [instant] Shown in the instant payment group (wallets, PayPal).
		 */
		const paymentMethodConfig = {
			card: {
				heading: mw.message( 'combowiki-selected-method-heading-card' ).text(),
				label: mw.message( 'combowiki-method-card' ).text(),
				component: 'card-form',
				error: onError,
				presubmit: getGravyCheckoutSession,
				formFields: getFormFieldsFor( 'card' ),
				validationRules: getValidationRulesFor( 'card' ),
				instant: false
			},
			adyen_card: {
				heading: mw.message( 'combowiki-selected-method-heading-card' ).text(),
				label: mw.message( 'combowiki-method-card' ).text(),
				component: 'adyen-card-form',
				gateway: 'adyen',
				error: onError,
				presubmit: getAdyenCheckoutSession,
				formFields: getFormFieldsFor( 'adyen_card' ),
				validationRules: getValidationRulesFor( 'adyen_card' ),
				instant: false
			},
			paypal: {
				label: mw.message( 'combowiki-method-paypal' ).text(),
				component: 'paypal-form',
				error: onError,
				formFields: getFormFieldsFor( 'paypal' ),
				validationRules: getValidationRulesFor( 'paypal' ),
				instant: true
			},
			braintree_paypal: {
				label: mw.message( 'combowiki-method-paypal' ).text(),
				component: 'braintree-paypal-form',
				waitForReady: true,
				error: onError,
				submit: submitDonation,
				formFields: getFormFieldsFor( 'braintree_paypal' ),
				validationRules: getValidationRulesFor( 'braintree_paypal' ),
				instant: true
			},
			venmo: {
				label: mw.message( 'combowiki-method-venmo' ).text(),
				component: 'venmo-form',
				error: onError,
				formFields: getFormFieldsFor( 'venmo' ),
				validationRules: getValidationRulesFor( 'venmo' ),
				instant: true
			},
			braintree_venmo: {
				label: mw.message( 'combowiki-method-venmo' ).text(),
				component: 'braintree-venmo-form',
				waitForReady: true,
				error: onError,
				submit: submitDonation,
				formFields: getFormFieldsFor( 'braintree_venmo' ),
				validationRules: getValidationRulesFor( 'braintree_venmo' ),
				instant: true
			},
			applepay: {
				label: mw.message( 'combowiki-method-applepay' ).text(),
				component: 'applepay-form',
				waitForReady: true,
				error: onError,
				presubmit: validateApplePay,
				isAvailable: () => isApplePayAvailable( params.gravyConfiguration ),
				formFields: getFormFieldsFor( 'applepay' ),
				validationRules: getValidationRulesFor( 'applepay' ),
				instant: true
			},
			ach: {
				label: mw.message( 'combowiki-method-ach' ).text(),
				component: 'ach-form',
				error: onError,
				supportsOneTime: false,
				formFields: getFormFieldsFor( 'ach' ),
				validationRules: getValidationRulesFor( 'ach' ),
				instant: false
			},
			googlepay: {
				label: mw.message( 'combowiki-method-googlepay' ).text(),
				component: 'googlepay-form',
				waitForReady: true,
				error: onError,
				isAvailable: () => isGooglePayAvailable( params.gravyConfiguration ),
				formFields: getFormFieldsFor( 'googlepay' ),
				validationRules: getValidationRulesFor( 'googlepay' ),
				instant: true
			},
			adyen_googlepay: {
				label: mw.message( 'combowiki-method-googlepay' ).text(),
				component: 'googlepay-form',
				waitForReady: true,
				submit: submitDonation,
				error: onError,
				gateway: 'adyen',
				isAvailable: () => isGooglePayAvailable( params.adyenConfiguration ),
				formFields: getFormFieldsFor( 'adyen_googlepay' ),
				validationRules: getValidationRulesFor( 'adyen_googlepay' ),
				instant: true
			},
			sepadirectdebit: {
				label: mw.message( 'combowiki-method-sepa' ).text(),
				component: 'sepa-form',
				error: onError,
				formFields: getFormFieldsFor( 'sepadirectdebit' ),
				validationRules: getValidationRulesFor( 'sepadirectdebit' )
			},
			adyen_sepadirectdebit: {
				label: mw.message( 'combowiki-method-sepa' ).text(),
				component: 'adyen-sepa-form',
				error: onError,
				gateway: 'adyen',
				formFields: getFormFieldsFor( 'sepadirectdebit' ),
				validationRules: getValidationRulesFor( 'sepadirectdebit' )
			},
			adyen_applepay: {
				label: mw.message( 'combowiki-method-applepay' ).text(),
				component: 'adyen-applepay-form',
				waitForReady: true,
				gateway: 'adyen',
				submit: submitDonation,
				error: onError,
				presubmit: validateApplePay,
				isAvailable: () => isAdyenApplePayAvailable( toRaw( props.donation ) ),
				extraFormFields: getFormFieldsFor( 'applepay' ),
				validationRules: getValidationRulesFor( 'applepay' ),
				instant: true
			}
		};

		/**
		 * @param {string} method Server payment method code
		 * @param {string} [submethod] Server payment submethod code
		 * @return {string} Lookup key for gatewayByMethod
		 */
		const methodKey = ( method, submethod ) => submethod ? method + '/' + submethod : method;

		// Server payment (sub)method => the gateway that handles it,
		// e.g. { cc: 'gravy', rtbt: 'gravy', 'rtbt/sepadirectdebit': 'gravy' }
		const gatewayByMethod = {};
		for ( const entry of params.paymentMethods ) {
			gatewayByMethod[ methodKey( entry.method, entry.submethod ) ] = entry.gateway;
		}

		/**
		 * @param {string} method Payment method key
		 * @return {boolean} Whether the server offers the method (and submethod, if the option
		 *  is one) on the gateway of the method for this country
		 */
		const isOfferedByServer = ( method ) => {
			const mapping = api.paymentMethodMap[ method ];
			return !!mapping && gatewayByMethod[ methodKey( mapping.method, mapping.submethod ) ] === mapping.gateway;
		};

		// Answers from the isAvailable checks, keyed by payment method: true once the
		// browser can use the method, false if it can't
		const availability = reactive( {} );

		/**
		 * Returns the payment methods offered for the current donation.
		 * Using computed for the caching property to ensure the list is only regenerated when
		 * the donation frequency is changed.
		 *
		 * @return {string[]} The list of available payment method keys.
		 */
		const availablePaymentMethods = computed( () => {
			const isOneTime = props.donation.frequency === 'once';
			const methods = [];
			for ( const [ method, config ] of Object.entries( paymentMethodConfig ) ) {
				if ( isOneTime && config.supportsOneTime === false ) {
					continue;
				}
				if ( !isOfferedByServer( method ) ) {
					continue;
				}
				// Not shown until its check says the browser can use it, nor after it says it can't
				if ( config.isAvailable && availability[ method ] !== true ) {
					continue;
				}
				methods.push( method );
			}
			return methods;
		} );

		/**
		 * The available payment methods split into instant ones (wallets, PayPal)
		 * and the others (cards, bank), in that display order.
		 *
		 * @return {{instant: string[], other: string[]}}
		 */
		const paymentMethodGroups = computed( () => {
			const groups = { instant: [], other: [] };
			for ( const method of availablePaymentMethods.value ) {
				groups[ paymentMethodConfig[ method ].instant ? 'instant' : 'other' ].push( method );
			}
			return groups;
		} );

		// The label shown above each group's buttons
		const methodGroupLabels = {
			instant: mw.message( 'combowiki-method-group-instant' ).text(),
			other: mw.message( 'combowiki-method-group-other' ).text()
		};

		/**
		 * @param {string} method Payment method key
		 * @return {boolean} Whether the method has form fields for the donor to fill in
		 */
		const hasFormFields = ( method ) => {
			const config = paymentMethodConfig[ method ];
			return !!config && config.formFields && Object.keys( config.formFields ).length > 0;
		};

		// A ref, so the form section updates when the selection changes here (e.g. cleared below)
		const selectedPaymentMethod = ref( props.donation.paymentMethod || '' );

		/**
		 * Sets the active payment method and notifies the parent via onPaymentMethodChange.
		 *
		 * @param {string} method The selected payment method key.
		 * @param {Event} [event] The native click event, used to halt propagation if blocked.
		 */
		const selectPaymentMethod = ( method, event = null ) => {
			if ( props.beforeSelect && !props.beforeSelect() ) {
				if ( event ) {
					event.stopPropagation();
					event.stopImmediatePropagation();
					event.preventDefault();
				}
				return;
			}
			selectedPaymentMethod.value = method;
			ctx.emit( 'onPaymentMethodChange', method );
			// Drop donation fields values if not needed in the newly selected method
			const thisMethodFormFields = method ? paymentMethodConfig[ method ].formFields : {};
			const formFieldsToRemove = {};
			const supportedMethodFieldNames = Object.keys( api.donateParamMap );
			// Don't bother checking if method has no form fields
			if ( thisMethodFormFields ) {
				for ( const fieldName of supportedMethodFieldNames ) {
					if ( !( fieldName in thisMethodFormFields ) ) {
						formFieldsToRemove[ fieldName ] = undefined;
					}
				}
			}
			ctx.emit( 'onDonorDetailsChange', formFieldsToRemove );
		};

		/**
		 * @param {string} method Payment method key
		 * @return {boolean} Whether the method's form goes in a bordered box. Only an
		 *  instant method with no fields to fill in (just its button) goes without.
		 */
		const isBoxed = ( method ) => {
			const config = paymentMethodConfig[ method ];
			return !!config && ( !config.instant || hasFormFields( method ) );
		};

		// Where a boxed method's submit button goes, below the box (see MethodSubmit).
		// Null for a method without a box, whose button stays in place.
		const submitSlot = ref( null );
		provide( 'methodSubmitTarget', computed( () => (
			isBoxed( selectedPaymentMethod.value ) ? submitSlot.value : null
		) ) );

		// Heading of the selected method's view, e.g. "Donate by credit or debit card"
		const selectedHeading = computed( () => {
			const config = paymentMethodConfig[ selectedPaymentMethod.value ];
			if ( !config ) {
				return '';
			}
			return config.heading ||
				mw.message( 'combowiki-selected-method-heading', config.label ).text();
		} );

		// Where focus goes once the picker and the selected view have swapped:
		// the heading after choosing a method, or the button of the method that was selected
		const root = ref();
		let focusAfterSwap = null;

		/**
		 * @param {string} method The payment method key the donor picked
		 */
		const chooseMethod = ( method ) => {
			focusAfterSwap = '.payment-form__selected-heading';
			selectPaymentMethod( method );
		};

		// Back to the picker, keeping what the donor entered in the shared donation
		// Fields are only dropped once the donor picks the next method (see selectPaymentMethod)
		const changeMethod = () => {
			focusAfterSwap = '[data-method="' + selectedPaymentMethod.value + '"]';
			selectedPaymentMethod.value = '';
			ctx.emit( 'onPaymentMethodChange', '' );
		};

		/**
		 * Once the new view has faded in, moves focus to it so keyboard and screen
		 * reader users carry on from there, and scrolls it into view if it isn't.
		 */
		const onSwapped = () => {
			const target = focusAfterSwap && root.value && root.value.querySelector( focusAfterSwap );
			focusAfterSwap = null;
			if ( !target ) {
				return;
			}
			const reduceMotion = window.matchMedia &&
				window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
			target.focus( { preventScroll: true } );
			target.scrollIntoView( { block: 'nearest', behavior: reduceMotion ? 'auto' : 'smooth' } );
		};

		/**
		 * Never offers a method the donor's browser can't use: no button, no form and
		 * no error. If the donor had selected it (e.g. from the URL), clears the selection
		 * so they pick another method. Not card on their behalf: it may be before they
		 * chose an amount, which the card form needs to create its checkout session.
		 *
		 * @param {string} method The payment method key.
		 */
		const markUnavailable = ( method ) => {
			availability[ method ] = false;
			if ( selectedPaymentMethod.value === method ) {
				selectPaymentMethod( '' );
			}
		};

		// One-click methods with waitForReady whose component said its button is drawn, or
		// that it failed (an error means there is nothing more to wait for)
		const readyButtons = reactive( {} );

		/**
		 * @param {string} method Payment method key
		 */
		const markButtonReady = ( method ) => {
			readyButtons[ method ] = true;
		};

		/**
		 * @param {string} method Payment method key
		 * @param {...*} args What the component emitted with the error
		 */
		const onButtonError = ( method, ...args ) => {
			markButtonReady( method );
			paymentMethodConfig[ method ].error( ...args );
		};

		/**
		 * Whether every button in the instant row is ready to show: no availability check is
		 * still running, and every wallet in the row has drawn its button (or failed to).
		 * The other row's buttons are plain buttons, so they never wait.
		 */
		const allButtonsReady = computed( () => {
			const checking = Object.keys( paymentMethodConfig ).some( ( method ) => paymentMethodConfig[ method ].isAvailable &&
				isOfferedByServer( method ) &&
				!( method in availability )
			);
			// Only buttons drawn in the instant row; a boxed method's button is in its own view
			const drawing = availablePaymentMethods.value.some( ( method ) => paymentMethodConfig[ method ].waitForReady &&
				!isBoxed( method ) &&
				!readyButtons[ method ]
			);
			return !checking && !drawing;
		} );

		// Shows the one-click buttons together, so they don't appear one by one and push
		// each other around as each SDK loads. Once shown they stay shown, e.g. when a
		// wallet turns up after the timeout below.
		const buttonsShown = ref( false );
		let showButtonsTimer = null;
		const showButtons = () => {
			buttonsShown.value = true;
			clearTimeout( showButtonsTimer );
		};
		watch( allButtonsReady, ( ready ) => {
			if ( ready ) {
				showButtons();
			}
		}, { immediate: true } );

		onMounted( () => {
			// A slow or stuck SDK must not keep the donor from the other methods: show what's
			// there, and let the rest appear as they load, as they did before.
			if ( !buttonsShown.value ) {
				showButtonsTimer = setTimeout( showButtons, BUTTONS_SHOWN_TIMEOUT_MS );
			}

			// Ask about every offered method with a check, whatever the frequency, so a
			// wallet is ready to show (or stays hidden) when the donor changes it
			for ( const [ method, config ] of Object.entries( paymentMethodConfig ) ) {
				if ( config.isAvailable && isOfferedByServer( method ) ) {
					config.isAvailable().then( ( available ) => {
						if ( available ) {
							availability[ method ] = true;
						} else {
							markUnavailable( method );
						}
					} );
				}
			}
		} );
		onBeforeUnmount( () => clearTimeout( showButtonsTimer ) );

		return {
			selectPaymentMethod,
			submitDonation,
			paymentMethodGroups,
			methodGroupLabels,
			selectedHeading,
			isBoxed,
			submitSlot,
			root,
			chooseMethod,
			changeMethod,
			onSwapped,
			donorFields,
			donorDetails,
			// Not used in the template, but tests read it from wrapper.vm
			// eslint-disable-next-line vue/no-unused-properties
			availablePaymentMethods,
			buttonsShown,
			markButtonReady,
			onButtonError,
			hasFormFields,
			paymentMethod: selectedPaymentMethod,
			paymentMethodConfig
		};
	}
} );
</script>
