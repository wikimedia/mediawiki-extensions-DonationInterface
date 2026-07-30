function apiPost( params ) {
	return ( new mw.Api() ).post( params );
}

const paymentMethodMap = {
	card: 'cc',
	paypal: 'paypal'
};

function buildDonateParams( donation ) {
	const frequencyUnit = {
		monthly: 'month',
		annual: 'year'
	};

	const unit = frequencyUnit[ donation.frequency ];

	const params = {
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

	return params;

}

function addCardParams( params, cardPayload ) {
	params.gateway_session_id = cardPayload.gateway_session_id;
	params.payment_token = cardPayload.payment_token;
	params.card_scheme = cardPayload.card_scheme;
	params.card_suffix = cardPayload.card_suffix;
	params.color_depth = screen.colorDepth || 24;
	params.screen_height = screen.height || 0;
	params.screen_width = screen.width || 0;
	params.time_zone_offset = Math.floor( new Date().getTimezoneOffset() ) || 0;
}

function submitDonation( donation, cardPayload ) {
	const params = buildDonateParams( donation );
	if ( cardPayload ) {
		addCardParams( params, cardPayload );
	}
	return apiPost( params );
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

module.exports = {
	submitDonation,
	createCheckoutSession
};
