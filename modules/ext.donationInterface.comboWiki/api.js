function apiPost( params ) {
	return ( new mw.Api() ).post( params );
}

function buildGravyDonateParams( donation, cardPayload ) {
	const frequencyUnit = {
		monthly: 'month',
		annual: 'year'
	};

	const unit = frequencyUnit[ donation.frequency ];

	const params = {
		action: 'di_donate_gravy',
		gateway: 'gravy',
		wmf_token: mw.config.get( 'wmf_token' ),
		first_name: donation.firstName,
		last_name: donation.lastName,
		email: donation.email,
		amount: donation.amount,
		currency: donation.currency,
		country: donation.country,
		payment_method: 'cc',
		opt_in: donation.optIn === 'yes' ? 1 : 0,
		uselang: mw.config.get( 'wgUserLanguage' ),
		gateway_session_id: cardPayload.gateway_session_id,
		payment_token: cardPayload.payment_token,
		card_scheme: cardPayload.card_scheme,
		card_suffix: cardPayload.card_suffix,
		color_depth: screen.colorDepth || 24,
		screen_height: screen.height || 0,
		screen_width: screen.width || 0,
		time_zone_offset: Math.floor( new Date().getTimezoneOffset() ) || 0
	};

	if ( unit ) {
		params.recurring = 1;
		params.frequency_unit = unit;
	}

	return params;

}

function submitDonation( donation, cardPayload ) {
	return apiPost( buildGravyDonateParams( donation, cardPayload ) );
}

module.exports = {
	submitDonation
};
