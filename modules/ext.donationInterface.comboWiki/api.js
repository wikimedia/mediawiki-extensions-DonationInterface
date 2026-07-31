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

function buildDonateParams( donation, paymentMethodData ) {
	const frequencyUnit = {
		monthly: 'month',
		annual: 'year'
	};

	const unit = frequencyUnit[ donation.frequency ];

	let params = {
		action: 'di_donate_gravy',
		gateway: 'gravy',
		result_page: 'combowiki',
		wmf_token: mw.config.get( 'wmf_token' ),
		first_name: donation.firstName,
		last_name: donation.lastName,
		email: donation.email,
		amount: donation.amount,
		currency: donation.currency,
		country: donation.country,
		payment_method: paymentMethodMap[ donation.paymentMethod ],
		opt_in: donation.optIn === 'yes' ? 1 : 0,
		uselang: mw.config.get( 'wgUserLanguage' )
	};

	if ( unit ) {
		params.recurring = 1;
		params.frequency_unit = unit;
	}
	if ( paymentMethodData ) {
		params = Object.assign( {}, params, paymentMethodData );
	}
	return params;

}

function addGooglePayParams( donation, paymentData ) {
	// copied from buildDonateParams for now
	const frequencyUnit = {
		monthly: 'month',
		annual: 'year'
	};

	const unit = frequencyUnit[ donation.frequency ];

	// google specific
	const paymentToken = paymentData.paymentMethodData.tokenizationData.token,
		donorInfo = paymentData.paymentMethodData.info.billingAddress;

	let params = {
		action: 'di_donate_gravy',
		gateway: 'gravy',
		result_page: 'combowiki',
		wmf_token: mw.config.get( 'wmf_token' ),
		full_name: donorInfo.name,
		email: donation.email,
		amount: donation.amount,
		currency: donation.currency,
		country: donation.country,
		payment_method: paymentMethodMap[ donation.paymentMethod ],
		opt_in: donation.optIn === 'yes' ? 1 : 0,
		uselang: mw.config.get( 'wgUserLanguage' )
	};

	// combine with above params, pasted to get it to work
	params.postal_code = donorInfo.postalCode;
	params.state_province = donorInfo.administrativeArea;
	params.city = donorInfo.locality;
	params.street_address = donorInfo.address1;
	params.email = paymentData.email;
	params.payment_token = paymentToken;
	params.card_suffix = paymentData.paymentMethodData.info.cardDetails;
	params.card_scheme = paymentData.paymentMethodData.info.cardNetwork;

	if ( unit ) {
		params.recurring = 1;
		params.frequency_unit = unit;
	}

	if ( paymentData ) {
		params = Object.assign( {}, params, paymentData );
	}
	return params;
}

function submitDonation( donation, paymentMethodData ) {
	if ( donation.paymentMethod === 'googlepay' ) {
		// how do we want to handle payment method specific things
		return apiPost( addGooglePayParams( donation, paymentMethodData ) );
	} else {
		return apiPost( buildDonateParams( donation, paymentMethodData ) );
	}
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
