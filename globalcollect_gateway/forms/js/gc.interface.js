/**
 * Basic utility function for loading the payment form from GlobalCollect
 * into the DOM. Overridden by the test environment's interface.
 */

( function ( mw, $ ) {

	mw.generatePaymentForm = function ( data ) {
		var $form = $( '<iframe>' )
			.attr( {
				src: data.result.formaction,
				width: 318,
				height: 314,
				frameborder: 0
			} );

		this.loadPaymentForm( $form );
	};

	mw.loadPaymentForm = function ( $form ) {
		var $payment = $( '#payment' );

		$payment.html( $form );
	};

}( mediaWiki, jQuery ) );
