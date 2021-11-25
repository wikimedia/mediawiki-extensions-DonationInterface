( function ( $ ) {
    $( function () {
        var autocompleteCache = {},
            // This triggers both on 'select' and 'focus' events to set the displayed
            // value to the employer/subsidiary name and the hidden field value to the
            // employer ID. The focus event is triggered on arrow key navigation.
            selectFocusFunction = function ( event, ui ) {
                event.preventDefault();
                $( '#employer' ).val( ui.item.label );
                $( '#employer_id' ).val( ui.item.value );
            };

        $( '#employer' ).autocomplete( {
            delay: 300, //throttle in milliseconds
            source: function ( request, response ) {
                //reset employer_id each time a new search starts
                $( '#employer_id' ).val( '' );

                var apiParameters = {
                    action: 'employerSearch',
                    employer: request.term,
                    format: 'json'
                }, cached = autocompleteCache[ request.term ];

                if ( cached ) {
                    response( cached );
                } else {
                    $.get( mw.util.wikiScript( 'api' ), apiParameters ).done( function ( data ) {
                        //check if the api sent back any errors and if so jump out here
                        if ( data.error ) {
                            response(); // this has to be called in all scenarios for preserve the widget state
                        } else {
                            // transform result to suit autocomplete format
                            var result = data.result.map( function ( item ) {
                                    return {
                                        label: item.name,
                                        value: item.id
                                    };
                                } ),
                            //trim results
                            output = result.slice( 0, 10 );

                            // Note: jquery autocomplete handles escaping by injecting
                            // values and labels into the DOM using jquery text() and
                            // val() methods. So, additional escaping is not needed.

                            //cache result
                            autocompleteCache[ request.term ] = output;
                            response( output );
                        }
                    } );
                }
            },
            select: selectFocusFunction,
            focus: selectFocusFunction
        } );
    } );
} )( jQuery );
