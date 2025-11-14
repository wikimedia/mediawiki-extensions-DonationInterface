const trackingParams = {
	addTo: ( params ) => {
		const allQueryParams = new URLSearchParams( window.location.search );
		[ 'wmf_campaign', 'wmf_medium', 'wmf_source' ].forEach( ( paramName ) => {
			if ( allQueryParams.get( paramName ) !== null ) {
				params[ paramName ] = allQueryParams.get( paramName );
			}
		} );
	}
};

module.exports = exports = trackingParams;
