/* global describe it expect jest */

const api = require( '../../../modules/ext.donationInterface.comboWiki/api.js' );

describe( 'ComboWiki donate API params, from donateParamMap', () => {
	const paramsFor = ( donation ) => {
		const post = jest.spyOn( mw.Api.prototype, 'post' ).mockReturnValue( new Promise( () => {} ) );
		api.init( { wmf_token: 'token', wgUserLanguage: 'de' } );
		api.submitDonation( donation );
		const params = post.mock.lastCall[ 0 ];
		post.mockRestore();
		return params;
	};

	it( 'sends the donation\'s details under their server field names', () => {
		expect( paramsFor( {
			paymentMethod: 'paypal',
			firstName: 'Jo',
			lastName: 'Doe',
			email: 'jo@example.org',
			stateProvince: 'NSW',
			city: 'Sydney'
		} ) ).toMatchObject( {
			first_name: 'Jo',
			last_name: 'Doe',
			email: 'jo@example.org',
			state_province: 'NSW',
			city: 'Sydney',
			payment_method: 'paypal'
		} );
	} );

	it( 'sends a recurring ACH donation with an employer as before', () => {
		expect( paramsFor( {
			paymentMethod: 'ach',
			frequency: 'monthly',
			amount: 10,
			currency: 'USD',
			country: 'US',
			email: 'jo@example.org',
			firstName: 'Jo',
			lastName: 'Doe',
			phone: null,
			optIn: '1',
			smsOptin: false,
			variant: null,
			employer: ' Wikimedia ',
			employerId: 42
		} ) ).toEqual( {
			action: 'di_donate_gravy',
			gateway: 'gravy',
			result_page: 'combowiki',
			wmf_token: 'token',
			uselang: 'de',
			email: 'jo@example.org',
			amount: 10,
			currency: 'USD',
			country: 'US',
			payment_method: 'dd',
			payment_submethod: 'ach',
			phone: null,
			opt_in: '1',
			sms_opt_in: 0,
			first_name: 'Jo',
			last_name: 'Doe',
			variant: null,
			employer: 'Wikimedia',
			employer_id: 42,
			recurring: 1,
			frequency_unit: 'month'
		} );
	} );

	it( 'leaves out the params that don\'t apply to a one-time card donation', () => {
		const params = paramsFor( {
			paymentMethod: 'card',
			frequency: 'once',
			smsOptin: true,
			employer: '',
			employerId: 0
		} );
		expect( params.payment_method ).toBe( 'cc' );
		expect( params.sms_opt_in ).toBe( 1 );
		// Undefined, which mw.Api drops before posting, as when the params weren't set at all
		for ( const name of [ 'payment_submethod', 'employer', 'employer_id', 'recurring', 'frequency_unit' ] ) {
			expect( params[ name ] ).toBeUndefined();
		}
	} );
} );

describe( 'ComboWiki gateway and token for each payment method', () => {
	const TOKENS = { wmf_token: 'gravy-token', wmf_tokens: { gravy: 'gravy-token', adyen: 'adyen-token' } };

	const postedParams = ( config, send ) => {
		const post = jest.spyOn( mw.Api.prototype, 'post' ).mockReturnValue( new Promise( () => {} ) );
		api.init( config );
		send();
		const params = post.mock.lastCall[ 0 ];
		post.mockRestore();
		return params;
	};

	it( 'knows the gateway of each payment method', () => {
		expect( api.getGateway( 'card' ) ).toBe( 'gravy' );
		expect( api.getGateway( 'adyen_card' ) ).toBe( 'adyen' );
		expect( api.getGateway( 'unknown' ) ).toBeUndefined();
	} );

	it( 'sends a donation to the gateway of its payment method, with the token of that gateway', () => {
		expect( postedParams( TOKENS, () => api.submitDonation( { paymentMethod: 'adyen_card' } ) ) ).toMatchObject( {
			action: 'di_donate_adyen',
			gateway: 'adyen',
			wmf_token: 'adyen-token'
		} );
	} );

	it( 'uses the token of the selected gateway when the page has no token for the gateway', () => {
		expect( postedParams( { wmf_token: 'gravy-token' }, () => api.submitDonation( { paymentMethod: 'adyen_card' } ) ) )
			.toMatchObject( { action: 'di_donate_adyen', wmf_token: 'gravy-token' } );
	} );

	it( 'creates a recurring checkout session on the gateway of the payment method', () => {
		expect( postedParams( TOKENS, () => api.createCheckoutSession( { paymentMethod: 'card', frequency: 'monthly' } ) ) )
			.toMatchObject( {
				action: 'di_checkoutsession_gravy',
				gateway: 'gravy',
				wmf_token: 'gravy-token',
				recurring: 1
			} );
	} );

	it( 'creates a one-time checkout session for a one-time donation', () => {
		expect( postedParams( TOKENS, () => api.createCheckoutSession( { paymentMethod: 'card', frequency: 'once' } ) ).recurring )
			.toBe( 0 );
	} );
} );
