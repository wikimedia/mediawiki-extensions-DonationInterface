function apiPost( params ) {
	return ( new mw.Api() ).post( params );
}

let apiConfig = {};
function init( config ) {
	apiConfig = config;
}

/**
 * ComboWiki payment option => server payment method, plus submethod when the
 * option is a single submethod with its own country rules (e.g. SEPA under rtbt).
 */
const paymentMethodMap = {
	card: { method: 'cc', gateway: 'gravy' },
	adyen_card: { method: 'cc', gateway: 'adyen' },
	paypal: { method: 'paypal', gateway: 'gravy' },
	braintree_paypal: { method: 'paypal', gateway: 'braintree' },
	applepay: { method: 'apple', gateway: 'gravy' },
	googlepay: { method: 'google', gateway: 'gravy' },
	venmo: { method: 'venmo', gateway: 'gravy' },
	ach: { method: 'dd', submethod: 'ach', gateway: 'gravy' },
	sepadirectdebit: { method: 'rtbt', submethod: 'sepadirectdebit', gateway: 'gravy' },
	adyen_googlepay: { method: 'google', gateway: 'adyen' }
};

/**
 * @param {string} paymentMethod ComboWiki payment option
 * @return {string|undefined} The server payment method code
 */
function getServerPaymentMethod( paymentMethod ) {
	const mapping = paymentMethodMap[ paymentMethod ];
	return mapping && mapping.method;
}

/**
 * @param {string} paymentMethod ComboWiki payment option
 * @return {string|undefined} The server payment submethod code, only for an option
 *  that is a single submethod (e.g. SEPA under rtbt)
 */
function getServerPaymentSubmethod( paymentMethod ) {
	const mapping = paymentMethodMap[ paymentMethod ];
	return mapping && mapping.submethod;
}

/**
 * @param {string} paymentMethod A paymentMethodMap key (e.g. 'card', 'adyen_card')
 * @return {string|undefined} The gateway that handles the payment method (e.g. 'gravy')
 */
function getGateway( paymentMethod ) {
	const mapping = paymentMethodMap[ paymentMethod ];
	return mapping && mapping.gateway;
}

/**
 * @param {string} gateway
 * @return {string} The edit token for the gateway, or the token of the selected
 *  gateway when the page has no token for it
 */
function getToken( gateway ) {
	return ( apiConfig.wmf_tokens || {} )[ gateway ] || apiConfig.wmf_token;
}

const frequencyUnitMap = {
	monthly: 'month',
	annual: 'year'
};

/**
 * @param {string} frequency ComboWiki frequency, e.g. monthly
 * @return {string|undefined} The server frequency unit, only for a recurring frequency
 */
function getFrequencyUnit( frequency ) {
	return frequencyUnitMap[ frequency ];
}

/**
 * @param {string} frequency ComboWiki frequency, e.g. monthly
 * @return {number|undefined} 1 for a recurring frequency
 */
function getRecurring( frequency ) {
	return getFrequencyUnit( frequency ) ? 1 : undefined;
}

/**
 * @param {string} [employer] Employer name as entered by the donor
 * @return {string|undefined} The trimmed name, only when one was entered
 */
function getEmployer( employer ) {
	return employer ? employer.trim() : undefined;
}

/**
 * @param {number|string} [employerId] Employer id from the employer search
 * @return {number|string|undefined} The id, only when one was picked
 */
function getEmployerId( employerId ) {
	return employerId || undefined;
}

/**
 * Server field name => the donation property that holds its value, for the fields of the
 * details form (PaymentMethodFormFields).
 *
 * Needed because the details form works with server field names: the server sends each
 * method's formFields and validationRules keyed by them, and the form reports what the donor
 * entered keyed by them too. The donation, however, keeps those values in camelCase properties.
 * - PaymentMethodFormFields uses it to prefill each input from the donation
 *   (e.g. first_name from donation.firstName).
 * - PaymentForm.updateDonorDetails uses it to copy the donor's entries back into the donation.
 *
 * getBaseDonateParams maps these same properties back to server field names when submitting,
 * so any change here must also be made there. Otherwise the form reads or writes a donation
 * property that isn't submitted.
 */
const donateParamMap = {
	first_name: 'firstName',
	last_name: 'lastName',
	email: 'email',
	street_address: 'streetAddress',
	street_number: 'streetNumber',
	city: 'city',
	state_province: 'stateProvince',
	postal_code: 'postalCode',
	fiscal_number: 'fiscalNumber'
};

/**
 * @param {Object} donation
 * @return {Object} The donate API params. A param that doesn't apply (e.g. employer when
 *  none was given) is undefined, and mw.Api drops undefined params, so the server never sees it.
 */
function getBaseDonateParams( donation ) {
	const gateway = getGateway( donation.paymentMethod );
	const params = {
		action: 'di_donate_' + gateway,
		gateway: gateway,
		result_page: 'combowiki',
		wmf_token: getToken( gateway ),
		amount: donation.amount,
		currency: donation.currency,
		country: donation.country,
		payment_method: getServerPaymentMethod( donation.paymentMethod ),
		payment_submethod: getServerPaymentSubmethod( donation.paymentMethod ),
		phone: donation.phone,
		opt_in: donation.optIn,
		sms_opt_in: donation.smsOptin ? 1 : 0,
		uselang: apiConfig.wgUserLanguage,
		variant: donation.variant,
		employer: getEmployer( donation.employer ),
		employer_id: getEmployerId( donation.employerId ),
		recurring: getRecurring( donation.frequency ),
		frequency_unit: getFrequencyUnit( donation.frequency )
	};
	for ( const [ apiName, propertyName ] of Object.entries( donateParamMap ) ) {
		if ( donation[ propertyName ] ) {
			params[ apiName ] = donation[ propertyName ];
		}
	}
	return params;
}

function buildDonateParams( donation, paymentMethodData ) {
	return Object.assign(
		{},
		getBaseDonateParams( donation ),
		paymentMethodData || {}
	);
}

function submitDonation( donation, paymentMethodData ) {
	return apiPost( buildDonateParams( donation, paymentMethodData ) );
}

function createCheckoutSession( donation ) {
	const gateway = getGateway( donation.paymentMethod );
	const recurring = [ 'monthly', 'annual' ].includes( donation.frequency ) ? 1 : 0;
	return apiPost( {
		action: 'di_checkoutsession_' + gateway,
		gateway: gateway,
		amount: donation.amount,
		payment_method: getServerPaymentMethod( donation.paymentMethod ),
		wmf_token: getToken( gateway ),
		country: donation.country,
		currency: donation.currency,
		recurring: recurring,
		uselang: apiConfig.wgUserLanguage
	} ).then( ( data ) => {
		const sessionId = data.checkout_session && data.checkout_session.session_id;
		if ( !sessionId ) {
			throw new Error( 'no-session' );
		}
		return sessionId;
	} );
}

function validateApplePayPaymentSession( payload ) {
	const params = {
		action: 'di_applesession_gravy',
		validation_url: payload.validationURL,
		wmf_token: apiConfig.wmf_token,
		payment_method: getServerPaymentMethod( payload.paymentMethod ),
		country: payload.country,
		currency: payload.currency,
		amount: payload.amount
	};
	return apiPost( params );
}

module.exports = {
	init,
	validateApplePayPaymentSession,
	submitDonation,
	paymentMethodMap,
	donateParamMap,
	createCheckoutSession,
	apiPost,
	getGateway
};
