( function( $ ) {     
		$.getDynamicFormElements = function(){
				var numAttempt = $('input[name=numAttempt]').val();
				var token = $('input[name=token]').val();
				
                var tracking_data = '{"url": "' + escape(window.location) + '",' + \
                	'"pageref": "' + escape(document.referrer) + '",' + \
                	'"token": "' + escape( token ) + '",' + \
                	'"numAttempt": "' + escape( numAttempt ) + '"}';

                var processFormElements = function (data, status){
                	// set the numAttempt and the token
                	$('input[name=numAttempt]').val(data['dynamic_form_elements']['numAttempt']);
                	$('input[name=token]').val(data['dynamic_form_elements']['token']);
                	
                	// early return if non-required dynamic form elements are set
                	if ( typeof data['dynamic_form_elements']['contribution_tracking_id'] == 'undefined' ) {
                		return;
                	}
                    $('input[name=orderid]').val(data['dynamic_form_elements']['orderid']);
                    $('input[name=token]').val(data['dynamic_form_elements']['token']);
                    $('input[name=contribution_tracking_id]').val(data['dynamic_form_elements']['contribution_tracking_id']);
                    $('input[name=utm_source]').val(data['dynamic_form_elements']['tracking_data']['utm_source']);
                    $('input[name=utm_medium]').val(data['dynamic_form_elements']['tracking_data']['utm_medium']);
                    $('input[name=utm_campaign]').val(data['dynamic_form_elements']['tracking_data']['utm_campaign']);
                    $('input[name=referrer]').val(data['dynamic_form_elements']['tracking_data']['referrer']);
                    $('input[name=language]').val(data['dynamic_form_elements']['tracking_data']['language']);
                };

                $.post( wgScriptPath + '/api.php?' + Math.random() , {
                            'action' : 'pfp',
                            'dispatch' : 'get_required_dynamic_form_elements',
                            'format' : 'json',
                            'tracking_data' : tracking_data
                        }, processFormElements, 'json' );
        };

        return $( this );

} )( jQuery );

jQuery( document ).ready( jQuery.getDynamicFormElements );