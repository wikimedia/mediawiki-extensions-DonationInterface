( function ( $, mw ) {
	$( function () {
		var mc = mw.monthlyConvert, originalGetSendData = mc.getSendData, frequencyUnit = 'month';

		mc.getSendData = function ( amount ) {
			var data = originalGetSendData( amount );
			data.frequency_unit = frequencyUnit;
			return data;
		};

		$( '.mc-diff-amount-link' ).on( 'click keypress', function ( e ) {
			if ( e.which === 13 || e.type === 'click' ) {
				frequencyUnit = 'year';
			}
		} );
		$( '.mc-back' ).on( 'click keypress', function ( e ) {
			if ( e.which === 13 || e.type === 'click' ) {
				frequencyUnit = 'month';
			}
		} );
	} );
} )( jQuery, mediaWiki );
