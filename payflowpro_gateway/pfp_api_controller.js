jQuery( function ( $ ) {
	// Do not fire the AJAX request if _nocache_ is set or we are not using a single-step form (known by lack of utm_source_id)
	if ( String( window.location ).indexOf( '_cache_' ) != -1 && String( window.location ).indexOf( 'utm_source_id' ) != -1 ) {
		var tracking_data = {
			'url': window.location,
			'pageref': document.referrer,
			'gateway': document.gateway,
			'payment_method': document.payment_method
		};
		$.post( mw.util.wikiScript( 'api' ),
			{
				'action': 'pfp',
				'dispatch': 'get_required_dynamic_form_elements',
				'format': 'json',
				'tracking_data': $.toJSON( tracking_data )
			},
			function ( data ) {
				if ( !data || !data['dynamic_form_elements'] ) {
					// Bad response
					// TODO throw some kind of error maybe?
					return;
				}
				var elements = data['dynamic_form_elements'];
				$( 'input[name=order_id]' ).val( elements['order_id'] );
				$( 'input[name=token]' ).val( elements['token'] );
				$( 'input[name=contribution_tracking_id]' ).val( elements['contribution_tracking_id'] );
				if ( elements['tracking_data'] ) {
					$( 'input[name=utm_source]' ).val( elements['tracking_data']['utm_source'] );
					$( 'input[name=utm_medium]' ).val( elements['tracking_data']['utm_medium'] );
					$( 'input[name=utm_campaign]' ).val( elements['tracking_data']['utm_campaign'] );
					$( 'input[name=referrer]' ).val( elements['tracking_data']['referrer'] );
					$( 'input[name=language]' ).val( elements['tracking_data']['language'] );
				}
			},
			'json'
		);
	}
} );
