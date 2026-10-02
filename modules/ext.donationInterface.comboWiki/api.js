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
	card: { method: 'cc' },
	adyen_card: { method: 'cc' },
	paypal: { method: 'paypal' },
	applepay: { method: 'apple' },
	googlepay: { method: 'google' },
	venmo: { method: 'venmo' },
	ach: { method: 'dd', submethod: 'ach' },
	sepadirectdebit: { method: 'rtbt', submethod: 'sepadirectdebit' }
};

/**
 * @param {string} paymentMethod ComboWiki payment option
 * @return {string|undefined} The server payment method code
 */
function getServerPaymentMethod( paymentMethod ) {
	const mapping = paymentMethodMap[ paymentMethod ];
	return mapping && mapping.method;
}

const frequencyUnitMap = {
	monthly: 'month',
	annual: 'year'
};

function getBaseDonateParams( donation ) {
	const params = {
		action: 'di_donate_' + ( donation.gateway || 'gravy' ),
		gateway: donation.gateway || 'gravy',
		result_page: 'combowiki',
		wmf_token: apiConfig.wmf_token,
		email: donation.email,
		amount: donation.amount,
		currency: donation.currency,
		country: donation.country,
		payment_method: getServerPaymentMethod( donation.paymentMethod ),
		phone: donation.phone,
		opt_in: donation.optIn === 'yes' ? 1 : 0,
		sms_opt_in: donation.smsOptin ? 1 : 0,
		uselang: apiConfig.wgUserLanguage,
		first_name: donation.firstName,
		last_name: donation.lastName,
		variant: donation.variant
	};

	const mapping = paymentMethodMap[ donation.paymentMethod ];
	if ( mapping && mapping.submethod ) {
		params.payment_submethod = mapping.submethod;
	}

	if ( donation.employer ) {
		params.employer = donation.employer.trim();
	}
	if ( donation.employerId ) {
		params.employer_id = donation.employerId;
	}

	const frequencyUnit = frequencyUnitMap[ donation.frequency ];
	if ( frequencyUnit ) {
		params.recurring = 1;
		params.frequency_unit = frequencyUnit;
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
	const recurring = [ 'monthly', 'annual' ].includes( donation.frequency ) ? 1 : 0;
	return apiPost( {
		action: 'di_checkoutsession_' + ( donation.gateway || 'gravy' ),
		gateway: donation.gateway || 'gravy',
		amount: donation.amount,
		payment_method: getServerPaymentMethod( donation.paymentMethod ),
		wmf_token: apiConfig.wmf_token,
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
	createCheckoutSession,
	apiPost
};
