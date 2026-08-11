function apiPost( params ) {
	return ( new mw.Api() ).post( params );
}

const paymentMethodMap = {
	card: 'cc',
	paypal: 'paypal',
	applepay: 'apple',
	googlepay: 'google',
	ach: 'dd'
};

const frequencyUnitMap = {
	monthly: 'month',
	annual: 'year'
};

function getBaseDonateParams( donation ) {
	const params = {
		action: 'di_donate_gravy',
		gateway: 'gravy',
		result_page: 'combowiki',
		wmf_token: mw.config.get( 'wmf_token' ),
		email: donation.email,
		amount: donation.amount,
		currency: donation.currency,
		country: donation.country,
		payment_method: paymentMethodMap[ donation.paymentMethod ],
		opt_in: donation.optIn === 'yes' ? 1 : 0,
		uselang: mw.config.get( 'wgUserLanguage' )
	};

	if ( donation.employer ) {
		params.employer = donation.employer.trim();
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
		{
			first_name: donation.firstName,
			last_name: donation.lastName
		},
		paymentMethodData || {}
	);
}

function addGooglePayParams( donation, paymentData ) {
	const paymentMethodData = paymentData.paymentMethodData;
	const paymentMethodInfo = paymentMethodData.info;
	const paymentToken = paymentMethodData.tokenizationData.token;
	const donorInfo = paymentMethodInfo.billingAddress;

	return Object.assign(
		{},
		getBaseDonateParams( donation ),
		{
			full_name: donorInfo.name,
			email: paymentData.email,
			postal_code: donorInfo.postalCode,
			state_province: donorInfo.administrativeArea,
			city: donorInfo.locality,
			street_address: donorInfo.address1,
			payment_token: paymentToken,
			card_suffix: paymentMethodInfo.cardDetails,
			card_scheme: paymentMethodInfo.cardNetwork
		},
		paymentData || {}
	);
}

function submitDonation( donation, paymentMethodData ) {
	if ( donation.paymentMethod === 'googlepay' ) {
		return apiPost( addGooglePayParams( donation, paymentMethodData ) );
	}

	return apiPost( buildDonateParams( donation, paymentMethodData ) );
}

function createCheckoutSession( donation ) {
	const recurring = [ 'monthly', 'annual' ].includes( donation.frequency ) ? 1 : 0;
	return apiPost( {
		action: 'di_checkoutsession_gravy',
		gateway: 'gravy',
		amount: donation.amount,
		payment_method: paymentMethodMap[ donation.paymentMethod ],
		wmf_token: mw.config.get( 'wmf_token' ),
		country: donation.country,
		currency: donation.currency,
		recurring: recurring,
		uselang: mw.config.get( 'wgUserLanguage' )
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
		wmf_token: mw.config.get( 'wmf_token' ),
		payment_method: paymentMethodMap[ payload.paymentMethod ],
		country: payload.country,
		currency: payload.currency,
		amount: payload.amount
	};
	return apiPost( params );
}

module.exports = {
	validateApplePayPaymentSession,
	submitDonation,
	createCheckoutSession
};
