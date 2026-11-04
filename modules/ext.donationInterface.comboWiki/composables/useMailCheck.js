const { ref } = require( 'vue' );
const MailCheck = require( 'ext.donationInterface.mailcheck' );

function useMailCheck() {
	let mcDomains = MailCheck.defaultDomains,
		initialised = false;
	const mailcheckSuggestion = ref( '' ),
		suggestedEmail = ref( '' ),
		multiCountrySubdomains = [ 'yahoo', 'hotmail', 'outlook', 'live' ],
		countryTlds = [
			'ca', 'cl', 'co.id', 'co.il', 'co.in', 'co.jp', 'co.uk', 'com', 'com.ar',
			'com.au', 'com.br', 'com.mx', 'com.pe', 'com.sg', 'cz', 'de', 'es',
			'fr', 'it', 'jp', 'kr', 'nl', 'pt', 'se'
		];

	// Adds the extra domains to check against. Safe to call more than once.
	const initialise = () => {
		if ( initialised ) {
			return;
		}
		mcDomains = mcDomains.concat( [
			'aim.com', 'alice.it', 'aon.at', 'bellsouth.net', 'bigpond.com',
			'bigpond.net.au', 'blueyonder.co.uk', 'btinternet.com',
			'btopenworld.com', 'charter.net', 'cox.net', 'docomo.ne.jp',
			'earthlink.net', 'email.it', 'embarqmail.com', 'ezweb.ne.jp',
			'fastwebnet.it', 'free.fr', 'frontier.com', 'gmx.at', 'gmx.de',
			'gmx.net', 'hetnet.nl', 'home.nl', 'i.softbank.jp', 'iinet.net.au',
			'inwind.it', 'juno.com', 'laposte.net', 'libero.it',
			'mindspring.com', 'netscape.net', 'neuf.fr', 'nifty.com',
			'ntlworld.com', 'o2.pl', 'online.no', 'optonline.net',
			'optusnet.com.au', 'orange.fr', 'pacbell.net', 'planet.nl',
			'pm.me', 'q.com', 'qq.com', 'roadrunner.com', 'rocketmail.com',
			'rogers.com', 'seznam.cz', 'sfr.fr', 'shaw.ca', 'sky.com',
			'skynet.be', 'sympatico.ca', 'talktalk.net', 'telefonica.net',
			'telenet.be', 'telia.com', 'telus.net', 'tin.it',
			'tiscali.co.uk', 'tiscali.it', 'tpg.com.au', 'umich.edu',
			'uol.com.br', 'videotron.ca', 'virgilio.it', 'wanadoo.fr',
			'web.de', 'windstream.net', 'wp.pl', 'xs4all.nl', 'xtra.co.nz',
			'ybb.ne.jp', 'ymail.com', 'ziggo.nl'
		] );

		multiCountrySubdomains.forEach( ( subdomain ) => {
			countryTlds.forEach( ( tlds ) => {
				mcDomains.push( subdomain + '.' + tlds );
			} );
		} );
		initialised = true;
	};

	MailCheck.domainThreshold = 2; // No way to set from opts!
	return {
		// The "Did you mean" message, as HTML with a .correction link around the suggested address
		mailcheckSuggestion,
		// The suggested address, for the .correction link to fill in
		suggestedEmail,
		initialise,
		runMailCheck: ( email ) => {
			// Using MailCheck.run for compatibility with useFieldValidation
			// MailCheck.run returns a suggestion when no callbacks are passed.
			const suggestion = MailCheck.run( {
				email: email,
				topLevelDomains: [],
				domains: mcDomains
			} );
			if ( suggestion && suggestion.full ) {
				suggestedEmail.value = suggestion.full;
				// The suggestion is built from what the donor typed, so escape it before
				// it goes into the message's HTML
				mailcheckSuggestion.value = mw.msg(
					'donate_interface-did-you-mean',
					mw.html.escape( suggestion.full )
				);
				return true;
			}
			suggestedEmail.value = '';
			mailcheckSuggestion.value = '';
			return false;
		}
	};
}

module.exports = {
	useMailCheck
};
